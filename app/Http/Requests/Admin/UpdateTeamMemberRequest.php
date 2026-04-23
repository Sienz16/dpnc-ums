<?php

namespace App\Http\Requests\Admin;

use App\Domain\Debate\Enums\SpeakerPosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        $team = $this->route('team');
        $member = $this->route('member');

        return [
            'full_name' => ['sometimes', 'string', 'max:255'],
            'speaker_position' => [
                'sometimes',
                Rule::in(SpeakerPosition::values()),
                Rule::unique('team_members', 'speaker_position')
                    ->where(fn ($query) => $query->where('team_id', $team->id))
                    ->ignore($member),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
