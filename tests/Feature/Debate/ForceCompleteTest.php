<?php

use App\Domain\Debate\Enums\JudgeAssignmentMode;
use App\Domain\Debate\Enums\SpeakerPosition;
use App\Models\DebateMatch;
use App\Models\JudgeAssignment;
use App\Models\Room;
use App\Models\Round;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeForceCompleteFixture(): array
{
    $superadmin = User::factory()->superadmin()->create();
    $judges = User::factory()->count(3)->judge()->create();

    $governmentTeam = Team::factory()->create();
    $oppositionTeam = Team::factory()->create();

    $govPm = TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerOne]);
    TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerTwo]);
    TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerThree]);
    TeamMember::factory()->create(['team_id' => $oppositionTeam->id, 'speaker_position' => SpeakerPosition::SpeakerOne]);
    TeamMember::factory()->create(['team_id' => $oppositionTeam->id, 'speaker_position' => SpeakerPosition::SpeakerTwo]);
    TeamMember::factory()->create(['team_id' => $oppositionTeam->id, 'speaker_position' => SpeakerPosition::SpeakerThree]);

    $match = DebateMatch::factory()->create([
        'round_id' => Round::factory()->create()->id,
        'room_id' => Room::factory()->create()->id,
        'government_team_id' => $governmentTeam->id,
        'opposition_team_id' => $oppositionTeam->id,
        'judge_panel_size' => 3,
    ]);

    foreach ($judges as $judge) {
        JudgeAssignment::query()->create([
            'match_id' => $match->id,
            'judge_id' => $judge->id,
            'assigned_mode' => JudgeAssignmentMode::Manual,
        ]);
    }

    return [$superadmin, $judges, $match, $govPm->id];
}

test('force complete requires reason', function () {
    [$superadmin, $_judges, $match] = makeForceCompleteFixture();

    $response = $this->actingAs($superadmin)
        ->postJson("/admin/matches/{$match->id}/force-complete", []);

    $response->assertInvalid(['reason']);
});

test('superadmin can force complete and creates provisional result with audit log', function () {
    [$superadmin, $judges, $match, $bestSpeakerId] = makeForceCompleteFixture();

    $payload = [
        'mark_pm' => 76,
        'mark_tpm' => 76,
        'mark_m1' => 76,
        'mark_kp' => 75,
        'mark_tkp' => 75,
        'mark_p1' => 75,
        'mark_penggulungan_gov' => 35,
        'mark_penggulungan_opp' => 34,
        'margin' => 2,
        'best_debater_member_id' => $bestSpeakerId,
    ];

    $this->actingAs($judges[0])->postJson("/judge/matches/{$match->id}/check-in")->assertOk();
    $this->actingAs($judges[0])->postJson("/judge/matches/{$match->id}/score-sheet/submit", $payload)->assertOk();

    $response = $this->actingAs($superadmin)
        ->postJson("/admin/matches/{$match->id}/force-complete", ['reason' => 'Deadline reached']);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'completed');
    $response->assertJsonPath('data.completion_type', 'force_completed');
    $response->assertJsonPath('data.result_state', 'provisional');

    $this->assertDatabaseHas('audit_logs', [
        'entity_type' => 'match',
        'entity_id' => $match->id,
        'action' => 'force_completed',
        'reason' => 'Deadline reached',
    ]);

    $this->assertDatabaseHas('match_results', [
        'match_id' => $match->id,
        'is_force_completed' => 1,
        'is_provisional' => 1,
    ]);
});

test('reopen preserves existing submissions and result while allowing selective edit', function () {
    [$superadmin, $judges, $match, $bestSpeakerId] = makeForceCompleteFixture();

    $payload = [
        'mark_pm' => 76,
        'mark_tpm' => 76,
        'mark_m1' => 76,
        'mark_kp' => 75,
        'mark_tkp' => 75,
        'mark_p1' => 75,
        'mark_penggulungan_gov' => 35,
        'mark_penggulungan_opp' => 34,
        'margin' => 2,
        'best_debater_member_id' => $bestSpeakerId,
    ];

    foreach ($judges as $judge) {
        $this->actingAs($judge)->postJson("/judge/matches/{$match->id}/check-in")->assertOk();
        $this->actingAs($judge)->postJson("/judge/matches/{$match->id}/score-sheet/submit", $payload)->assertOk();
    }

    $this->actingAs($superadmin)
        ->postJson("/admin/matches/{$match->id}/reopen", ['reason' => 'Need correction'])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $existingSheet = $match->scoreSheets()->where('judge_id', $judges[0]->id)->firstOrFail();
    $existingAssignment = $match->judgeAssignments()->where('judge_id', $judges[0]->id)->firstOrFail();

    expect($existingSheet->state->value)->toBe('submitted');
    expect($existingSheet->submitted_at)->not->toBeNull();
    expect($existingAssignment->submitted_at)->not->toBeNull();

    $this->assertDatabaseHas('match_results', ['match_id' => $match->id]);

    $this->actingAs($judges[0])
        ->putJson("/judge/matches/{$match->id}/score-sheet/draft", [
            ...$payload,
            'mark_pm' => 82,
        ])
        ->assertOk()
        ->assertJsonPath('data.mark_pm', '82.0')
        ->assertJsonPath('data.state', 'draft');

    expect($existingAssignment->fresh()->submitted_at)->toBeNull();
});
