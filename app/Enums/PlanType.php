<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Breeding plan flavour on a protocol (spec §3, §5.2). `sync` is the CIDR
 * 10-day three-visit protocol; `natural` is a single timed farm call the
 * client requests when they recognise standing heat.
 */
enum PlanType: string
{
    case Natural = 'natural';
    case Sync = 'sync';
}
