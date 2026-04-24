<?php

use App\Domain\Debate\Enums\MatchStatus;
use App\Domain\Debate\Enums\SpeakerPosition;
use App\Models\DebateMatch;
use App\Models\JudgeAssignment;
use App\Models\MatchResult;
use App\Models\MatchSpeaker;
use App\Models\Room;
use App\Models\Round;
use App\Models\ScoreSheet;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('superadmin can delete match', function () {
    $superadmin = User::factory()->superadmin()->create();

    $match = DebateMatch::factory()->create([
        'status' => MatchStatus::Pending,
    ]);

    $this->actingAs($superadmin)
        ->deleteJson("/admin/matches/{$match->id}")
        ->assertNoContent();

    $this->assertModelMissing($match);
});

test('superadmin can delete completed match with judging records', function () {
    $superadmin = User::factory()->superadmin()->create();
    $judge = User::factory()->judge()->create();
    $governmentTeam = Team::factory()->create();
    $oppositionTeam = Team::factory()->create();
    $bestSpeaker = TeamMember::factory()->create([
        'team_id' => $governmentTeam->id,
        'speaker_position' => SpeakerPosition::SpeakerOne,
    ]);

    $match = DebateMatch::factory()->create([
        'status' => MatchStatus::Completed,
        'government_team_id' => $governmentTeam->id,
        'opposition_team_id' => $oppositionTeam->id,
    ]);

    $assignment = JudgeAssignment::factory()->create([
        'match_id' => $match->id,
        'judge_id' => $judge->id,
        'checked_in_at' => now(),
        'submitted_at' => now(),
    ]);
    $scoreSheet = ScoreSheet::factory()->create([
        'match_id' => $match->id,
        'judge_id' => $judge->id,
        'best_debater_member_id' => $bestSpeaker->id,
    ]);
    $result = MatchResult::factory()->create([
        'match_id' => $match->id,
        'best_speaker_member_id' => $bestSpeaker->id,
    ]);
    $matchSpeaker = MatchSpeaker::query()->create([
        'match_id' => $match->id,
        'team_id' => $governmentTeam->id,
        'team_member_id' => $bestSpeaker->id,
        'speaker_position' => SpeakerPosition::SpeakerOne,
    ]);

    $this->actingAs($superadmin)
        ->deleteJson("/admin/matches/{$match->id}")
        ->assertNoContent();

    $this->assertModelMissing($match);
    $this->assertModelMissing($assignment);
    $this->assertModelMissing($scoreSheet);
    $this->assertModelMissing($result);
    $this->assertModelMissing($matchSpeaker);
});

