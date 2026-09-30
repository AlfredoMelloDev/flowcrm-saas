<?php

namespace App\Enums;

// Deliberately only two cases — "overdue" is never persisted. It's derived
// from status=pending + scheduled_at in the past (see ActivityResource).
enum ActivityStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
}
