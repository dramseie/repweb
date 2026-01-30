<?php

namespace App\Enum;

enum IssueResolution: string
{
    case Fixed = 'fixed';
    case WontFix = 'wontfix';
    case Duplicate = 'duplicate';
    case Invalid = 'invalid';
    case CannotReproduce = 'cannot_reproduce';
    case Workaround = 'workaround';
}
