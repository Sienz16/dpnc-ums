<?php

namespace App\Domain\Debate\Enums;

enum TeamSide: string
{
    case Government = 'government';
    case Opposition = 'opposition';
}
