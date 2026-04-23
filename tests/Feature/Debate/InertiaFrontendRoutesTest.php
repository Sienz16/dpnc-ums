<?php

use App\Models\DebateMatch;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('superadmin team show route renders the inertia page with the team id prop', function () {
    $superadmin = User::factory()->superadmin()->create();
    $team = Team::factory()->create();

    $this->actingAs($superadmin)
        ->get(route('debate.admin.teams.show', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('debate/admin/Teams/Show')
            ->where('teamId', $team->id),
        );
});

test('superadmin match show route renders the inertia page with the match id prop', function () {
    $superadmin = User::factory()->superadmin()->create();
    $match = DebateMatch::factory()->create();

    $this->actingAs($superadmin)
        ->get(route('debate.admin.matches.show', $match))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('debate/admin/Matches/Show')
            ->where('matchId', $match->id),
        );
});

test('superadmin report show route renders the inertia page with the match id prop', function () {
    $superadmin = User::factory()->superadmin()->create();
    $match = DebateMatch::factory()->create();

    $this->actingAs($superadmin)
        ->get(route('debate.admin.reports.matches.show', $match))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('debate/admin/Reports/Match')
            ->where('matchId', $match->id),
        );
});

test('judge match show route renders the inertia page with the match id prop', function () {
    $judge = User::factory()->judge()->create();
    $match = DebateMatch::factory()->create();

    $this->actingAs($judge)
        ->get(route('debate.judge.matches.show', $match))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('debate/judge/Matches/Show')
            ->where('matchId', $match->id),
        );
});
