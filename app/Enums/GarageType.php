<?php

namespace App\Enums;

enum GarageType: string
{
    case Attached = 'attached';
    case Unattached = 'unattached';

    /** A garage is stated (e.g. "G2") but not whether it is attached. */
    case Unknown = 'unknown';
}
