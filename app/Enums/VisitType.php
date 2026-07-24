<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kind of farm call a Visit represents (spec §3, §5.5). V1/V2/V3 are the
 * three sync-protocol visits; `standard` is a single dated visit; `oncall` is
 * an expedited standing-heat / emergency call.
 */
enum VisitType: string
{
    case Visit1 = 'v1';
    case Visit2 = 'v2';
    case Visit3 = 'v3';
    case Standard = 'standard';
    case OnCall = 'oncall';
}
