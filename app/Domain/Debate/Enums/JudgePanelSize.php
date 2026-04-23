<?php

namespace App\Domain\Debate\Enums;

enum JudgePanelSize: int
{
    case One = 1;
    case Three = 3;
    case Five = 5;
    case Seven = 7;

    /**
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_map(static fn (self $size): int => $size->value, self::cases());
    }
}
