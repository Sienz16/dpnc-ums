<?php

namespace App\Domain\Debate\Enums;

enum ScoreSheetState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}
