<?php

use App\Support\ZoneConverter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The organiser's intent is a wall-clock time in a zone ("6 PM in Vancouver"). Zone rules for
     * future dates can change (BC moved to permanent UTC-7 on 1 Nov 2026), so the wall-clock time is
     * now stored next to the UTC instant and is what the website prints. `events:resync-utc`
     * re-derives the UTC instants after a time-zone database update.
     *
     * The backfill converts the stored UTC with the rules in config/timezones.php, then the server's own.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('starts_local')->nullable()->after('is_all_day');
            $table->dateTime('ends_local')->nullable()->after('starts_local');
        });

        DB::table('events')->orderBy('id')->each(function (object $row): void {
            $local = fn (?string $utc): ?string => $utc === null
                ? null
                : ZoneConverter::toWallClock(CarbonImmutable::parse($utc, 'UTC'), $row->timezone);

            DB::table('events')->where('id', $row->id)->update([
                'starts_local' => $local($row->starts_at),
                'ends_local' => $local($row->ends_at),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['starts_local', 'ends_local']);
        });
    }
};
