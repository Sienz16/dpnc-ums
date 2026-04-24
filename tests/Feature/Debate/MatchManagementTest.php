<?php

use App\Domain\Debate\Enums\MatchStatus;
use App\Domain\Debate\Enums\SpeakerPosition;
use App\Models\DebateMatch;
use App\Models\MatchSpeaker;
use App\Models\Room;
use App\Models\Round;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('superadmin can delete pending match', function () {
    $superadmin = User::factory()->superadmin()->create();

    $match = DebateMatch::factory()->create([
        'status' => MatchStatus::Pending,
    ]);

    $this->actingAs($superadmin)
        ->deleteJson("/admin/matches/{$match->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('matches', [
        'id' => $match->id,
    ]);
});

test('superadmin cannot delete non pending match', function () {
    $superadmin = User::factory()->superadmin()->create();

    $match = DebateMatch::factory()->create([
        'status' => MatchStatus::Completed,
    ]);

    $this->actingAs($superadmin)
        ->deleteJson("/admin/matches/{$match->id}")
        ->assertUnprocessable();
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
