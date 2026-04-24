<?php

namespace App\Support;

class DebateScoreSheetRules
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(string $prefix = ''): array
    {
        $field = static fn (string $name): string => $prefix.$name;

        return [
            $field('mark_pm') => ['required', 'numeric', 'min:0', 'max:100'],
            $field('mark_tpm') => ['required', 'numeric', 'min:0', 'max:100'],
            $field('mark_m1') => ['required', 'numeric', 'min:0', 'max:100'],
            $field('mark_kp') => ['required', 'numeric', 'min:0', 'max:100'],
            $field('mark_tkp') => ['required', 'numeric', 'min:0', 'max:100'],
            $field('mark_p1') => ['required', 'numeric', 'min:0', 'max:100'],
            $field('mark_penggulungan_gov') => ['required', 'numeric', 'min:0', 'max:50'],
            $field('mark_penggulungan_opp') => ['required', 'numeric', 'min:0', 'max:50'],
            $field('margin') => ['required', 'numeric', 'min:1', 'max:8'],
            $field('best_debater_member_id') => ['required', 'integer', 'exists:team_members,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $prefix = ''): array
    {
        $field = static fn (string $name, string $rule): string => $prefix.$name.'.'.$rule;

        return [
            $field('mark_pm', 'max') => 'PM tidak boleh melebihi 100 markah.',
            $field('mark_tpm', 'max') => 'TPM tidak boleh melebihi 100 markah.',
            $field('mark_m1', 'max') => 'M1 tidak boleh melebihi 100 markah.',
            $field('mark_kp', 'max') => 'KP tidak boleh melebihi 100 markah.',
            $field('mark_tkp', 'max') => 'TKP tidak boleh melebihi 100 markah.',
            $field('mark_p1', 'max') => 'P1 tidak boleh melebihi 100 markah.',
            $field('mark_penggulungan_gov', 'max') => 'Penggulungan kerajaan tidak boleh melebihi 50 markah.',
            $field('mark_penggulungan_opp', 'max') => 'Penggulungan pembangkang tidak boleh melebihi 50 markah.',
            $field('margin', 'min') => 'Margin mesti sekurang-kurangnya 1.',
            $field('margin', 'max') => 'Margin tidak boleh melebihi 8.',
        ];
    }
}
