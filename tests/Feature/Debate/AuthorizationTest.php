<?php

use App\Domain\Debate\Enums\JudgeAssignmentMode;
use App\Domain\Debate\Enums\JudgePanelSize;
use App\Models\DebateMatch;
use App\Models\JudgeAssignment;
use App\Models\Room;
use App\Models\Round;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('judge cannot access superadmin routes', function () {
    $judge = User::factory()->judge()->create();

    $response = $this->actingAs($judge)->getJson('/admin/rounds');

    $response->assertForbidden();
});

test('superadmin cannot access judge-only routes', function () {
    $superadmin = User::factory()->superadmin()->create();

    $response = $this->actingAs($superadmin)->getJson('/judge/matches');

    $response->assertForbidden();
});

test('judge sees only assigned matches', function () {
    $judge = User::factory()->judge()->create();
    $otherJudge = User::factory()->judge()->create();

    $matchA = DebateMatch::factory()->create([
        'round_id' => Round::factory()->create()->id,
        'room_id' => Room::factory()->create()->id,
        'government_team_id' => Team::factory()->create()->id,
        'opposition_team_id' => Team::factory()->create()->id,
        'judge_panel_size' => JudgePanelSize::One,
    ]);

    $matchB = DebateMatch::factory()->create([
        'round_id' => Round::factory()->create()->id,
        'room_id' => Room::factory()->create()->id,
        'government_team_id' => Team::factory()->create()->id,
        'opposition_team_id' => Team::factory()->create()->id,
        'judge_panel_size' => JudgePanelSize::One,
    ]);

    JudgeAssignment::query()->create([
        'match_id' => $matchA->id,
        'judge_id' => $judge->id,
        'assigned_mode' => JudgeAssignmentMode::Manual,
    ]);

    JudgeAssignment::query()->create([
        'match_id' => $matchB->id,
        'judge_id' => $otherJudge->id,
        'assigned_mode' => JudgeAssignmentMode::Manual,
    ]);

    $response = $this->actingAs($judge)->getJson('/judge/matches');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $matchA->id);
});
