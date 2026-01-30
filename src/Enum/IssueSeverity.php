<?php

namespace App\Enum;

enum IssueSeverity: string
{
    case Blocker = 'blocker';
    case Critical = 'critical';
    case Major = 'major';
    case Minor = 'minor';
    case Trivial = 'trivial';
}
