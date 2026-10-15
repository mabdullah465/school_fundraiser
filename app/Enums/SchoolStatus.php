<?php

namespace App\Enums;

enum SchoolStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Deactivated = 'deactivated';
}
