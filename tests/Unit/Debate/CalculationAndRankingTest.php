<?php

use App\Domain\Debate\Enums\ScoreSheetState;
use App\Domain\Debate\Enums\SpeakerPosition;
use App\Domain\Debate\Enums\TeamSide;
use App\Domain\Debate\Services\MatchResultCalculator;
use App\Domain\Debate\Services\RankingService;
use App\Models\DebateMatch;
use App\Models\MatchResult;
use App\Models\Room;
use App\Models\Round;
use App\Models\ScoreSheet;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calculator picks best speaker by votes and tie breaks by average speaker mark', function () {
    $governmentTeam = Team::factory()->create();
    $oppositionTeam = Team::factory()->create();

    $govPm = TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerOne]);
    $govTpm = TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerTwo]);
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

    $judges = User::factory()->count(3)->judge()->create();

    ScoreSheet::query()->create([
        'match_id' => $match->id,
        'judge_id' => $judges[0]->id,
        'mark_pm' => 80,
        'mark_tpm' => 70,
        'mark_m1' => 70,
        'mark_kp' => 65,
        'mark_tkp' => 65,
        'mark_p1' => 65,
        'mark_penggulungan_gov' => 70,
        'mark_penggulungan_opp' => 65,
        'gov_total' => 290,
        'opp_total' => 260,
        'margin' => 30,
        'winner_side' => TeamSide::Government,
        'best_debater_member_id' => $govPm->id,
        'state' => ScoreSheetState::Submitted,
        'submitted_at' => now(),
    ]);

    ScoreSheet::query()->create([
        'match_id' => $match->id,
        'judge_id' => $judges[1]->id,
        'mark_pm' => 68,
        'mark_tpm' => 82,
        'mark_m1' => 70,
        'mark_kp' => 65,
        'mark_tkp' => 65,
        'mark_p1' => 65,
        'mark_penggulungan_gov' => 70,
        'mark_penggulungan_opp' => 65,
        'gov_total' => 290,
        'opp_total' => 260,
        'margin' => 30,
        'winner_side' => TeamSide::Government,
        'best_debater_member_id' => $govTpm->id,
        'state' => ScoreSheetState::Submitted,
        'submitted_at' => now(),
    ]);

    ScoreSheet::query()->create([
        'match_id' => $match->id,
        'judge_id' => $judges[2]->id,
        'mark_pm' => 79,
        'mark_tpm' => 75,
        'mark_m1' => 70,
        'mark_kp' => 65,
        'mark_tkp' => 65,
        'mark_p1' => 65,
        'mark_penggulungan_gov' => 70,
        'mark_penggulungan_opp' => 65,
        'gov_total' => 294,
        'opp_total' => 260,
        'margin' => 34,
        'winner_side' => TeamSide::Government,
        'best_debater_member_id' => $govTpm->id,
        'state' => ScoreSheetState::Submitted,
        'submitted_at' => now(),
    ]);

    $calculator = app(MatchResultCalculator::class);
    $result = $calculator->recalculate($match->fresh(), false, false);

    expect($result)->not->toBeNull();
    expect($result->winner_side)->toBe(TeamSide::Government);
    expect($result->winner_vote_count)->toBe(3);
    expect($result->loser_vote_count)->toBe(0);
    expect((float) $result->official_margin)->toBe(31.3);
    expect($result->best_speaker_member_id)->toBe($govTpm->id);
});

test('calculator resolves tied best speaker votes without crashing', function () {
    $governmentTeam = Team::factory()->create();
    $oppositionTeam = Team::factory()->create();

    $govPm = TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerOne]);
    $govTpm = TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerTwo]);
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

    $judges = User::factory()->count(3)->judge()->create();

    ScoreSheet::query()->create([
        'match_id' => $match->id,
        'judge_id' => $judges[0]->id,
        'mark_pm' => 82,
        'mark_tpm' => 70,
        'mark_m1' => 70,
        'mark_kp' => 65,
        'mark_tkp' => 65,
        'mark_p1' => 65,
        'mark_penggulungan_gov' => 70,
        'mark_penggulungan_opp' => 65,
        'gov_total' => 292,
        'opp_total' => 260,
        'margin' => 32,
        'winner_side' => TeamSide::Government,
        'best_debater_member_id' => $govPm->id,
        'state' => ScoreSheetState::Submitted,
        'submitted_at' => now(),
    ]);

    ScoreSheet::query()->create([
        'match_id' => $match->id,
        'judge_id' => $judges[1]->id,
        'mark_pm' => 68,
        'mark_tpm' => 84,
        'mark_m1' => 70,
        'mark_kp' => 65,
        'mark_tkp' => 65,
        'mark_p1' => 65,
        'mark_penggulungan_gov' => 70,
        'mark_penggulungan_opp' => 65,
        'gov_total' => 292,
        'opp_total' => 260,
        'margin' => 32,
        'winner_side' => TeamSide::Government,
        'best_debater_member_id' => $govTpm->id,
        'state' => ScoreSheetState::Submitted,
        'submitted_at' => now(),
    ]);

    ScoreSheet::query()->create([
        'match_id' => $match->id,
        'judge_id' => $judges[2]->id,
        'mark_pm' => 81,
        'mark_tpm' => 74,
        'mark_m1' => 70,
        'mark_kp' => 65,
        'mark_tkp' => 65,
        'mark_p1' => 65,
        'mark_penggulungan_gov' => 70,
        'mark_penggulungan_opp' => 65,
        'gov_total' => 295,
        'opp_total' => 260,
        'margin' => 35,
        'winner_side' => TeamSide::Government,
        'best_debater_member_id' => $govPm->id,
        'state' => ScoreSheetState::Submitted,
        'submitted_at' => now(),
    ]);

    $result = app(MatchResultCalculator::class)->recalculate($match->fresh(), false, false);

    expect($result)->not->toBeNull();
    expect($result->best_speaker_member_id)->toBe($govPm->id);
});

test('team ranking sorts by win count then judge count then margin then score', function () {
    $teamA = Team::factory()->create(['name' => 'Alpha']);
    $teamB = Team::factory()->create(['name' => 'Beta']);
    $teamC = Team::factory()->create(['name' => 'Gamma']);

    $match1 = DebateMatch::factory()->create([
        'government_team_id' => $teamA->id,
        'opposition_team_id' => $teamB->id,
    ]);

    $match2 = DebateMatch::factory()->create([
        'government_team_id' => $teamC->id,
        'opposition_team_id' => $teamA->id,
    ]);

    MatchResult::factory()->create([
        'match_id' => $match1->id,
        'winner_side' => TeamSide::Government,
        'winner_vote_count' => 2,
        'loser_vote_count' => 1,
        'official_margin' => 5,
        'official_team_score_government' => 300,
        'official_team_score_opposition' => 295,
    ]);

    MatchResult::factory()->create([
        'match_id' => $match2->id,
        'winner_side' => TeamSide::Opposition,
        'winner_vote_count' => 3,
        'loser_vote_count' => 0,
        'official_margin' => 7,
        'official_team_score_government' => 292,
        'official_team_score_opposition' => 299,
    ]);

    $rankings = app(RankingService::class)->teamRankings();

    expect($rankings[0]['team_name'])->toBe('Alpha');
    expect($rankings[0]['win_count'])->toBe(2);
    expect($rankings[0]['judge_count'])->toBe(5);
});
