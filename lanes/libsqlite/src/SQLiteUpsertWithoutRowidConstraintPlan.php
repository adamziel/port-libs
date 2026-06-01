<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteUpsertWithoutRowidConstraintPlan
{
    private const SOURCE = 'upsert1.test';
    private const SCENARIO = 'upsert1-1000 WITHOUT ROWID primary-key NOT NULL failure aborts before UPSERT conflict handling';

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $primaryKey
     * @param list<string> $conflictTarget
     * @param list<string>|string $returning
     * @return array{
     *     source:string,
     *     scenario:string,
     *     table:string,
     *     without_rowid:true,
     *     primary_key:list<string>,
     *     conflict_target:list<string>,
     *     returning:list<string>,
     *     rc:int,
     *     ok:false,
     *     error:string,
     *     before:list<array<string,mixed>>,
     *     incoming_rows:list<array<string,mixed>>,
     *     failed_row:array<string,mixed>,
     *     failed_ordinal:int,
     *     failed_column:string,
     *     after:list<array<string,mixed>>,
     *     inserted_rows:list<array<string,mixed>>,
     *     updated_rows:list<array<string,mixed>>,
     *     skipped_rows:list<array<string,mixed>>,
     *     returning_rows:list<array<string,mixed>>,
     *     changes:int,
     *     processed_rows:int,
     *     conflict_probe_attempted:bool,
     *     later_rows_processed:bool,
     *     dependencies:list<string>
     * }
     */
    public static function missingPrimaryKeyAbort(
        array $rows,
        array $incomingRows,
        array $primaryKey,
        array $conflictTarget,
        string $table = 'app_without_rowid',
        array|string $returning = '*'
    ): array {
        self::assertRows($rows, 'base rows');
        self::assertRows($incomingRows, 'incoming rows');
        if ($incomingRows === []) {
            throw new InvalidArgumentException('SQLite upstream upsert1-1000 requires at least one incoming row');
        }

        $primaryKey = self::identifiers($primaryKey, 'primary key');
        if ($primaryKey === []) {
            throw new InvalidArgumentException('SQLite WITHOUT ROWID primary key cannot be empty');
        }

        $conflictTarget = self::identifiers($conflictTarget, 'conflict target');
        if ($conflictTarget === []) {
            throw new InvalidArgumentException('SQLite UPSERT conflict target cannot be empty');
        }

        $table = self::identifier($table, 'table');
        $projection = self::returningProjection($returning);
        $firstIncoming = $incomingRows[0];

        foreach ($primaryKey as $column) {
            if (!array_key_exists($column, $firstIncoming) || $firstIncoming[$column] === null) {
                return [
                    'source' => self::SOURCE,
                    'scenario' => self::SCENARIO,
                    'table' => $table,
                    'without_rowid' => true,
                    'primary_key' => $primaryKey,
                    'conflict_target' => $conflictTarget,
                    'returning' => $projection,
                    'rc' => 1,
                    'ok' => false,
                    'error' => 'NOT NULL constraint failed: ' . $table . '.' . $column,
                    'before' => array_values($rows),
                    'incoming_rows' => array_values($incomingRows),
                    'failed_row' => $firstIncoming,
                    'failed_ordinal' => 1,
                    'failed_column' => $column,
                    'after' => array_values($rows),
                    'inserted_rows' => [],
                    'updated_rows' => [],
                    'skipped_rows' => [],
                    'returning_rows' => [],
                    'changes' => 0,
                    'processed_rows' => 0,
                    'conflict_probe_attempted' => false,
                    'later_rows_processed' => false,
                    'dependencies' => self::dependencies(),
                ];
            }
        }

        throw new InvalidArgumentException('SQLite upstream upsert1-1000 fixture requires the first incoming row to miss a WITHOUT ROWID primary key');
    }

    /**
     * @return list<string>
     */
    public static function dependencies(): array
    {
        return [
            'upsert1.test-1000',
            'returning1.test-error-no-row-stream',
            'sqlite-without-rowid-primary-key-not-null-before-upsert-conflict',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function assertRows(array $rows, string $label): void
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite {$label} must be a list");
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite {$label} row {$index} must be an array");
            }
            foreach ($row as $column => $_value) {
                if (!is_string($column) || $column === '') {
                    throw new InvalidArgumentException("SQLite {$label} row {$index} has an invalid column name");
                }
            }
        }
    }

    private static function identifier(string $identifier, string $label): string
    {
        if ($identifier === '' || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $identifier) !== 1) {
            throw new InvalidArgumentException("SQLite {$label} identifier is malformed");
        }

        return $identifier;
    }

    /**
     * @param list<string> $identifiers
     * @return list<string>
     */
    private static function identifiers(array $identifiers, string $label): array
    {
        if (!array_is_list($identifiers)) {
            throw new InvalidArgumentException("SQLite {$label} identifiers must be a list");
        }

        foreach ($identifiers as $identifier) {
            self::identifier($identifier, $label);
        }

        return array_values($identifiers);
    }

    /**
     * @param list<string>|string $returning
     * @return list<string>
     */
    private static function returningProjection(array|string $returning): array
    {
        if ($returning === '*') {
            return ['*'];
        }

        if (!is_array($returning) || $returning === []) {
            throw new InvalidArgumentException('SQLite RETURNING projection cannot be empty');
        }

        foreach ($returning as $column) {
            self::identifier($column, 'RETURNING column');
        }

        return array_values($returning);
    }
}
