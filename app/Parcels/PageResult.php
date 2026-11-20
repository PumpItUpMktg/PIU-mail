<?php

namespace App\Parcels;

/**
 * What one page job did, and what the next job in the county's chain is.
 */
final readonly class PageResult
{
    public function __construct(
        /** The fetch failed: retry the same offset with nextLimit (smaller). */
        public bool $split,
        public int $nextOffset,
        public int $nextLimit,
        /** Another page follows (ignored when split). */
        public bool $more,
    ) {}
}
