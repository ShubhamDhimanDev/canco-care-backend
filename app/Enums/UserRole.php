<?php

namespace App\Enums;

enum UserRole: string
{
    /** Full access: users, settings, pages, donations and all content. */
    case Admin = 'admin';

    /** Content access: stories, events and inquiries. No users, settings, pages or donations. */
    case Editor = 'editor';
}
