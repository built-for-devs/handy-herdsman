<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a booking cannot proceed for a business-rule reason that carries
 * a client-facing message (spec §6). Examples: a declined service area, an
 * empty animal selection, or a chosen protocol slot that is no longer bookable.
 */
class BookingValidationException extends RuntimeException {}