test('cannot create match with team already assigned in the same round', function () {
    $superadmin = User::factory()->superadmin()->create();

    $round = Round::factory()->create();
    $roomA = Room::factory()->create();
    $roomB = Room::factory()->create();

    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $teamC = Team::factory()->create();

    DebateMatch::factory()->create([
        'round_id' => $round->id,
        'room_id' => $roomA->id,
        'government_team_id' => $teamA->id,
        'opposition_team_id' => $teamB->id,
    ]);

    $this->actingAs($superadmin)
        ->postJson('/admin/matches', [
            'round_id' => $round->id,
            'room_id' => $roomB->id,
            'government_team_id' => $teamA->id,
            'opposition_team_id' => $teamC->id,
            'judge_panel_size' => 3,
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertInvalid([
            'government_team_id',
            'opposition_team_id',
        ]);
});

test('cannot create match with room already assigned in the same round', function () {
    $superadmin = User::factory()->superadmin()->create();

    $round = Round::factory()->create();
    $room = Room::factory()->create();
    $otherRoom = Room::factory()->create();

    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $teamC = Team::factory()->create();
    $teamD = Team::factory()->create();

    DebateMatch::factory()->create([
        'round_id' => $round->id,
        'room_id' => $room->id,
        'government_team_id' => $teamA->id,
        'opposition_team_id' => $teamB->id,
    ]);

    $this->actingAs($superadmin)
        ->postJson('/admin/matches', [
            'round_id' => $round->id,
            'room_id' => $room->id,
            'government_team_id' => $teamC->id,
            'opposition_team_id' => $teamD->id,
            'judge_panel_size' => 3,
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertInvalid(['room_id']);

    $this->actingAs($superadmin)
        ->postJson('/admin/matches', [
            'round_id' => $round->id,
            'room_id' => $otherRoom->id,
            'government_team_id' => $teamC->id,
            'opposition_team_id' => $teamD->id,
            'judge_panel_size' => 3,
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertCreated();
});

test('cannot update match with team already assigned in the same round', function () {
    $superadmin = User::factory()->superadmin()->create();

    $round = Round::factory()->create();
    $roomA = Room::factory()->create();
    $roomB = Room::factory()->create();

    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $teamC = Team::factory()->create();
    $teamD = Team::factory()->create();

    DebateMatch::factory()->create([
        'round_id' => $round->id,
        'room_id' => $roomA->id,
        'government_team_id' => $teamA->id,
        'opposition_team_id' => $teamB->id,
        'status' => MatchStatus::Pending,
    ]);

    $matchToUpdate = DebateMatch::factory()->create([
        'round_id' => $round->id,
        'room_id' => $roomB->id,
        'government_team_id' => $teamC->id,
        'opposition_team_id' => $teamD->id,
        'status' => MatchStatus::Pending,
    ]);

    $this->actingAs($superadmin)
        ->patchJson("/admin/matches/{$matchToUpdate->id}", [
            'government_team_id' => $teamA->id,
        ])
        ->assertInvalid([
            'government_team_id',
            'opposition_team_id',
        ]);
});

test('cannot update match with room already assigned in the same round', function () {
    $superadmin = User::factory()->superadmin()->create();

    $round = Round::factory()->create();
    $roomA = Room::factory()->create();
    $roomB = Room::factory()->create();

    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $teamC = Team::factory()->create();
    $teamD = Team::factory()->create();

    DebateMatch::factory()->create([
        'round_id' => $round->id,
        'room_id' => $roomA->id,
        'government_team_id' => $teamA->id,
        'opposition_team_id' => $teamB->id,
        'status' => MatchStatus::Pending,
    ]);

    $matchToUpdate = DebateMatch::factory()->create([
        'round_id' => $round->id,
        'room_id' => $roomB->id,
        'government_team_id' => $teamC->id,
        'opposition_team_id' => $teamD->id,
        'status' => MatchStatus::Pending,
    ]);

    $this->actingAs($superadmin)
        ->patchJson("/admin/matches/{$matchToUpdate->id}", [
            'room_id' => $roomA->id,
        ])
        ->assertInvalid(['room_id']);
});

test('superadmin can randomize first round matchups and reshuffle pending matches', function () {
    $superadmin = User::factory()->superadmin()->create();

    $round = Round::factory()->create(['sequence' => 1]);
    $rooms = Room::factory()->count(3)->create();
    $teams = Team::factory()->count(6)->create();

    $firstResponse = $this->actingAs($superadmin)
        ->postJson("/admin/rounds/{$round->id}/matches/randomize");

    $firstResponse->assertCreated()
        ->assertJsonPath('data.created_matches_count', 3)
        ->assertJsonPath('data.unpaired_team', null);

    $firstMatchIds = DebateMatch::query()
        ->where('round_id', $round->id)
        ->pluck('id')
        ->all();

    expect($firstMatchIds)->toHaveCount(3);

    $assignedTeamIds = DebateMatch::query()
        ->where('round_id', $round->id)
        ->get()
        ->flatMap(fn (DebateMatch $match): array => [
            $match->government_team_id,
            $match->opposition_team_id,
        ])
        ->sort()
        ->values()
        ->all();

    expect($assignedTeamIds)->toBe($teams->pluck('id')->sort()->values()->all());

    $secondResponse = $this->actingAs($superadmin)
        ->postJson("/admin/rounds/{$round->id}/matches/randomize");

    $secondResponse->assertCreated()
        ->assertJsonPath('data.created_matches_count', 3);

    DebateMatch::query()
        ->whereIn('id', $firstMatchIds)
        ->each(fn (DebateMatch $match) => $this->assertModelMissing($match));

    expect(DebateMatch::query()->where('round_id', $round->id)->count())->toBe(3);
    expect(DebateMatch::query()->where('round_id', $round->id)->pluck('room_id')->unique()->count())->toBe($rooms->count());
});

test('superadmin cannot reshuffle first round after a match has started', function () {
    $superadmin = User::factory()->superadmin()->create();

    $round = Round::factory()->create(['sequence' => 1]);
    $room = Room::factory()->create();
    $governmentTeam = Team::factory()->create();
    $oppositionTeam = Team::factory()->create();

    DebateMatch::factory()->create([
        'round_id' => $round->id,
        'room_id' => $room->id,
        'government_team_id' => $governmentTeam->id,
        'opposition_team_id' => $oppositionTeam->id,
        'status' => MatchStatus::InProgress,
    ]);

    $this->actingAs($superadmin)
        ->postJson("/admin/rounds/{$round->id}/matches/randomize")
        ->assertUnprocessable();
});

test('can create match with a match specific lineup override', function () {
    $superadmin = User::factory()->superadmin()->create();

    $round = Round::factory()->create();
    $room = Room::factory()->create();

    $governmentTeam = Team::factory()->create();
    $oppositionTeam = Team::factory()->create();

    $govMembers = collect([
        SpeakerPosition::SpeakerOne,
        SpeakerPosition::SpeakerTwo,
        SpeakerPosition::SpeakerThree,
        SpeakerPosition::SpeakerFour,
    ])->map(fn (SpeakerPosition $position) => TeamMember::factory()->create([
        'team_id' => $governmentTeam->id,
        'speaker_position' => $position,
    ]));

    $oppMembers = collect([
        SpeakerPosition::SpeakerOne,
        SpeakerPosition::SpeakerTwo,
        SpeakerPosition::SpeakerThree,
        SpeakerPosition::SpeakerFour,
    ])->map(fn (SpeakerPosition $position) => TeamMember::factory()->create([
        'team_id' => $oppositionTeam->id,
        'speaker_position' => $position,
    ]));

    $response = $this->actingAs($superadmin)
        ->postJson('/admin/matches', [
            'round_id' => $round->id,
            'room_id' => $room->id,
            'government_team_id' => $governmentTeam->id,
            'opposition_team_id' => $oppositionTeam->id,
            'judge_panel_size' => 3,
            'scheduled_at' => now()->addDay()->toDateTimeString(),
            'government' => [
                'speaker_1' => $govMembers[3]->id,
                'speaker_2' => $govMembers[1]->id,
                'speaker_3' => $govMembers[2]->id,
                'speaker_4' => $govMembers[0]->id,
            ],
            'opposition' => [
                'speaker_1' => $oppMembers[0]->id,
                'speaker_2' => $oppMembers[1]->id,
                'speaker_3' => $oppMembers[2]->id,
                'speaker_4' => $oppMembers[3]->id,
            ],
        ]);

    $response->assertCreated();

    $matchId = $response->json('data.id');

    $this->assertDatabaseHas('match_speakers', [
        'match_id' => $matchId,
        'team_id' => $governmentTeam->id,
        'team_member_id' => $govMembers[3]->id,
        'speaker_position' => SpeakerPosition::SpeakerOne->value,
    ]);

    expect(MatchSpeaker::query()->where('match_id', $matchId)->count())->toBe(8);
});
