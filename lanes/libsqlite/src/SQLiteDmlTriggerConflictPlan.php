<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteDmlTriggerConflictPlan
{
    /**
     * @param list<array<string,mixed>> $targetRows
     * @param list<array<string,mixed>> $sideRows
     * @param list<array<string,mixed>> $inputRows
     * @param list<array{timing:string,event:string,table:string,action:string,row?:array<string,mixed>,conflict_action?:string}> $triggers
     * @param list<string> $sideUniqueColumns
     * @return array{target:list<array<string,mixed>>,side:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,conflict_action:string,changes:int}
     */
    public static function insertRows(
        array $targetRows,
        array $sideRows,
        array $inputRows,
        array $triggers,
        array $sideUniqueColumns,
        string $statementConflictAction = 'abort',
    ): array {
        $statementConflictAction = self::normalizeConflictAction($statementConflictAction, 'statement');
        self::validateUniqueColumns($sideUniqueColumns);

        $target = array_values($targetRows);
        $side = array_values($sideRows);
        $inserted = [];
        $ignored = [];
        $effects = [];

        foreach ($inputRows as $input) {
            $before = self::applyTriggers('before', 'insert', $input, $side, $triggers, $sideUniqueColumns, $statementConflictAction);
            $effects = array_merge($effects, $before['effects']);
            if ($before['ignored']) {
                $ignored[] = $input;
                continue;
            }
            $side = $before['side'];

            $target[] = $input;
            $inserted[] = $input;

            $after = self::applyTriggers('after', 'insert', $input, $side, $triggers, $sideUniqueColumns, $statementConflictAction);
            $effects = array_merge($effects, $after['effects']);
            $side = $after['side'];
        }

        return [
            'target' => $target,
            'side' => $side,
            'inserted' => $inserted,
            'ignored' => $ignored,
            'trigger_effects' => $effects,
            'conflict_action' => $statementConflictAction,
            'changes' => count($inserted),
        ];
    }

    /**
     * @param list<array<string,mixed>> $side
     * @param list<array{timing:string,event:string,table:string,action:string,row?:array<string,mixed>,conflict_action?:string}> $triggers
     * @param list<string> $uniqueColumns
     * @return array{side:list<array<string,mixed>>,effects:list<array<string,mixed>>,ignored:bool}
     */
    private static function applyTriggers(
        string $timing,
        string $event,
        array $newRow,
        array $side,
        array $triggers,
        array $uniqueColumns,
        string $statementConflictAction,
    ): array {
        $effects = [];

        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (($trigger['table'] ?? null) !== 'side') {
                throw new \InvalidArgumentException('SQLite DML trigger conflict corpus supports side-table trigger actions only');
            }
            if (($trigger['action'] ?? null) !== 'insert') {
                throw new \InvalidArgumentException('SQLite DML trigger conflict corpus supports INSERT trigger actions only');
            }

            $triggerConflictAction = self::normalizeConflictAction((string) ($trigger['conflict_action'] ?? 'abort'), 'trigger');
            $effectiveConflictAction = $statementConflictAction === 'abort' ? $triggerConflictAction : $statementConflictAction;
            $row = self::triggerRow($trigger['row'] ?? [], $newRow);
            $conflictIndex = self::findConflictIndex($side, $row, $uniqueColumns);

            if ($conflictIndex !== null) {
                if ($effectiveConflictAction === 'ignore') {
                    $effects[] = [
                        'timing' => $timing,
                        'action' => 'insert',
                        'result' => 'ignored-conflict',
                        'effective_conflict_action' => $effectiveConflictAction,
                        'row' => $row,
                    ];
                    continue;
                }
                if ($effectiveConflictAction === 'replace') {
                    $side[$conflictIndex] = $row;
                    $effects[] = [
                        'timing' => $timing,
                        'action' => 'insert',
                        'result' => 'replaced-conflict',
                        'effective_conflict_action' => $effectiveConflictAction,
                        'row' => $row,
                    ];
                    continue;
                }
                if ($effectiveConflictAction === 'fail') {
                    $effects[] = [
                        'timing' => $timing,
                        'action' => 'insert',
                        'result' => 'failed-conflict',
                        'effective_conflict_action' => $effectiveConflictAction,
                        'row' => $row,
                    ];

                    return ['side' => array_values($side), 'effects' => $effects, 'ignored' => true];
                }

                throw new \InvalidArgumentException('SQLite trigger side-table unique constraint conflict');
            }

            $side[] = $row;
            $effects[] = [
                'timing' => $timing,
                'action' => 'insert',
                'result' => 'inserted',
                'effective_conflict_action' => $effectiveConflictAction,
                'row' => $row,
            ];
        }

        return ['side' => array_values($side), 'effects' => $effects, 'ignored' => false];
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function triggerRow(array $template, array $newRow): array
    {
        $row = [];
        foreach ($template as $column => $value) {
            if (is_string($value) && str_starts_with($value, 'new.')) {
                $sourceColumn = substr($value, 4);
                if (!array_key_exists($sourceColumn, $newRow)) {
                    throw new \InvalidArgumentException("SQLite trigger NEW column {$sourceColumn} is missing");
                }
                $row[$column] = $newRow[$sourceColumn];
                continue;
            }

            $row[$column] = $value;
        }

        return $row;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     */
    private static function findConflictIndex(array $rows, array $row, array $columns): ?int
    {
        foreach ($rows as $index => $candidate) {
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $candidate) || $row[$column] !== $candidate[$column]) {
                    continue 2;
                }
            }

            return $index;
        }

        return null;
    }

    /**
     * @param list<string> $columns
     */
    private static function validateUniqueColumns(array $columns): void
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite trigger conflict unique column list cannot be empty');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new \InvalidArgumentException('SQLite trigger conflict unique column is malformed');
            }
        }
    }

    private static function normalizeConflictAction(string $action, string $label): string
    {
        $action = strtolower(trim($action));
        if (!in_array($action, ['abort', 'fail', 'ignore', 'replace', 'rollback'], true)) {
            throw new \InvalidArgumentException("SQLite {$label} conflict action is unsupported");
        }

        return $action;
    }
}
