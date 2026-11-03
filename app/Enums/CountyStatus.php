<?php

namespace App\Enums;

enum CountyStatus: string
{
    case NotLoaded = 'not_loaded';
    case Queued = 'queued';
    case Loading = 'loading';
    case Loaded = 'loaded';
    case Failed = 'failed';
}
