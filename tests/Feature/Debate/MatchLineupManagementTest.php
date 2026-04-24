<?php

use App\Domain\Debate\Enums\JudgeAssignmentMode;
use App\Domain\Debate\Enums\SpeakerPosition;
use App\Models\DebateMatch;
use App\Models\JudgeAssignment;
use App\Models\MatchSpeaker;
use App\Models\Room;
use App\Models\Round;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeMatchLineupFixture(): array
{
    $superadmin = User::factory()->superadmin()->create();
    $judge = User::factory()->judge()->create();

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

    $match = DebateMatch::factory()->create([
        'round_id' => Round::factory()->create()->id,
        'room_id' => Room::factory()->create()->id,
        'government_team_id' => $governmentTeam->id,
        'opposition_team_id' => $oppositionTeam->id,
        'judge_panel_size' => 1,
    ]);

    JudgeAssignment::query()->create([
        'match_id' => $match->id,
        'judge_id' => $judge->id,
        'assigned_mode' => JudgeAssignmentMode::Manual,
    ]);

    return [$superadmin, $judge, $match, $govMembers, $oppMembers];
}

test('superadmin can set match specific lineup before any score sheet exists', function () {
    [$superadmin, $judge, $match, $govMembers, $oppMembers] = makeMatchLineupFixture();

    $reserveGovernmentSpeaker = $govMembers[3];

    $this->actingAs($superadmin)
        ->patchJson("/admin/matches/{$match->id}/lineup", [
            'government' => [
                'speaker_1' => $reserveGovernmentSpeaker->id,
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
        ])
        ->assertOk()
        ->assertJsonPath('data.government_lineup.0.id', $reserveGovernmentSpeaker->id)
        ->assertJsonPath('data.government_lineup.0.speaker_position', SpeakerPosition::SpeakerOne->value);

    $this->actingAs($judge)->postJson("/judge/matches/{$match->id}/check-in")->assertOk();

    $this->actingAs($judge)
        ->postJson("/judge/matches/{$match->id}/score-sheet/submit", [
            'mark_pm' => 82,
            'mark_tpm' => 78,
            'mark_m1' => 77,
            'mark_kp' => 75,
            'mark_tkp' => 74,
            'mark_p1' => 73,
            'mark_penggulungan_gov' => 36,
            'mark_penggulungan_opp' => 34,
            'margin' => 3,
            'best_debater_member_id' => $reserveGovernmentSpeaker->id,
        ])
        ->assertOk();
});

test('superadmin cannot change lineup after score sheets already exist', function () {
    [$superadmin, $judge, $match, $govMembers, $oppMembers] = makeMatchLineupFixture();

    $this->actingAs($judge)->postJson("/judge/matches/{$match->id}/check-in")->assertOk();

    $this->actingAs($judge)
        ->putJson("/judge/matches/{$match->id}/score-sheet/draft", [
            'mark_pm' => 80,
            'mark_tpm' => 78,
            'mark_m1' => 77,
            'mark_kp' => 75,
            'mark_tkp' => 74,
            'mark_p1' => 73,
            'mark_penggulungan_gov' => 35,
            'mark_penggulungan_opp' => 34,
            'margin' => 2,
            'best_debater_member_id' => $govMembers[0]->id,
        ])
        ->assertOk();

    $this->actingAs($superadmin)
        ->patchJson("/admin/matches/{$match->id}/lineup", [
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
        ])
        ->assertUnprocessable();
});

test('judge match detail shows the match specific lineup instead of the base roster order', function () {
    [$superadmin, $judge, $match, $govMembers, $oppMembers] = makeMatchLineupFixture();

    MatchSpeaker::query()->create([
        'match_id' => $match->id,
        'team_id' => $match->government_team_id,
        'team_member_id' => $govMembers[3]->id,
        'speaker_position' => SpeakerPosition::SpeakerOne,
    ]);

    MatchSpeaker::query()->create([
        'match_id' => $match->id,
        'team_id' => $match->government_team_id,
        'team_member_id' => $govMembers[1]->id,
        'speaker_position' => SpeakerPosition::SpeakerTwo,
    ]);

    MatchSpeaker::query()->create([
        'match_id' => $match->id,
        'team_id' => $match->government_team_id,
        'team_member_id' => $govMembers[2]->id,
        'speaker_position' => SpeakerPosition::SpeakerThree,
    ]);

    MatchSpeaker::query()->create([
        'match_id' => $match->id,
        'team_id' => $match->government_team_id,
        'team_member_id' => $govMembers[0]->id,
        'speaker_position' => SpeakerPosition::SpeakerFour,
    ]);

    foreach ([0, 1, 2, 3] as $index) {
        MatchSpeaker::query()->create([
            'match_id' => $match->id,
            'team_id' => $match->opposition_team_id,
            'team_member_id' => $oppMembers[$index]->id,
            'speaker_position' => SpeakerPosition::ordered()[$index],
        ]);
    }

    $this->actingAs($superadmin)
        ->getJson("/admin/matches/{$match->id}")
        ->assertOk()
        ->assertJsonPath('data.government_lineup.0.id', $govMembers[3]->id)
        ->assertJsonPath('data.government_lineup.0.speaker_position', SpeakerPosition::SpeakerOne->value);

    $this->actingAs($judge)
        ->getJson("/judge/matches/{$match->id}")
        ->assertOk()
        ->assertJsonPath('data.government_lineup.0.id', $govMembers[3]->id)
        ->assertJsonPath('data.government_lineup.3.id', $govMembers[0]->id);
});
