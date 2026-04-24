<?php

namespace App\Http\Requests\Admin;

use App\Domain\Debate\Enums\SpeakerPosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $team = $this->route('team');
                $member = $this->route('member');

                if (! $this->has('speaker_position')) {
                    return;
                }

                if ($this->input('speaker_position') === $member->speaker_position->value) {
                    return;
                }

                if ($team->roster_locked) {
                    $validator->errors()->add(
                        'speaker_position',
                        'Posisi pendebat tidak boleh diubah selepas pasukan memulakan perlawanan. Buka ciri lineup per perlawanan jika anda perlukan pertukaran semasa kejohanan.',
                    );
                }
            },
        ];
    }
}
