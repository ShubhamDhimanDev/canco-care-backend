<?php

namespace App\Console\Commands;

use App\Services\LegacyStoryImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

#[Signature('stories:import-legacy
    {--source= : Legacy blog API snapshot (JSON); defaults to the bundled data}
    {--images= : Directory with the story images named {legacy id}-*}
    {--alt= : JSON map of legacy id => image alt text}
    {--update : Overwrite stories that were already imported}
    {--dry-run : Show what would happen without writing anything}')]
#[Description('Import the stories published on the legacy Canco Cares site')]
class ImportLegacyStories extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LegacyStoryImporter $importer): int
    {
        $base = database_path('seeders/data/legacy-stories');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $results = $importer->import(
                sourcePath: (string) ($this->option('source') ?: $base.'/stories.json'),
                imagesPath: (string) ($this->option('images') ?: $base.'/images'),
                altPath: (string) ($this->option('alt') ?: $base.'/alt-text.json'),
                update: (bool) $this->option('update'),
                dryRun: $dryRun,
            );
        } catch (InvalidArgumentException|JsonException|RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Legacy ID', 'Title', 'Slug', 'Result', 'Notes'],
            array_map(fn (array $r): array => [
                $r['legacy_id'], $r['title'], $r['slug'], $r['result'], implode('; ', $r['notes']),
            ], $results),
        );

        $flagged = count(array_filter($results, fn (array $r): bool => $r['notes'] !== []));

        if ($flagged > 0) {
            $this->components->warn("{$flagged} stories need attention (see Notes).");
        }

        $this->components->info($dryRun
            ? 'Dry run: nothing was written.'
            : 'Done. Run "php artisan storage:link" once if images do not show.');

        return self::SUCCESS;
    }
}
