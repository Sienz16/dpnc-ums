<?php

namespace App\Http\Requests\Admin;

use App\Domain\Debate\Enums\JudgePanelSize;
use App\Models\DebateMatch;
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
        });
    }
}
