<?php

namespace App\Enum;

enum IssueActivityType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Commented = 'commented';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case Attached = 'attached';
    case Linked = 'linked';
    case Mentioned = 'mentioned';
}
