<?php

use App\Domain\Debate\Enums\MatchStatus;
use App\Models\DebateMatch;
use App\Models\Room;
use App\Models\Round;
use App\Models\Team;
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
