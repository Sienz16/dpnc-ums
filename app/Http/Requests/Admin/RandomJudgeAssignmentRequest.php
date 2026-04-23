<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RandomJudgeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'eligible_judge_ids' => ['nullable', 'array'],
            'eligible_judge_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
        ];
    }
}
