<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    /** Stays on the website, flagged, so links people already shared do not 404. */
    case Cancelled = 'cancelled';
}
