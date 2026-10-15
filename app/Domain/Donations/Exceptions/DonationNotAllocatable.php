<?php

namespace App\Domain\Donations\Exceptions;

use RuntimeException;

/**
 * A paid order cannot be credited. The caller quarantines it for an admin.
 */
class DonationNotAllocatable extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    public static function missingSchool(): self
    {
        return new self('missing_or_unknown_school');
    }

    public static function schoolNotApproved(): self
    {
        return new self('school_not_approved');
    }

    public static function noActiveRate(): self
    {
        return new self('no_active_rate');
    }
}
