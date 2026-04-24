<?php

use App\Http\Controllers\Admin\AdminScoreSheetController;
use App\Http\Controllers\Admin\JudgeController;
use App\Http\Controllers\Admin\MatchAssignmentController;
use App\Http\Controllers\Admin\MatchController;
use App\Http\Controllers\Admin\MatchLifecycleController;
use App\Http\Controllers\Admin\MatchLineupController;
use App\Http\Controllers\Admin\RankingController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoundController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TeamMemberController;
use App\Http\Controllers\Judge\JudgeCheckInController;
use App\Http\Controllers\Judge\JudgeMatchController;
use App\Http\Controllers\Judge\JudgeScoreSheetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:superadmin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('judges', [JudgeController::class, 'index'])->name('judges.index');
    Route::post('judges', [JudgeController::class, 'store'])->name('judges.store');
    Route::patch('judges/{judge}', [JudgeController::class, 'update'])->name('judges.update');

    Route::apiResource('rounds', RoundController::class)->except(['show']);
    Route::apiResource('rooms', RoomController::class)->except(['show']);
    Route::apiResource('teams', TeamController::class);

    Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])->name('teams.members.store');
    Route::patch('teams/{team}/members/{member}', [TeamMemberController::class, 'update'])->name('teams.members.update');
    Route::delete('teams/{team}/members/{member}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');

    Route::get('matches', [MatchController::class, 'index'])->name('matches.index');
    Route::post('matches', [MatchController::class, 'store'])->name('matches.store');
    Route::get('matches/{match}', [MatchController::class, 'show'])->name('matches.show');
    Route::patch('matches/{match}', [MatchController::class, 'update'])->name('matches.update');
    Route::delete('matches/{match}', [MatchController::class, 'destroy'])->name('matches.destroy');

    Route::post('matches/{match}/assignments/manual', [MatchAssignmentController::class, 'manual'])->name('matches.assignments.manual');
    Route::post('matches/{match}/assignments/randomize', [MatchAssignmentController::class, 'randomize'])->name('matches.assignments.randomize');
    Route::delete('matches/{match}/assignments', [MatchAssignmentController::class, 'clear'])->name('matches.assignments.clear');

    Route::post('matches/{match}/force-complete', [MatchLifecycleController::class, 'forceComplete'])->name('matches.force-complete');
    Route::post('matches/{match}/reopen', [MatchLifecycleController::class, 'reopen'])->name('matches.reopen');
    Route::patch('matches/{match}/lineup', [MatchLineupController::class, 'update'])->name('matches.lineup.update');
    Route::patch('matches/{match}/score-sheets/{judge}', [AdminScoreSheetController::class, 'update'])->name('matches.score-sheets.update');

    Route::get('rankings/teams', [RankingController::class, 'teams'])->name('rankings.teams');
    Route::get('rankings/speakers', [RankingController::class, 'speakers'])->name('rankings.speakers');

    Route::get('reports/matches/{match}', [ReportController::class, 'match'])->name('reports.matches.show');
    Route::get('reports/tournament', [ReportController::class, 'tournament'])->name('reports.tournament');
});

Route::middleware(['auth', 'role:judge'])->prefix('judge')->name('judge.')->group(function (): void {
    Route::get('matches', [JudgeMatchController::class, 'index'])->name('matches.index');
    Route::get('matches/{match}', [JudgeMatchController::class, 'show'])->name('matches.show');
    Route::post('matches/{match}/check-in', [JudgeCheckInController::class, 'store'])->name('matches.check-in');
    Route::get('matches/{match}/score-sheet', [JudgeScoreSheetController::class, 'show'])->name('matches.score-sheet.show');
    Route::put('matches/{match}/score-sheet/draft', [JudgeScoreSheetController::class, 'saveDraft'])->name('matches.score-sheet.draft');
    Route::post('matches/{match}/score-sheet/submit', [JudgeScoreSheetController::class, 'submit'])->name('matches.score-sheet.submit');
});
