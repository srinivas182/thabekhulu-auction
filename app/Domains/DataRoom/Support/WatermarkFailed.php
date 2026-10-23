<?php

namespace App\Domains\DataRoom\Support;

use RuntimeException;

/** A document could not be watermarked, so it must not be served. */
final class WatermarkFailed extends RuntimeException {}
