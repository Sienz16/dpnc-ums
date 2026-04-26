<?php

use App\Domain\Debate\Enums\ScoreSheetState;
use App\Domain\Debate\Enums\SpeakerPosition;
use App\Domain\Debate\Enums\TeamSide;
use App\Domain\Debate\Services\MatchResultCalculator;
use App\Domain\Debate\Services\RankingService;
use App\Models\DebateMatch;
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

test('team ranking breaks ties by judge count then official margin then score', function () {
    $teamA = Team::factory()->create(['name' => 'Alpha']);
    $teamB = Team::factory()->create(['name' => 'Beta']);
    $teamC = Team::factory()->create(['name' => 'Gamma']);

    $match1 = DebateMatch::factory()->create([
        'government_team_id' => $teamA->id,
        'opposition_team_id' => $teamB->id,
    ]);

    $match2 = DebateMatch::factory()->create([
        'government_team_id' => $teamA->id,
        'opposition_team_id' => $teamC->id,
    ]);

    $match3 = DebateMatch::factory()->create([
        'government_team_id' => $teamC->id,
        'opposition_team_id' => $teamB->id,
    ]);

    MatchResult::factory()->create([
        'match_id' => $match1->id,
        'winner_side' => TeamSide::Government,
        'winner_vote_count' => 2,
        'loser_vote_count' => 1,
        'official_margin' => 6,
        'official_team_score_government' => 290,
        'official_team_score_opposition' => 284,
    ]);

    MatchResult::factory()->create([
        'match_id' => $match2->id,
        'winner_side' => TeamSide::Government,
        'winner_vote_count' => 1,
        'loser_vote_count' => 0,
        'official_margin' => 4,
        'official_team_score_government' => 282,
        'official_team_score_opposition' => 278,
    ]);

    MatchResult::factory()->create([
        'match_id' => $match3->id,
        'winner_side' => TeamSide::Government,
        'winner_vote_count' => 3,
        'loser_vote_count' => 0,
        'official_margin' => 5,
        'official_team_score_government' => 276,
        'official_team_score_opposition' => 271,
    ]);

    $rankings = app(RankingService::class)->teamRankings();

    expect($rankings->pluck('team_name')->take(3)->all())->toBe(['Alpha', 'Gamma', 'Beta']);
    expect($rankings[0]['judge_count'])->toBe(3);
    expect((float) $rankings[0]['average_margin'])->toBe(5.0);
    expect((float) $rankings[1]['average_margin'])->toBe(0.5);
});

test('team ranking can prioritize the selected factor sequence', function () {
    $teamA = Team::factory()->create(['name' => 'Alpha']);
    $teamB = Team::factory()->create(['name' => 'Beta']);
    $teamC = Team::factory()->create(['name' => 'Gamma']);
    $teamD = Team::factory()->create(['name' => 'Delta']);
    $teamE = Team::factory()->create(['name' => 'Epsilon']);
    $teamF = Team::factory()->create(['name' => 'Zeta']);

    $match1 = DebateMatch::factory()->create([
        'government_team_id' => $teamA->id,
        'opposition_team_id' => $teamC->id,
    ]);

    $match2 = DebateMatch::factory()->create([
        'government_team_id' => $teamB->id,
        'opposition_team_id' => $teamD->id,
    ]);

    $match3 = DebateMatch::factory()->create([
        'government_team_id' => $teamE->id,
        'opposition_team_id' => $teamF->id,
    ]);

    MatchResult::factory()->create([
        'match_id' => $match1->id,
        'winner_side' => TeamSide::Government,
        'winner_vote_count' => 1,
        'loser_vote_count' => 0,
        'official_margin' => 8,
        'official_team_score_government' => 280,
        'official_team_score_opposition' => 272,
    ]);

    MatchResult::factory()->create([
        'match_id' => $match2->id,
        'winner_side' => TeamSide::Government,
        'winner_vote_count' => 3,
        'loser_vote_count' => 0,
        'official_margin' => 2,
        'official_team_score_government' => 300,
        'official_team_score_opposition' => 298,
    ]);

    MatchResult::factory()->create([
        'match_id' => $match3->id,
        'winner_side' => TeamSide::Government,
        'winner_vote_count' => 1,
        'loser_vote_count' => 0,
        'official_margin' => 1,
        'official_team_score_government' => 320,
        'official_team_score_opposition' => 319,
    ]);

    $rankingService = app(RankingService::class);

    expect($rankingService->teamRankings()->first()['team_name'])->toBe('Beta');
    expect($rankingService->teamRankings(['margin', 'win', 'marks', 'judge'])->first()['team_name'])->toBe('Alpha');
    expect($rankingService->teamRankings(['marks', 'margin', 'win', 'judge'])->first()['team_name'])->toBe('Epsilon');
    expect($rankingService->teamRankings(['judge', 'margin', 'marks', 'win'])->first()['team_name'])->toBe('Beta');
});

