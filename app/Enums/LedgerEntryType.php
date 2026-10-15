<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case DonationCredit = 'donation_credit';
    case RefundAdjustment = 'refund_adjustment';
    case ChargebackAdjustment = 'chargeback_adjustment';
    case CancellationReversal = 'cancellation_reversal';
    case ManualAdjustment = 'manual_adjustment';
}
