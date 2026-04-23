<?php

namespace App\Domain\Debate\Enums;

enum JudgeAssignmentMode: string
{
    case Manual = 'manual';
    case Random = 'random';
}
