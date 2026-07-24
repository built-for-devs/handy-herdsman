<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Payment lifecycle (spec §5.5, §10b — Money):
 *
 *  pending  → card captured at booking, not yet charged (awaiting confirm).
 *  paid     → charged successfully when the booking reached `confirmed`.
 *  owed     → cash booking; charge skipped, Jeff settles in person.
 *  failed   → the confirm-time charge failed (retryable by staff).
 *  refunded → reserved for later refunds.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Owed = 'owed';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
