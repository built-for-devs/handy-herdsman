<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The stored animal type on `cattle` (§10b — Animal type). "calf"/"weanling"
 * are NOT types — they are display labels computed from `dob` (see AgeStage).
 *
 * Protocol timing keys off this: a heifer's Visit 3 window is earlier/tighter
 * than a cow's. `bull` and `steer` cannot receive a breeding service.
 */
enum AnimalType: string
{
    case Heifer = 'heifer';
    case Cow = 'cow';
    case Bull = 'bull';
    case Steer = 'steer';

    /**
     * Female breeding animals can receive AI/breeding services; males cannot.
     */
    public function canBeBred(): bool
    {
        return match ($this) {
            self::Heifer, self::Cow => true,
            self::Bull, self::Steer => false,
        };
    }

    /**
     * Config key under `protocol.windows` for this type's Visit 3 window.
     * Only breeding-eligible females have a window.
     */
    public function windowKey(): string
    {
        return match ($this) {
            self::Heifer => 'heifer',
            self::Cow => 'cow',
            default => throw new \LogicException("{$this->value} has no breeding window."),
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
