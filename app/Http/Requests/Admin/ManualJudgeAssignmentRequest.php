<?php

namespace App\Http\Requests\Admin;

use App\Domain\Debate\Enums\JudgePanelSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualJudgeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        $panelSize = $this->route('match')->judge_panel_size;
        $expectedSize = $panelSize instanceof JudgePanelSize ? $panelSize->value : (int) $panelSize;

        return [
            'judge_ids' => ['required', 'array', 'size:'.$expectedSize],
            'judge_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
        ];
    }
}
