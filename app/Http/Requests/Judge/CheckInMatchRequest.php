<?php

namespace App\Http\Requests\Judge;

use App\Models\DebateMatch;
use Illuminate\Foundation\Http\FormRequest;

class CheckInMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DebateMatch $match */
        $match = $this->route('match');

        return $this->user()?->can('view', $match) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
