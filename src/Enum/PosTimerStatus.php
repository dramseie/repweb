<?php

namespace App\Enum;

enum PosTimerStatus: string
{
	case Idle = 'idle';
	case Running = 'running';
	case Paused = 'paused';
	case Finished = 'finished';
}
