<?php

namespace App\Enum;

enum IssuePriority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Trivial = 'trivial';
}
