<?php

namespace App\Http\Requests\Admin;

use App\Domain\Debate\Enums\SpeakerPosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'speaker_position' => [
                'required',
                Rule::in(SpeakerPosition::values()),
                Rule::unique('team_members', 'speaker_position')->where(fn ($query) => $query->where('team_id', $this->route('team')->id)),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
