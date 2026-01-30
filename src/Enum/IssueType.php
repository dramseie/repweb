<?php

namespace App\Enum;

enum IssueType: string
{
    case Bug = 'bug';
    case Feature = 'feature';
    case Enhancement = 'enhancement';
    case Task = 'task';
    case Epic = 'epic';
    case Story = 'story';
    case Spike = 'spike';
    case Incident = 'incident';
}
