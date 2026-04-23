<?php

namespace App\Domain\Debate\Enums;

enum SpeakerPosition: string
{
    case SpeakerOne = 'speaker_1';
    case SpeakerTwo = 'speaker_2';
    case SpeakerThree = 'speaker_3';
    case SpeakerFour = 'speaker_4';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $position): string => $position->value, self::cases());
    }

    /**
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        return [
            self::SpeakerOne,
            self::SpeakerTwo,
            self::SpeakerThree,
            self::SpeakerFour,
        ];
    }

    public function slotNumber(): int
    {
        return match ($this) {
            self::SpeakerOne => 1,
            self::SpeakerTwo => 2,
            self::SpeakerThree => 3,
            self::SpeakerFour => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SpeakerOne => 'Pendebat 1',
            self::SpeakerTwo => 'Pendebat 2',
            self::SpeakerThree => 'Pendebat 3',
            self::SpeakerFour => 'Pendebat 4 (Simpanan)',
        };
    }

    public function roleLabel(TeamSide $side): string
    {
        return match ($side) {
            TeamSide::Government => match ($this) {
                self::SpeakerOne => 'Perdana Menteri',
                self::SpeakerTwo => 'Timbalan Perdana Menteri',
                self::SpeakerThree => 'Menteri Pertahanan',
                self::SpeakerFour => 'Simpanan Kerajaan',
            },
            TeamSide::Opposition => match ($this) {
                self::SpeakerOne => 'Ketua Pembangkang',
                self::SpeakerTwo => 'Timbalan Ketua Pembangkang',
                self::SpeakerThree => 'Pembangkang Pertama',
                self::SpeakerFour => 'Simpanan Pembangkang',
            },
        };
    }

    public function scoreField(TeamSide $side): ?string
    {
        return match ($side) {
            TeamSide::Government => match ($this) {
                self::SpeakerOne => 'mark_pm',
                self::SpeakerTwo => 'mark_tpm',
                self::SpeakerThree => 'mark_m1',
                self::SpeakerFour => null,
            },
            TeamSide::Opposition => match ($this) {
                self::SpeakerOne => 'mark_kp',
                self::SpeakerTwo => 'mark_tkp',
                self::SpeakerThree => 'mark_p1',
                self::SpeakerFour => null,
            },
        };
    }

    public function isReserve(): bool
    {
        return $this === self::SpeakerFour;
    }
}
