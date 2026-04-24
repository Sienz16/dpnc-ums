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

test('superadmin can force complete a reopened match and writes audit log', function () {
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
        ->postJson("/admin/matches/{$match->id}/reopen", ['reason' => 'Semakan pentadbiran'])
        ->assertOk();

    $response = $this->actingAs($superadmin)
        ->postJson("/admin/matches/{$match->id}/force-complete", ['reason' => 'Deadline reached']);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'completed');
    $response->assertJsonPath('data.completion_type', 'force_completed');
    $response->assertJsonPath('data.result_state', 'final');

    $this->assertDatabaseHas('audit_logs', [
        'entity_type' => 'match',
        'entity_id' => $match->id,
        'action' => 'force_completed',
        'reason' => 'Deadline reached',
    ]);

    $this->assertDatabaseHas('match_results', [
        'match_id' => $match->id,
        'is_force_completed' => 1,
        'is_provisional' => 0,
    ]);
});

test('force complete rejects missing judge score sheets when some judges have not submitted', function () {
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

    $this->actingAs($superadmin)
        ->postJson("/admin/matches/{$match->id}/force-complete", ['reason' => 'Hakim terakhir bermasalah'])
        ->assertUnprocessable()
        ->assertInvalid(['score_sheets']);
});

test('superadmin can force complete by entering missing judge score sheets on behalf of them', function () {
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
        ->postJson("/admin/matches/{$match->id}/force-complete", [
            'reason' => 'Dua akaun hakim gagal diakses',
            'score_sheets' => [
                [
                    'judge_id' => $judges[1]->id,
                    ...$payload,
                ],
                [
                    'judge_id' => $judges[2]->id,
                    ...$payload,
                    'mark_pm' => 77,
                    'margin' => 3,
                ],
            ],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'completed');
    $response->assertJsonPath('data.completion_type', 'force_completed');
    $response->assertJsonPath('data.result_state', 'final');

    expect($match->fresh()->judgeAssignments()->whereNull('submitted_at')->count())->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'entity_type' => 'score_sheet',
        'action' => 'admin_submitted_on_behalf',
        'reason' => 'Dua akaun hakim gagal diakses',
    ]);
});

test('superadmin can correct a completed match score sheet and result is recalculated', function () {
    [$superadmin, $judges, $match, $bestSpeakerId] = makeForceCompleteFixture();

    $submittedPayload = [
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
        $this->actingAs($judge)->postJson("/judge/matches/{$match->id}/score-sheet/submit", $submittedPayload)->assertOk();
    }

    $oldOfficialOppositionScore = (float) $match->fresh()->result()->firstOrFail()->official_team_score_opposition;

    $response = $this->actingAs($superadmin)
        ->patchJson("/admin/matches/{$match->id}/score-sheets/{$judges[0]->id}", [
            'mark_pm' => 75,
            'mark_tpm' => 75,
            'mark_m1' => 75,
            'mark_kp' => 80,
            'mark_tkp' => 80,
            'mark_p1' => 80,
            'mark_penggulungan_gov' => 31,
            'mark_penggulungan_opp' => 42,
            'margin' => 8,
            'best_debater_member_id' => $bestSpeakerId,
            'reason' => 'Markah hakim tersalah salin',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.judge_id', $judges[0]->id);

    $freshResult = $match->fresh()->result()->firstOrFail();

    expect((float) $freshResult->official_team_score_opposition)->toBeGreaterThan($oldOfficialOppositionScore);

    $this->assertDatabaseHas('audit_logs', [
        'entity_type' => 'score_sheet',
        'action' => 'admin_corrected',
        'reason' => 'Markah hakim tersalah salin',
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
