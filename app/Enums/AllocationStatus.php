<?php

namespace App\Enums;

enum AllocationStatus: string
{
    /** Allocated, still inside the hold period. */
    case Pending = 'pending';
    /** Hold period passed, eligible to be transferred to the school. */
    case Payable = 'payable';
    /** Fully transferred to the school's Connect account. */
    case Completed = 'completed';
    /** Refunded down to zero. */
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
}
