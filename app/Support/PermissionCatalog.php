<?php

namespace App\Support;

/**
 * Every permission the admin knows about, grouped for the role form.
 *
 * This list is the single source of truth: `PermissionSync` writes it to the database (migration and
 * `php artisan permissions:sync`), policies and routes check these names, and the role screen draws
 * its checkboxes from it. Add a permission here, run `permissions:sync`, then use it.
 *
 * Managing users and roles is deliberately NOT a permission: it stays with the super administrator
 * (`users.role = admin`), because whoever can edit roles could grant themselves everything.
 */
final class PermissionCatalog
{
    public const EDITOR_ROLE = 'Editor';

    /**
     * @return array<string, array{label: string, description: string, permissions: array<string, string>}>
     */
    public static function groups(): array
    {
        return [
            'stories' => [
                'label' => 'Stories',
                'description' => 'The blog.',
                'permissions' => [
                    'stories.view' => 'See the list of stories',
                    'stories.create' => 'Write new stories',
                    'stories.update' => 'Edit stories (including publishing, scheduling and tags)',
                    'stories.delete' => 'Move stories to the trash and restore them',
                    'stories.force-delete' => 'Delete stories permanently',
                ],
            ],
            'events' => [
                'label' => 'Events',
                'description' => 'Upcoming and past events.',
                'permissions' => [
                    'events.view' => 'See the list of events',
                    'events.create' => 'Create events',
                    'events.update' => 'Edit events (including publishing)',
                    'events.delete' => 'Move events to the trash and restore them',
                    'events.force-delete' => 'Delete events permanently',
                ],
            ],
            'home-page' => [
                'label' => 'Home page',
                'description' => 'The words and pictures on the home page.',
                'permissions' => [
                    'home-page.update' => 'Edit the home page',
                ],
            ],
            'media' => [
                'label' => 'Media library',
                'description' => 'Uploaded pictures. Anyone who edits stories, events or the home page can already pick and upload pictures from inside those screens.',
                'permissions' => [
                    'media.view' => 'Open the media library',
                    'media.create' => 'Upload pictures',
                    'media.update' => 'Edit alt text and convert files',
                    'media.delete' => 'Delete pictures that are not in use',
                ],
            ],
            'inquiries' => [
                'label' => 'Inquiries',
                'description' => 'Messages from the contact form.',
                'permissions' => [
                    'inquiries.view' => 'Read inquiries',
                    'inquiries.update' => 'Change status and add notes',
                    'inquiries.delete' => 'Delete inquiries',
                    'inquiries.export' => 'Download inquiries as CSV',
                ],
            ],
            'donations' => [
                'label' => 'Donations',
                'description' => 'Donors’ names, addresses and amounts. Give this to as few people as possible.',
                'permissions' => [
                    'donations.view' => 'See donations and receipts',
                    'donations.export' => 'Download donations as CSV',
                    'donations.resend' => 'Resend receipts',
                ],
            ],
            'settings' => [
                'label' => 'Settings',
                'description' => 'Website-wide configuration.',
                'permissions' => [
                    'site-settings.manage' => 'Change site settings (impact figure, receipts, donation options)',
                    'inquiry-topics.manage' => 'Manage inquiry topics and who receives them',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(...array_values(array_map(
            fn (array $group): array => array_keys($group['permissions']),
            self::groups(),
        )));
    }

    /**
     * What a plain "Editor" may do: everything about content, nothing about money, settings or
     * permanent deletion. This matches how the first two roles (admin / editor) behaved before roles
     * became configurable.
     *
     * @return list<string>
     */
    public static function editorDefaults(): array
    {
        return [
            'stories.view', 'stories.create', 'stories.update', 'stories.delete',
            'events.view', 'events.create', 'events.update', 'events.delete',
            'home-page.update',
            'media.view', 'media.create', 'media.update', 'media.delete',
            'inquiries.view', 'inquiries.update', 'inquiries.delete', 'inquiries.export',
        ];
    }
}
