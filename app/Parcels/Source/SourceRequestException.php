<?php

namespace App\Parcels\Source;

use RuntimeException;

/**
 * Raised for source HTTP / ArcGIS errors. The message carries only the
 * operation, status and ArcGIS error text: NEVER a response body, which could
 * contain owner fields and would otherwise land in logs and failed_jobs.
 */
class SourceRequestException extends RuntimeException {}
