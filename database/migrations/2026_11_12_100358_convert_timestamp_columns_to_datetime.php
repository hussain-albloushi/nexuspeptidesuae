<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MySQL TIMESTAMP columns store UTC instants and reinterpret them through the
 * connection's session time zone. That means the database — not the website —
 * decides what time a row shows. DATETIME columns have no time zone logic at
 * all: the website's wall-clock value is stored and read back verbatim.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->isMySql()) {
            return;
        }

        // Pin the session to UTC so converting TIMESTAMP -> DATETIME does not
        // silently reinterpret the values that are already stored.
        DB::statement("SET time_zone = '+00:00'");

        foreach ($this->groupedTimestampColumns('timestamp') as $table => $columns) {
            $this->modifyColumns($table, $columns);
        }
    }

    /**
     * One-way change: reverting every DATETIME column to TIMESTAMP would
     * reintroduce database timezone conversion, and cannot distinguish the
     * columns that were originally DATETIME. A forward-fix migration is the
     * supported way back. The data shift that follows is reversible.
     */
    public function down(): void {}

    /** @return array<string, list<array{name: string, type: string, nullable: bool}>> */
    private function groupedTimestampColumns(string $dataType): array
    {
        $columns = DB::select(
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = ?',
            [$dataType]
        );

        $grouped = [];
        foreach ($columns as $column) {
            $grouped[$column->TABLE_NAME][] = [
                'name' => $column->COLUMN_NAME,
                'type' => $column->COLUMN_TYPE,
                'nullable' => $column->IS_NULLABLE === 'YES',
            ];
        }

        return $grouped;
    }

    /** @param list<array{name: string, type: string, nullable: bool}> $columns */
    private function modifyColumns(string $table, array $columns): void
    {
        $modifications = array_map(
            fn (array $column): string => sprintf(
                'MODIFY %s %s %s',
                $this->quoteIdentifier($column['name']),
                str_replace('timestamp', 'datetime', $column['type']),
                $column['nullable'] ? 'NULL' : 'NOT NULL'
            ),
            $columns
        );

        DB::statement(sprintf(
            'ALTER TABLE %s %s',
            $this->quoteIdentifier($table),
            implode(', ', $modifications)
        ));
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function isMySql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
