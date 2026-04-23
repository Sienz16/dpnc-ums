<?php

namespace App\Domain\Debate\Enums;

enum MatchResultState: string
{
    case Provisional = 'provisional';
    case Final = 'final';
}
