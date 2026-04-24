<?php

namespace App\Http\Requests\Admin;

use App\Domain\Debate\Enums\JudgePanelSize;
use App\Domain\Debate\Enums\SpeakerPosition;
use App\Models\DebateMatch;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'round_id' => ['required', 'integer', 'exists:rounds,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'government_team_id' => ['required', 'integer', 'exists:teams,id', 'different:opposition_team_id'],
            'opposition_team_id' => ['required', 'integer', 'exists:teams,id', 'different:government_team_id'],
            'judge_panel_size' => ['required', 'integer', Rule::in(JudgePanelSize::values())],
            'scheduled_at' => ['nullable', 'date'],
            'government.speaker_1' => ['nullable', 'integer'],
            'government.speaker_2' => ['nullable', 'integer', 'different:government.speaker_1'],
            'government.speaker_3' => ['nullable', 'integer', 'different:government.speaker_1', 'different:government.speaker_2'],
            'government.speaker_4' => ['nullable', 'integer', 'different:government.speaker_1', 'different:government.speaker_2', 'different:government.speaker_3'],
            'opposition.speaker_1' => ['nullable', 'integer'],
            'opposition.speaker_2' => ['nullable', 'integer', 'different:opposition.speaker_1'],
            'opposition.speaker_3' => ['nullable', 'integer', 'different:opposition.speaker_1', 'different:opposition.speaker_2'],
            'opposition.speaker_4' => ['nullable', 'integer', 'different:opposition.speaker_1', 'different:opposition.speaker_2', 'different:opposition.speaker_3'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $roundId = (int) $this->input('round_id');
            $roomId = (int) $this->input('room_id');
            $governmentTeamId = (int) $this->input('government_team_id');
            $oppositionTeamId = (int) $this->input('opposition_team_id');

            $roomAlreadyAssigned = DebateMatch::query()
                ->where('round_id', $roundId)
                ->where('room_id', $roomId)
                ->exists();

            if ($roomAlreadyAssigned) {
                $validator->errors()->add('room_id', 'Bilik yang dipilih sudah digunakan dalam pusingan ini.');
            }

            $teamAlreadyAssigned = DebateMatch::query()
                ->where('round_id', $roundId)
                ->where(function ($query) use ($governmentTeamId, $oppositionTeamId): void {
                    $query->whereIn('government_team_id', [$governmentTeamId, $oppositionTeamId])
                        ->orWhereIn('opposition_team_id', [$governmentTeamId, $oppositionTeamId]);
                })
                ->exists();

            if ($teamAlreadyAssigned) {
                $message = 'Pasukan yang dipilih sudah dipadankan dalam pusingan ini.';
                $validator->errors()->add('government_team_id', $message);
                $validator->errors()->add('opposition_team_id', $message);
            }

            $this->validateOptionalLineup($validator, 'government', $governmentTeamId);
            $this->validateOptionalLineup($validator, 'opposition', $oppositionTeamId);
        });
    }

    protected function validateOptionalLineup(Validator $validator, string $side, int $teamId): void
    {
        $lineup = collect($this->input($side, []));

        if ($lineup->filter(fn (mixed $value): bool => $value !== null)->isEmpty()) {
            return;
        }

        foreach (['speaker_1', 'speaker_2', 'speaker_3'] as $requiredPosition) {
            if ($lineup->get($requiredPosition) === null) {
                $validator->errors()->add("{$side}.{$requiredPosition}", 'Lengkapkan tiga pendebat utama untuk lineup perlawanan.');
            }
        }

        $team = Team::query()->with('members')->find($teamId);

        if (! $team) {
            return;
        }

        $allowedMemberIds = $team->members->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        foreach (SpeakerPosition::ordered() as $position) {
            $memberId = $lineup->get($position->value);

            if ($memberId === null) {
                continue;
            }

            if (! in_array((int) $memberId, $allowedMemberIds, true)) {
                $validator->errors()->add("{$side}.{$position->value}", 'Ahli yang dipilih mesti datang daripada pasukan yang betul.');
            }
        }
    }
}
