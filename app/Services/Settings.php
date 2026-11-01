<?php

namespace App\Services;

use App\Jobs\RevalidateFrontend;
use App\Models\Setting;
use App\Support\SettingDefaults;
use Illuminate\Support\Facades\Cache;

/**
 * Reads and writes administrator-editable settings. All rows are loaded once and cached, because
 * almost every public request needs a few of them. Unknown keys are rejected on write so a typo
 * cannot silently create a setting nothing reads.
 */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default;
    }

    public function string(string $key): string
    {
        return (string) ($this->get($key) ?? '');
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $value = $this->get($key);

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }

    /**
     * Defaults overlaid with whatever is stored.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        /** @var array<string, mixed> $stored */
        $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()
            ->pluck('value', 'key')
            ->all());

        return $this->loaded = array_replace(SettingDefaults::all(), $stored);
    }

    /**
     * @param  array<string, mixed>  $values  key => value; every key must be a known setting
     */
    public function setMany(array $values): void
    {
        $known = SettingDefaults::all();

        foreach (array_keys($values) as $key) {
            if (! array_key_exists($key, $known)) {
                throw new \InvalidArgumentException("Unknown setting [{$key}].");
            }
        }

        // One revalidation for the whole save, not one per row.
        Setting::withoutEvents(function () use ($values): void {
            foreach ($values as $key => $value) {
                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'group' => SettingDefaults::groupOf($key)],
                );
            }
        });

        $this->flush();

        RevalidateFrontend::dispatch(['settings'])->afterCommit();
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }
}
