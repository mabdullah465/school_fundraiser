<?php

namespace App\Enums;

enum IngestionStatus: string
{
    /** Order stored, but not paid yet, so nothing is allocated. */
    case Pending = 'pending';
    /** Paid and allocated to a school. */
    case Ok = 'ok';
    /** Paid, but no valid approved school. Needs an admin. Never credited silently. */
    case Quarantined = 'quarantined';
}
