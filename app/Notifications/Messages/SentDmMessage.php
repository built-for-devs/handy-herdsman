<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

/**
 * A plain-text SMS body destined for the sent.dm channel (spec §5.7). Kept
 * deliberately tiny — SMS is a single field.
 */
final class SentDmMessage
{
    public function __construct(public string $content = '') {}

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }
}
