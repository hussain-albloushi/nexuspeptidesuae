<?php

use App\Models\Setting;
use App\Services\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rows written before this change hold UTC wall-clock values. Rebase them to
 * the store timezone (Asia/Dubai, UTC+4, no DST) so history and new records
 * read consistently from the website clock. The shift keeps every instant
 * intact: the same moment is now expressed as website time instead of UTC.
 */
return new class extends Migration
{
    private const SHIFT_HOURS = 4;

    private const SKIPPED_TABLES = ['migrations', 'settings'];

    public function up(): void
    {
        $this->shift(self::SHIFT_HOURS);
        $this->writeTimezoneSetting(SiteSettings::DEFAULT_TIMEZONE);
    }

    public function down(): void
    {
        $this->shift(-self::SHIFT_HOURS);
        $this->writeTimezoneSetting('UTC');
    }

    private function writeTimezoneSetting(string $timezone): void
    {
        Setting::updateOrCreate(
            ['key' => 'timezone'],
            ['value' => $timezone, 'type' => 'string', 'is_secret' => false]
        );

        // This process (and any worker started later) must use the same clock
        // as the stored values.
        app(SiteSettings::class)->forget();
        app(SiteSettings::class)->applyRuntimeConfig();
    }

    private function shift(int $hours): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return; // Fresh SQLite databases have no historical rows.
        }

        // Restrict to the current database: without an explicit schema
        // getTables() lists every schema on the server, which would shift
        // same-named tables more than once.
        $seen = [];
        foreach (Schema::getTables(DB::getDatabaseName()) as $table) {
            if (isset($seen[$table['name']]) || in_array($table['name'], self::SKIPPED_TABLES, true)) {
                continue;
            }
            $seen[$table['name']] = true;

            $columns = array_values(array_filter(
                Schema::getColumns($table['name']),
                fn (array $column): bool => in_array(strtolower($column['type_name']), ['timestamp', 'datetime'], true)
            ));

            if ($columns === []) {
                continue;
            }

            $assignments = array_map(
                fn (array $column): string => sprintf(
                    '%s = DATE_ADD(%s, INTERVAL %d HOUR)',
                    $this->quote($column['name']),
                    $this->quote($column['name']),
                    $hours
                ),
                $columns
            );

            DB::statement(sprintf(
                'UPDATE %s SET %s',
                $this->quote($table['name']),
                implode(', ', $assignments)
            ));
        }
    }

    private function quote(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
};
