<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/** Sentinel owner values planted in the fixture; must never be persisted. */
function ownerSentinels(): array
{
    return ['SENTINEL OWNER NAME', 'SENTINELVILLE', '07999-1234', 'PO BOX 999', '1 CORPORATE PLAZA'];
}

function fixturePath(string $name): string
{
    return __DIR__.'/Fixtures/'.$name;
}
