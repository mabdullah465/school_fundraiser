<?php

namespace App\Enums;

enum OrderDisplayStatus: string
{
    case Pending = 'pending';
    case Placed = 'placed';
    case InProcess = 'in_process';
    case Shipped = 'shipped';
    case Received = 'received';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
