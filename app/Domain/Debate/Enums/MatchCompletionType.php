<?php

namespace App\Domain\Debate\Enums;

enum MatchCompletionType: string
{
    case Normal = 'normal';
    case ForceCompleted = 'force_completed';
}
