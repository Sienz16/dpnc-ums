<?php

namespace App\Http\Requests\Admin;

use App\Models\DebateMatch;
use App\Support\DebateScoreSheetRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ForceCompleteMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return array_merge([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'score_sheets' => ['array'],
            'score_sheets.*.judge_id' => ['required', 'integer'],
        ], DebateScoreSheetRules::rules('score_sheets.*.'));
    }

    public function messages(): array
    {
        return DebateScoreSheetRules::messages('score_sheets.*.');
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var DebateMatch $match */
                $match = $this->route('match');

                $missingJudgeIds = $match->judgeAssignments()
                    ->whereNull('submitted_at')
                    ->pluck('judge_id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values();

                if ($missingJudgeIds->isEmpty()) {
                    return;
                }

                $providedJudgeIds = collect($this->input('score_sheets', []))
                    ->pluck('judge_id')
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values();

                $missingProvidedJudgeIds = $missingJudgeIds->diff($providedJudgeIds)->values();

                if ($missingProvidedJudgeIds->isNotEmpty()) {
                    $validator->errors()->add(
                        'score_sheets',
                        'Lengkapkan borang markah untuk semua hakim yang belum menghantar keputusan sebelum tamat paksa.',
                    );
                }
            },
        ];
    }
}
