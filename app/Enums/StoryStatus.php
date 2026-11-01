<?php

namespace App\Enums;

enum StoryStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
