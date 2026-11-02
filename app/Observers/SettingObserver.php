<?php

namespace App\Observers;

use App\Jobs\RevalidateFrontend;
use App\Models\Setting;
use App\Services\Settings;

/**
 * Settings feed the home page, the footer, the impact figure and the donation form, so any change
 * drops the website's "settings" cache (the Settings service's own cache is cleared here too).
 */
class SettingObserver
{
    public function saved(Setting $setting): void
    {
        $this->refresh();
    }

    public function deleted(Setting $setting): void
    {
        $this->refresh();
    }

    private function refresh(): void
    {
        app(Settings::class)->flush();

        RevalidateFrontend::dispatch(['settings'])->afterCommit();
    }
}
