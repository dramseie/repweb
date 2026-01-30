<?php

namespace App\Enum;

enum IssueLinkType: string
{
    case Blocks = 'blocks';
    case IsBlockedBy = 'is_blocked_by';
    case RelatesTo = 'relates_to';
    case Duplicates = 'duplicates';
    case IsDuplicatedBy = 'is_duplicated_by';
    case ParentOf = 'parent_of';
    case ChildOf = 'child_of';
    case Causes = 'causes';
    case IsCausedBy = 'is_caused_by';
}
