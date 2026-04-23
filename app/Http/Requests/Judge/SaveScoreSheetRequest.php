<?php

namespace App\Http\Requests\Judge;

use App\Models\DebateMatch;
use Illuminate\Foundation\Http\FormRequest;

class SaveScoreSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DebateMatch $match */
        $match = $this->route('match');

        return $this->user()?->can('view', $match) ?? false;
    }

    public function rules(): array
    {
        return [
            'mark_pm' => ['required', 'numeric', 'min:0', 'max:100'],
            'mark_tpm' => ['required', 'numeric', 'min:0', 'max:100'],
            'mark_m1' => ['required', 'numeric', 'min:0', 'max:100'],
            'mark_kp' => ['required', 'numeric', 'min:0', 'max:100'],
            'mark_tkp' => ['required', 'numeric', 'min:0', 'max:100'],
            'mark_p1' => ['required', 'numeric', 'min:0', 'max:100'],
            'mark_penggulungan_gov' => ['required', 'numeric', 'min:0', 'max:100'],
            'mark_penggulungan_opp' => ['required', 'numeric', 'min:0', 'max:100'],
            'margin' => ['required', 'numeric', 'min:0', 'max:100'],
            'best_debater_member_id' => ['required', 'integer', 'exists:team_members,id'],
        ];
    }
}
