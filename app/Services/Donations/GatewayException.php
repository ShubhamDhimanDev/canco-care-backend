<?php

namespace App\Services\Donations;

use RuntimeException;

/**
 * The payment provider could not be reached or gave an answer we cannot use. The donation stays
 * pending: the donor may have been charged, so it must be reconciled, never silently retried.
 */
class GatewayException extends RuntimeException {}
