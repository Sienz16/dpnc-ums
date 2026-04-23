<?php

use App\Domain\Debate\Enums\JudgeAssignmentMode;
use App\Domain\Debate\Enums\SpeakerPosition;
use App\Models\DebateMatch;
use App\Models\JudgeAssignment;
use App\Models\Room;
use App\Models\Round;
use App\Models\ScoreSheet;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAssignmentFixture(): array
{
    $superadmin = User::factory()->superadmin()->create();
    $judges = User::factory()->count(3)->judge()->create();

    $governmentTeam = Team::factory()->create();
    $oppositionTeam = Team::factory()->create();

    TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerOne]);
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

    ScoreSheet::factory()->create([
        'match_id' => $match->id,
        'judge_id' => $judges[0]->id,
    ]);

    return [$superadmin, $match];
}

test('superadmin can clear all judge assignments from a pending match', function () {
    [$superadmin, $match] = makeAssignmentFixture();

    $this->actingAs($superadmin)
        ->deleteJson("/admin/matches/{$match->id}/assignments")
        ->assertOk()
        ->assertJson(['data' => []]);

    expect($match->fresh()->judgeAssignments()->count())->toBe(0);
    expect($match->fresh()->scoreSheets()->count())->toBe(0);
});

test('manual assignment rejects judges already assigned in another room of same round', function () {
    $superadmin = User::factory()->superadmin()->create();

    $round = Round::factory()->create();
    $roomA = Room::factory()->create();
    $roomB = Room::factory()->create();

    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $teamC = Team::factory()->create();
    $teamD = Team::factory()->create();

    $matchA = DebateMatch::factory()->create([
        'round_id' => $round->id,
        'room_id' => $roomA->id,
        'government_team_id' => $teamA->id,
        'opposition_team_id' => $teamB->id,
        'judge_panel_size' => 3,
    ]);

    $matchB = DebateMatch::factory()->create([
        'round_id' => $round->id,
        'room_id' => $roomB->id,
        'government_team_id' => $teamC->id,
        'opposition_team_id' => $teamD->id,
        'judge_panel_size' => 3,
    ]);

    $judgeAssignedInRound = User::factory()->judge()->create();
    $otherJudgeOne = User::factory()->judge()->create();
    $otherJudgeTwo = User::factory()->judge()->create();
    $otherJudgeThree = User::factory()->judge()->create();

    JudgeAssignment::query()->create([
        'match_id' => $matchA->id,
        'judge_id' => $judgeAssignedInRound->id,
        'assigned_mode' => JudgeAssignmentMode::Manual,
    ]);

    $this->actingAs($superadmin)
        ->postJson("/admin/matches/{$matchB->id}/assignments/manual", [
            'judge_ids' => [$judgeAssignedInRound->id, $otherJudgeOne->id, $otherJudgeTwo->id],
        ])
        ->assertUnprocessable();

    $this->actingAs($superadmin)
        ->postJson("/admin/matches/{$matchB->id}/assignments/manual", [
            'judge_ids' => [$otherJudgeOne->id, $otherJudgeTwo->id, $otherJudgeThree->id],
        ])
        ->assertOk();
});

test('random assignment excludes judges already assigned in another room of same round', function () {
    $superadmin = User::factory()->superadmin()->create();

    $round = Round::factory()->create();
    $roomA = Room::factory()->create();
    $roomB = Room::factory()->create();

    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $teamC = Team::factory()->create();
    $teamD = Team::factory()->create();

    $matchA = DebateMatch::factory()->create([
        'round_id' => $round->id,
        'room_id' => $roomA->id,
        'government_team_id' => $teamA->id,
        'opposition_team_id' => $teamB->id,
        'judge_panel_size' => 3,
    ]);

    $matchB = DebateMatch::factory()->create([
        'round_id' => $round->id,
        'room_id' => $roomB->id,
        'government_team_id' => $teamC->id,
        'opposition_team_id' => $teamD->id,
        'judge_panel_size' => 3,
    ]);

    $blockedJudge = User::factory()->judge()->create();
    $eligibleOne = User::factory()->judge()->create();
    $eligibleTwo = User::factory()->judge()->create();
    $eligibleThree = User::factory()->judge()->create();

    JudgeAssignment::query()->create([
        'match_id' => $matchA->id,
        'judge_id' => $blockedJudge->id,
        'assigned_mode' => JudgeAssignmentMode::Manual,
    ]);

    $this->actingAs($superadmin)
        ->postJson("/admin/matches/{$matchB->id}/assignments/randomize", [
            'eligible_judge_ids' => [$blockedJudge->id, $eligibleOne->id, $eligibleTwo->id, $eligibleThree->id],
        ])
        ->assertOk();

    $assignedJudgeIds = JudgeAssignment::query()
        ->where('match_id', $matchB->id)
        ->pluck('judge_id')
        ->all();

    expect($assignedJudgeIds)->not()->toContain($blockedJudge->id);
});
