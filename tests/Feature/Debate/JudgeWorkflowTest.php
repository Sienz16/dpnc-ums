<?php

use App\Domain\Debate\Enums\JudgeAssignmentMode;
use App\Domain\Debate\Enums\MatchStatus;
use App\Domain\Debate\Enums\ScoreSheetState;
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

function makeScoringFixture(): array
{
    $round = Round::factory()->create();
    $room = Room::factory()->create();

    $governmentTeam = Team::factory()->create();
    $oppositionTeam = Team::factory()->create();

    $govPm = TeamMember::factory()->create([
        'team_id' => $governmentTeam->id,
        'speaker_position' => SpeakerPosition::SpeakerOne,
    ]);

    TeamMember::factory()->create([
        'team_id' => $governmentTeam->id,
        'speaker_position' => SpeakerPosition::SpeakerTwo,
    ]);

    TeamMember::factory()->create([
        'team_id' => $governmentTeam->id,
        'speaker_position' => SpeakerPosition::SpeakerThree,
    ]);

    TeamMember::factory()->create([
        'team_id' => $oppositionTeam->id,
        'speaker_position' => SpeakerPosition::SpeakerOne,
    ]);

    TeamMember::factory()->create([
        'team_id' => $oppositionTeam->id,
        'speaker_position' => SpeakerPosition::SpeakerTwo,
    ]);

    TeamMember::factory()->create([
        'team_id' => $oppositionTeam->id,
        'speaker_position' => SpeakerPosition::SpeakerThree,
    ]);

    $match = DebateMatch::factory()->create([
        'round_id' => $round->id,
        'room_id' => $room->id,
        'government_team_id' => $governmentTeam->id,
        'opposition_team_id' => $oppositionTeam->id,
        'judge_panel_size' => 3,
    ]);

    $judges = User::factory()->count(3)->judge()->create();

    foreach ($judges as $judge) {
        JudgeAssignment::query()->create([
            'match_id' => $match->id,
            'judge_id' => $judge->id,
            'assigned_mode' => JudgeAssignmentMode::Manual,
        ]);
    }

    return [
        'match' => $match,
        'judges' => $judges,
        'best_debater_member_id' => $govPm->id,
    ];
}

test('reserve speaker cannot be selected as best debater', function () {
    ['match' => $match, 'judges' => $judges] = makeScoringFixture();

    $reserveSpeaker = TeamMember::factory()->create([
        'team_id' => $match->government_team_id,
        'speaker_position' => SpeakerPosition::SpeakerFour,
    ]);

    $this->actingAs($judges[0])->postJson("/judge/matches/{$match->id}/check-in")->assertOk();

    $response = $this->actingAs($judges[0])->postJson("/judge/matches/{$match->id}/score-sheet/submit", [
        'mark_pm' => 76,
        'mark_tpm' => 76,
        'mark_m1' => 76,
        'mark_kp' => 75,
        'mark_tkp' => 75,
        'mark_p1' => 75,
        'mark_penggulungan_gov' => 75,
        'mark_penggulungan_opp' => 74,
        'margin' => 2,
        'best_debater_member_id' => $reserveSpeaker->id,
    ]);

    $response->assertUnprocessable();
});

test('match becomes in progress when all assigned judges check in', function () {
    ['match' => $match, 'judges' => $judges] = makeScoringFixture();

    $this->actingAs($judges[0])->postJson("/judge/matches/{$match->id}/check-in")->assertOk();
    $this->actingAs($judges[1])->postJson("/judge/matches/{$match->id}/check-in")->assertOk();

    expect($match->fresh()->status)->toBe(MatchStatus::Pending);

    $this->actingAs($judges[2])->postJson("/judge/matches/{$match->id}/check-in")->assertOk();

    expect($match->fresh()->status)->toBe(MatchStatus::InProgress);
});

test('judge cannot submit before check in', function () {
    ['match' => $match, 'judges' => $judges, 'best_debater_member_id' => $memberId] = makeScoringFixture();

    $payload = [
        'mark_pm' => 76,
        'mark_tpm' => 76,
        'mark_m1' => 76,
        'mark_kp' => 75,
        'mark_tkp' => 75,
        'mark_p1' => 75,
        'mark_penggulungan_gov' => 75,
        'mark_penggulungan_opp' => 74,
        'margin' => 2,
        'best_debater_member_id' => $memberId,
    ];

    $response = $this->actingAs($judges[0])->postJson("/judge/matches/{$match->id}/score-sheet/submit", $payload);

    $response->assertUnprocessable();
});

test('match auto completes normally when all assigned judges submit', function () {
    ['match' => $match, 'judges' => $judges, 'best_debater_member_id' => $memberId] = makeScoringFixture();

    $payload = [
        'mark_pm' => 76,
        'mark_tpm' => 76,
        'mark_m1' => 76,
        'mark_kp' => 75,
        'mark_tkp' => 75,
        'mark_p1' => 75,
        'mark_penggulungan_gov' => 75,
        'mark_penggulungan_opp' => 74,
        'margin' => 2,
        'best_debater_member_id' => $memberId,
    ];

    foreach ($judges as $judge) {
        $this->actingAs($judge)->postJson("/judge/matches/{$match->id}/check-in")->assertOk();
        $this->actingAs($judge)->postJson("/judge/matches/{$match->id}/score-sheet/submit", $payload)->assertOk();
    }

    $freshMatch = $match->fresh();

    expect($freshMatch->status)->toBe(MatchStatus::Completed);
    expect($freshMatch->result_state->value)->toBe('final');
    expect($freshMatch->completion_type->value)->toBe('normal');

    $firstJudgeSheet = $freshMatch->scoreSheets()->where('judge_id', $judges[0]->id)->first();

    expect($firstJudgeSheet->state)->toBe(ScoreSheetState::Submitted);

    $editAttempt = $this->actingAs($judges[0])->putJson("/judge/matches/{$match->id}/score-sheet/draft", [
        ...$payload,
        'mark_pm' => 90,
    ]);

    $editAttempt->assertUnprocessable();
});
