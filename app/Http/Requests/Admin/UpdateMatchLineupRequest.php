<?php

namespace App\Http\Requests\Admin;

use App\Domain\Debate\Enums\SpeakerPosition;
use App\Models\DebateMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMatchLineupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'government.speaker_1' => ['required', 'integer'],
            'government.speaker_2' => ['required', 'integer', 'different:government.speaker_1'],
            'government.speaker_3' => ['required', 'integer', 'different:government.speaker_1', 'different:government.speaker_2'],
            'government.speaker_4' => ['nullable', 'integer', 'different:government.speaker_1', 'different:government.speaker_2', 'different:government.speaker_3'],
            'opposition.speaker_1' => ['required', 'integer'],
            'opposition.speaker_2' => ['required', 'integer', 'different:opposition.speaker_1'],
            'opposition.speaker_3' => ['required', 'integer', 'different:opposition.speaker_1', 'different:opposition.speaker_2'],
            'opposition.speaker_4' => ['nullable', 'integer', 'different:opposition.speaker_1', 'different:opposition.speaker_2', 'different:opposition.speaker_3'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var DebateMatch $match */
                $match = $this->route('match');

                $this->validateTeamLineup($validator, 'government', $match->government_team_id);
                $this->validateTeamLineup($validator, 'opposition', $match->opposition_team_id);
            },
        ];
    }

    public function messages(): array
    {
        return [
            'government.speaker_1.required' => 'Pilih Pendebat 1 kerajaan.',
            'government.speaker_2.required' => 'Pilih Pendebat 2 kerajaan.',
            'government.speaker_3.required' => 'Pilih Pendebat 3 kerajaan.',
            'opposition.speaker_1.required' => 'Pilih Pendebat 1 pembangkang.',
            'opposition.speaker_2.required' => 'Pilih Pendebat 2 pembangkang.',
            'opposition.speaker_3.required' => 'Pilih Pendebat 3 pembangkang.',
        ];
    }

    protected function validateTeamLineup(Validator $validator, string $side, int $teamId): void
    {
        $allowedMemberIds = $this->route('match')
            ->loadMissing(['governmentTeam.members', 'oppositionTeam.members'])
            ->{$side === 'government' ? 'governmentTeam' : 'oppositionTeam'}
            ->members
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        foreach (SpeakerPosition::ordered() as $position) {
            $memberId = $this->input("{$side}.{$position->value}");

            if ($memberId === null) {
                continue;
            }

            if (! in_array((int) $memberId, $allowedMemberIds, true)) {
                $validator->errors()->add(
                    "{$side}.{$position->value}",
                    'Ahli yang dipilih mesti datang daripada pasukan yang betul.',
                );
            }
        }
    }
}
