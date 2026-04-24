<?php

use App\Domain\Debate\Enums\MatchStatus;
use App\Domain\Debate\Enums\SpeakerPosition;
use App\Models\DebateMatch;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeTeamWithStartedMatchFixture(): array
{
    $superadmin = User::factory()->superadmin()->create();
    $team = Team::factory()->create();
    $opponent = Team::factory()->create();

    $member = TeamMember::factory()->create([
        'team_id' => $team->id,
        'speaker_position' => SpeakerPosition::SpeakerOne,
    ]);

    TeamMember::factory()->create([
        'team_id' => $team->id,
        'speaker_position' => SpeakerPosition::SpeakerTwo,
    ]);

    TeamMember::factory()->create([
        'team_id' => $team->id,
        'speaker_position' => SpeakerPosition::SpeakerThree,
    ]);

    TeamMember::factory()->create([
        'team_id' => $opponent->id,
        'speaker_position' => SpeakerPosition::SpeakerOne,
    ]);

    TeamMember::factory()->create([
        'team_id' => $opponent->id,
        'speaker_position' => SpeakerPosition::SpeakerTwo,
    ]);

    TeamMember::factory()->create([
        'team_id' => $opponent->id,
        'speaker_position' => SpeakerPosition::SpeakerThree,
    ]);

    DebateMatch::factory()->create([
        'government_team_id' => $team->id,
        'opposition_team_id' => $opponent->id,
        'status' => MatchStatus::InProgress,
    ]);

    return [$superadmin, $team, $member];
}

test('superadmin cannot change speaker position after team has started a match', function () {
    [$superadmin, $team, $member] = makeTeamWithStartedMatchFixture();

    $this->actingAs($superadmin)
        ->patchJson("/admin/teams/{$team->id}/members/{$member->id}", [
            'speaker_position' => SpeakerPosition::SpeakerFour->value,
        ])
        ->assertUnprocessable()
        ->assertInvalid(['speaker_position']);

    expect($member->fresh()->speaker_position)->toBe(SpeakerPosition::SpeakerOne);
});

test('superadmin can still correct member name after team has started a match', function () {
    [$superadmin, $team, $member] = makeTeamWithStartedMatchFixture();

    $this->actingAs($superadmin)
        ->patchJson("/admin/teams/{$team->id}/members/{$member->id}", [
            'full_name' => 'Nama Dibetulkan',
        ])
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Nama Dibetulkan');

    expect($member->fresh()->full_name)->toBe('Nama Dibetulkan');
});

test('superadmin cannot delete team member after team has started a match', function () {
    [$superadmin, $team, $member] = makeTeamWithStartedMatchFixture();

    $this->actingAs($superadmin)
        ->deleteJson("/admin/teams/{$team->id}/members/{$member->id}")
        ->assertUnprocessable();

    $this->assertDatabaseHas('team_members', [
        'id' => $member->id,
    ]);
});

test('team detail endpoint marks roster as locked after the team has started a match', function () {
    [$superadmin, $team] = makeTeamWithStartedMatchFixture();

    $this->actingAs($superadmin)
        ->getJson("/admin/teams/{$team->id}")
        ->assertOk()
        ->assertJsonPath('data.roster_locked', true);
});
