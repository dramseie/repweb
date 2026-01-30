<?php

namespace App\Enum;

enum IssueStatus: string
{
    case New = 'new';
    case Open = 'open';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Reopened = 'reopened';
    case Blocked = 'blocked';
}
