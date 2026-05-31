<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

/**
 * Models the connection-counter boundary exercised by SQLite changes2.test.
 */
final class SQLiteReturningPreparedStatementPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @return array{
     *     before:list<array<string,mixed>>,
     *     after:list<array<string,mixed>>,
     *     returning_rows:list<array<string,mixed>>,
     *     step_trace:list<array{result:string,changes:int,row:array<string,mixed>|null}>,
     *     changed_rows:int,
     *     counters:array{last_insert_rowid:int,changes:int,total_changes:int},
     *     dependencies:list<string>
     * }
     */
    public static function updateReturningSteps(
        array $rows,
        string $idColumn,
        mixed $idValue,
        string $valueColumn,
        mixed $newValue,
        string $returningColumn,
        ?SQLiteConnectionCounters $counters = null
    ): array {
        self::assertRows($rows);
        self::assertIdentifier('id column', $idColumn);
        self::assertIdentifier('value column', $valueColumn);
        self::assertIdentifier('returning column', $returningColumn);

        $counters ??= SQLiteConnectionCounters::initial();
        $before = array_values($rows);
        $after = [];
        $returningRows = [];

        foreach ($before as $index => $row) {
            self::assertRowHasColumn($row, $idColumn, $index);
            self::assertRowHasColumn($row, $valueColumn, $index);
            self::assertRowHasColumn($row, $returningColumn, $index);

            if (self::sqliteEquals($row[$idColumn], $idValue)) {
                $row[$valueColumn] = $newValue;
                $returningRows[] = [$returningColumn => $row[$returningColumn]];
            }

            $after[] = $row;
        }

        $changedRows = count($returningRows);
        $counters->recordUpdate($changedRows);
        $stepTrace = [];

        foreach ($returningRows as $returningRow) {
            $stepTrace[] = [
                'result' => 'SQLITE_ROW',
                'changes' => $counters->changes(),
                'row' => $returningRow,
            ];
        }

        $stepTrace[] = [
            'result' => 'SQLITE_DONE',
            'changes' => $counters->changes(),
            'row' => null,
        ];

        return [
            'before' => $before,
            'after' => $after,
            'returning_rows' => $returningRows,
            'step_trace' => $stepTrace,
            'changed_rows' => $changedRows,
            'counters' => $counters->toArray(),
            'dependencies' => [
                'changes2.test-1.1',
                'changes2.test-1.2',
                'changes2.test-1.3',
                'changes2.test-1.4',
                'sqlite-prepared-returning-step-counter',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $logRows
     * @return array{
     *     before:list<array<string,mixed>>,
     *     after:list<array<string,mixed>>,
     *     inserted:array<string,mixed>,
     *     value:string,
     *     step_trace:list<array{result:string,changes:int,row:null}>,
     *     counters_before:array{last_insert_rowid:int,changes:int,total_changes:int},
     *     counters_after:array{last_insert_rowid:int,changes:int,total_changes:int},
     *     dependencies:list<string>
     * }
     */
    public static function insertChangesLog(
        array $logRows,
        SQLiteConnectionCounters $counters,
        string $messageColumn = 'message',
        ?int $rowId = null
    ): array {
        self::assertRows($logRows);
        self::assertIdentifier('message column', $messageColumn);

        $before = array_values($logRows);
        $countersBefore = $counters->toArray();
        $message = $counters->changes() . ' changes';
        $rowId ??= count($before) + 1;

        $inserted = [
            $messageColumn => $message,
            'rowid' => $rowId,
        ];
        $after = $before;
        $after[] = $inserted;

        $counters->recordInsert($rowId);

        return [
            'before' => $before,
            'after' => $after,
            'inserted' => $inserted,
            'value' => $message,
            'step_trace' => [
                [
                    'result' => 'SQLITE_DONE',
                    'changes' => $counters->changes(),
                    'row' => null,
                ],
            ],
            'counters_before' => $countersBefore,
            'counters_after' => $counters->toArray(),
            'dependencies' => [
                'changes2.test-2.1',
                'changes2.test-2.2',
                'changes2.test-2.3',
                'changes2.test-2.4',
                'sqlite-prepared-changes-function-evaluation',
            ],
        ];
    }

    private static function sqliteEquals(mixed $left, mixed $right): bool
    {
        if ((is_int($left) || is_float($left) || is_string($left)) && (is_int($right) || is_float($right) || is_string($right))) {
            $leftNumeric = filter_var($left, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            $rightNumeric = filter_var($right, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            if ($leftNumeric !== null && $rightNumeric !== null) {
                return (float) $leftNumeric === (float) $rightNumeric;
            }
        }

        return $left === $right;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function assertRows(array $rows): void
    {
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite prepared row {$index} must be an array");
            }

            foreach ($row as $column => $_value) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException("SQLite prepared row {$index} has an invalid column name");
                }
            }
        }
    }

    private static function assertIdentifier(string $label, string $identifier): void
    {
        if ($identifier === '' || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $identifier) !== 1) {
            throw new \InvalidArgumentException("SQLite prepared {$label} must be a simple identifier");
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function assertRowHasColumn(array $row, string $column, int $index): void
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite prepared row {$index} is missing column {$column}");
        }
    }
}
