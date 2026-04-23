<?php

use App\Models\DebateMatch;
use App\Models\Team;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'role:superadmin'])->prefix('debate/admin')->name('debate.admin.')->group(function (): void {
    Route::inertia('judges', 'debate/admin/Judges/Index')->name('judges.index');
    Route::inertia('rounds', 'debate/admin/Rounds/Index')->name('rounds.index');
    Route::inertia('rooms', 'debate/admin/Rooms/Index')->name('rooms.index');
    Route::inertia('teams', 'debate/admin/Teams/Index')->name('teams.index');
    Route::get('teams/{team}', fn (Team $team) => Inertia::render('debate/admin/Teams/Show', [
        'teamId' => $team->id,
    ]))->name('teams.show');
    Route::inertia('matches', 'debate/admin/Matches/Index')->name('matches.index');
    Route::get('matches/{match}', fn (DebateMatch $match) => Inertia::render('debate/admin/Matches/Show', [
        'matchId' => $match->id,
    ]))->name('matches.show');
    Route::inertia('rankings/teams', 'debate/admin/Rankings/Teams')->name('rankings.teams');
    Route::inertia('rankings/speakers', 'debate/admin/Rankings/Speakers')->name('rankings.speakers');
    Route::inertia('reports/tournament', 'debate/admin/Reports/Tournament')->name('reports.tournament');
    Route::get('reports/matches/{match}', fn (DebateMatch $match) => Inertia::render('debate/admin/Reports/Match', [
        'matchId' => $match->id,
    ]))->name('reports.matches.show');
});

Route::middleware(['auth', 'role:judge'])->prefix('debate/judge')->name('debate.judge.')->group(function (): void {
    Route::inertia('matches', 'debate/judge/Matches/Index')->name('matches.index');
    Route::get('matches/{match}', fn (DebateMatch $match) => Inertia::render('debate/judge/Matches/Show', [
        'matchId' => $match->id,
    ]))->name('matches.show');
});
