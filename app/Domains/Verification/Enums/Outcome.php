<?php

namespace App\Domains\Verification\Enums;

/** Every provider result is normalised to one of these (decision K4). */
enum Outcome: string
{
    case Passed = 'passed';
    case Review = 'review';
    case Failed = 'failed';
}
