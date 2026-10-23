<?php

namespace App\Domains\DataRoom\Settings;

use Spatie\LaravelSettings\Settings;

class DataRoomSettings extends Settings
{
    /** NDA shown before the data room opens (attorney-reviewed HTML). */
    public string $nda_text;

    /** Increases whenever the NDA text changes; bidders accept the current version again. */
    public int $nda_version;

    /** Downloads by one bidder in 24 hours above which staff see a flag. */
    public int $daily_download_alert;

    public static function group(): string
    {
        return 'dataroom';
    }
}
