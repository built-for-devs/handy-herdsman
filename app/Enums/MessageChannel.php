<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Messaging channel for a preference category (spec §5.7). Each category is
 * email, text, or both, and at least one channel must always stay on.
 */
enum MessageChannel: string
{
    case Email = 'email';
    case Text = 'text';
    case Both = 'both';

    public function includesEmail(): bool
    {
        return $this === self::Email || $this === self::Both;
    }

    public function includesText(): bool
    {
        return $this === self::Text || $this === self::Both;
    }

    /**
     * The channel with SMS removed — used when the client has not granted SMS
     * consent for a category. `both` degrades to email; `text` degrades to
     * email (email needs no separate consent and is always deliverable).
     */
    public function withoutText(): self
    {
        return self::Email;
    }
}