test('team ranking can be scoped to selected rounds and uses total margin', function () {
    $roundOne = Round::factory()->create(['sequence' => 1]);
    $roundTwo = Round::factory()->create(['sequence' => 2]);
    $teamA = Team::factory()->create(['name' => 'Alpha']);
    $teamB = Team::factory()->create(['name' => 'Beta']);

    $match1 = DebateMatch::factory()->create([
        'round_id' => $roundOne->id,
        'government_team_id' => $teamA->id,
        'opposition_team_id' => $teamB->id,
    ]);

    $match2 = DebateMatch::factory()->create([
        'round_id' => $roundTwo->id,
        'government_team_id' => $teamB->id,
        'opposition_team_id' => $teamA->id,
    ]);

    MatchResult::factory()->create([
        'match_id' => $match1->id,
        'winner_side' => TeamSide::Government,
        'winner_vote_count' => 3,
        'loser_vote_count' => 0,
        'official_margin' => 8,
        'official_team_score_government' => 300,
        'official_team_score_opposition' => 292,
    ]);

    MatchResult::factory()->create([
        'match_id' => $match2->id,
        'winner_side' => TeamSide::Government,
        'winner_vote_count' => 3,
        'loser_vote_count' => 0,
        'official_margin' => 2,
        'official_team_score_government' => 288,
        'official_team_score_opposition' => 286,
    ]);

    $allRoundsRankings = app(RankingService::class)->teamRankings();
    $roundOneRankings = app(RankingService::class)->teamRankings(roundIds: [$roundOne->id]);

    expect((float) $allRoundsRankings->firstWhere('team_name', 'Alpha')['total_margin'])->toBe(6.0);
    expect((float) $roundOneRankings->firstWhere('team_name', 'Alpha')['total_margin'])->toBe(8.0);
    expect((float) $roundOneRankings->firstWhere('team_name', 'Beta')['total_margin'])->toBe(-8.0);
});

