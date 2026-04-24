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
            'mark_penggulungan_gov' => ['required', 'numeric', 'min:0', 'max:50'],
            'mark_penggulungan_opp' => ['required', 'numeric', 'min:0', 'max:50'],
            'margin' => ['required', 'numeric', 'min:1', 'max:8'],
            'best_debater_member_id' => ['required', 'integer', 'exists:team_members,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'mark_pm.max' => 'PM tidak boleh melebihi 100 markah.',
            'mark_tpm.max' => 'TPM tidak boleh melebihi 100 markah.',
            'mark_m1.max' => 'M1 tidak boleh melebihi 100 markah.',
            'mark_kp.max' => 'KP tidak boleh melebihi 100 markah.',
            'mark_tkp.max' => 'TKP tidak boleh melebihi 100 markah.',
            'mark_p1.max' => 'P1 tidak boleh melebihi 100 markah.',
            'mark_penggulungan_gov.max' => 'Penggulungan kerajaan tidak boleh melebihi 50 markah.',
            'mark_penggulungan_opp.max' => 'Penggulungan pembangkang tidak boleh melebihi 50 markah.',
            'margin.min' => 'Margin mesti sekurang-kurangnya 1.',
            'margin.max' => 'Margin tidak boleh melebihi 8.',
        ];
    }
}
