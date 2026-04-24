<?php

namespace App\Http\Requests\Admin;

use App\Support\DebateScoreSheetRules;
use Illuminate\Foundation\Http\FormRequest;

class AdminScoreSheetUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return array_merge(
            DebateScoreSheetRules::rules(),
            [
                'reason' => ['required', 'string', 'min:3', 'max:1000'],
            ],
        );
    }

    public function messages(): array
    {
        return DebateScoreSheetRules::messages();
    }
}
