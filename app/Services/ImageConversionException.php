<?php

namespace App\Services;

use RuntimeException;

/**
 * An uploaded image could not be converted to WebP. The message is written for the person who
 * uploaded it and is shown as a validation error.
 */
class ImageConversionException extends RuntimeException {}
