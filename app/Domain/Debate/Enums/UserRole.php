<?php

namespace App\Domain\Debate\Enums;

enum UserRole: string
{
    case Superadmin = 'superadmin';
    case Judge = 'judge';
}