test('speaker ranking uses match specific lineup overrides', function () {
    $governmentTeam = Team::factory()->create(['name' => 'Gov']);
    $oppositionTeam = Team::factory()->create(['name' => 'Opp']);

    $govSpeakerOne = TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerOne, 'full_name' => 'Gov One']);
    TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerTwo]);
    TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerThree]);
    $govReserve = TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerFour, 'full_name' => 'Gov Reserve']);

    TeamMember::factory()->create(['team_id' => $oppositionTeam->id, 'speaker_position' => SpeakerPosition::SpeakerOne]);
    TeamMember::factory()->create(['team_id' => $oppositionTeam->id, 'speaker_position' => SpeakerPosition::SpeakerTwo]);
    TeamMember::factory()->create(['team_id' => $oppositionTeam->id, 'speaker_position' => SpeakerPosition::SpeakerThree]);

    $match = DebateMatch::factory()->create([
        'round_id' => Round::factory()->create()->id,
        'room_id' => Room::factory()->create()->id,
        'government_team_id' => $governmentTeam->id,
        'opposition_team_id' => $oppositionTeam->id,
        'judge_panel_size' => 1,
    ]);

    MatchSpeaker::query()->insert([
        [
            'match_id' => $match->id,
            'team_id' => $governmentTeam->id,
            'team_member_id' => $govReserve->id,
            'speaker_position' => SpeakerPosition::SpeakerOne->value,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'match_id' => $match->id,
            'team_id' => $governmentTeam->id,
            'team_member_id' => $govSpeakerOne->id,
            'speaker_position' => SpeakerPosition::SpeakerFour->value,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $judge = User::factory()->judge()->create();

    ScoreSheet::query()->create([
        'match_id' => $match->id,
        'judge_id' => $judge->id,
        'mark_pm' => 83,
        'mark_tpm' => 74,
        'mark_m1' => 73,
        'mark_kp' => 71,
        'mark_tkp' => 70,
        'mark_p1' => 69,
        'mark_penggulungan_gov' => 36,
        'mark_penggulungan_opp' => 34,
        'gov_total' => 266,
        'opp_total' => 244,
        'margin' => 3,
        'winner_side' => TeamSide::Government,
        'best_debater_member_id' => $govReserve->id,
        'state' => ScoreSheetState::Submitted,
        'submitted_at' => now(),
    ]);

    $rankings = app(RankingService::class)->speakerRankings();

    $reserveRow = $rankings->firstWhere('speaker_id', $govReserve->id);
    $speakerOneRow = $rankings->firstWhere('speaker_id', $govSpeakerOne->id);

    expect($reserveRow)->not->toBeNull();
    expect($reserveRow['average_official_points_per_appearance'])->toBe(83.0);
    expect($speakerOneRow)->toBeNull();
});

test('speaker ranking can be scoped to selected rounds', function () {
    $roundOne = Round::factory()->create(['sequence' => 1]);
    $roundTwo = Round::factory()->create(['sequence' => 2]);
    $governmentTeam = Team::factory()->create(['name' => 'Gov']);
    $oppositionTeam = Team::factory()->create(['name' => 'Opp']);

    $govPm = TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerOne, 'full_name' => 'Gov PM']);
    TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerTwo]);
    TeamMember::factory()->create(['team_id' => $governmentTeam->id, 'speaker_position' => SpeakerPosition::SpeakerThree]);

    TeamMember::factory()->create(['team_id' => $oppositionTeam->id, 'speaker_position' => SpeakerPosition::SpeakerOne]);
    TeamMember::factory()->create(['team_id' => $oppositionTeam->id, 'speaker_position' => SpeakerPosition::SpeakerTwo]);
    TeamMember::factory()->create(['team_id' => $oppositionTeam->id, 'speaker_position' => SpeakerPosition::SpeakerThree]);

    $match1 = DebateMatch::factory()->create([
        'round_id' => $roundOne->id,
        'government_team_id' => $governmentTeam->id,
        'opposition_team_id' => $oppositionTeam->id,
    ]);

    $match2 = DebateMatch::factory()->create([
        'round_id' => $roundTwo->id,
        'government_team_id' => $governmentTeam->id,
        'opposition_team_id' => $oppositionTeam->id,
    ]);

    ScoreSheet::factory()->create([
        'match_id' => $match1->id,
        'mark_pm' => 80,
        'state' => ScoreSheetState::Submitted,
        'submitted_at' => now(),
    ]);

    ScoreSheet::factory()->create([
        'match_id' => $match2->id,
        'mark_pm' => 60,
        'state' => ScoreSheetState::Submitted,
        'submitted_at' => now(),
    ]);

    MatchResult::factory()->create([
        'match_id' => $match1->id,
        'best_speaker_member_id' => $govPm->id,
    ]);

    MatchResult::factory()->create([
        'match_id' => $match2->id,
        'best_speaker_member_id' => $govPm->id,
    ]);

    $allRoundsRow = app(RankingService::class)->speakerRankings()->firstWhere('speaker_id', $govPm->id);
    $roundOneRow = app(RankingService::class)->speakerRankings(roundIds: [$roundOne->id])->firstWhere('speaker_id', $govPm->id);

    expect($allRoundsRow['appearances'])->toBe(2);
    expect((float) $allRoundsRow['average_official_points_per_appearance'])->toBe(70.0);
    expect($allRoundsRow['best_speaker_wins_count'])->toBe(2);
    expect($roundOneRow['appearances'])->toBe(1);
    expect((float) $roundOneRow['average_official_points_per_appearance'])->toBe(80.0);
    expect($roundOneRow['best_speaker_wins_count'])->toBe(1);
});
