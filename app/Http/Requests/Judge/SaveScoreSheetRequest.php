<?php

namespace App\Http\Requests\Judge;

use App\Models\DebateMatch;
use App\Support\DebateScoreSheetRules;
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
        return DebateScoreSheetRules::rules();
    }

    public function messages(): array
    {
        return DebateScoreSheetRules::messages();
    }
}
