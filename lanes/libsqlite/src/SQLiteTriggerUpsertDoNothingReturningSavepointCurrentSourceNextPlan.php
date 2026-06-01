<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $savepointRows
     * @param list<array<string,mixed>> $currentIncoming
     * @param list<array<string,mixed>> $nextIncoming
     * @param list<string> $uniqueColumns
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,string,int):mixed> $returning
     * @param array{savepoint?:string,current_source?:string,next_source?:string,rollback_current?:bool} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $savepointRows,
        array $currentIncoming,
        array $nextIncoming,
        array $uniqueColumns,
        array $triggers,
        array $returning,
        array $options = [],
    ): array {
        $savepoint = self::identifier((string) ($options['savepoint'] ?? 'app_upsert_do_nothing_returning'), 'savepoint');
        $currentSource = self::source((string) ($options['current_source'] ?? 'current-upsert-do-nothing-returning'));
        $nextSource = self::source((string) ($options['next_source'] ?? 'next-upsert-do-nothing-returning'));
        $rollbackCurrent = (bool) ($options['rollback_current'] ?? false);

        self::validateColumns($uniqueColumns, 'conflict target column');
        if ($returning === []) {
            throw new \InvalidArgumentException('SQLite trigger UPSERT DO NOTHING RETURNING projection cannot be empty');
        }

        $savepointImage = array_values($savepointRows);
        $current = self::runSource($savepointImage, $currentIncoming, $uniqueColumns, $triggers, $returning, $currentSource);
        $nextStartRows = $rollbackCurrent ? $savepointImage : $current['rows'];
        $next = self::runSource($nextStartRows, $nextIncoming, $uniqueColumns, $triggers, $returning, $nextSource);

        return [
            'status' => $rollbackCurrent
                ? 'trigger-upsert-do-nothing-returning-current-source-next142-rolled-back'
                : 'trigger-upsert-do-nothing-returning-current-source-next142-released',
            'savepoint' => $savepoint,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'rollback_current' => $rollbackCurrent,
            'savepoint_rows' => $savepointImage,
            'current_statement_rows' => $current['rows'],
            'next_start_rows' => $nextStartRows,
            'next_rows' => $next['rows'],
            'current_returning_rows' => $rollbackCurrent ? [] : $current['returning_rows'],
            'attempted_current_returning_rows' => $current['returning_rows'],
            'next_returning_rows' => $next['returning_rows'],
            'returning_rows' => array_merge($rollbackCurrent ? [] : $current['returning_rows'], $next['returning_rows']),
            'current_skipped_conflicts' => $current['skipped_conflicts'],
            'next_skipped_conflicts' => $next['skipped_conflicts'],
            'trigger_effects' => array_merge($current['trigger_effects'], $next['trigger_effects']),
            'current_trigger_effects' => $current['trigger_effects'],
            'next_trigger_effects' => $next['trigger_effects'],
            'current_changes' => $rollbackCurrent ? 0 : $current['changes'],
            'attempted_current_changes' => $current['changes'],
            'next_changes' => $next['changes'],
            'committed_changes' => ($rollbackCurrent ? 0 : $current['changes']) + $next['changes'],
            'source_transition' => [
                'current' => $currentSource,
                'next' => $nextSource,
                'next_started_from' => $rollbackCurrent ? 'savepoint' : 'current-source',
                'conflict_action' => 'do-nothing',
                'returning_for_conflicts' => 'suppressed',
            ],
            'dependencies' => [
                'sqlite-trigger-upsert-do-nothing-returning-savepoint-current-source-next142',
                'sqlite-upsert-do-nothing-suppresses-returning-conflict-row',
                'sqlite-before-insert-trigger-fires-before-conflict-check',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $startRows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,string,int):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,skipped_conflicts:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int}
     */
    private static function runSource(array $startRows, array $incomingRows, array $uniqueColumns, array $triggers, array $returning, string $source): array
    {
        $rows = array_values($startRows);
        $returningRows = [];
        $skipped = [];
        $effects = [];
        $changes = 0;

        foreach (array_values($incomingRows) as $ordinal => $incoming) {
            $candidate = $incoming;
            self::fireTriggers($triggers, 'before', 'insert', null, $candidate, $source, $ordinal, $effects);
            $conflict = self::conflictIndex($rows, $candidate, $uniqueColumns);
            if ($conflict !== null) {
                $skipped[] = [
                    'source' => $source,
                    'ordinal' => $ordinal,
                    'reason' => 'unique-conflict-do-nothing',
                    'conflict_index' => $conflict,
                    'conflict_key' => self::key($candidate, $uniqueColumns),
                    'incoming' => $candidate,
                    'existing' => $rows[$conflict],
                ];
                continue;
            }

            $rows[] = $candidate;
            self::fireTriggers($triggers, 'after', 'insert', null, $candidate, $source, $ordinal, $effects);
            $returningRows[] = self::returningRow($returning, $candidate, null, 'insert', $ordinal);
            ++$changes;
        }

        return [
            'rows' => $rows,
            'returning_rows' => $returningRows,
            'skipped_conflicts' => $skipped,
            'trigger_effects' => $effects,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $triggers
     * @param list<array<string,mixed>> $effects
     */
    private static function fireTriggers(array $triggers, string $timing, string $event, ?array $old, array &$new, string $source, int $ordinal, array &$effects): void
    {
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? true, $old, $new)) {
                continue;
            }

            $action = strtolower((string) ($trigger['action'] ?? 'audit'));
            if ($action === 'set-new') {
                foreach ((array) ($trigger['set'] ?? []) as $column => $value) {
                    $new[self::identifier((string) $column, 'trigger set column')] = self::value($value, $old, $new);
                }
            } elseif ($action !== 'audit') {
                throw new \InvalidArgumentException('SQLite trigger UPSERT DO NOTHING RETURNING trigger action is unsupported');
            }

            $effects[] = [
                'trigger' => (string) ($trigger['name'] ?? ''),
                'timing' => $timing,
                'event' => $event,
                'action' => $action,
                'source' => $source,
                'ordinal' => $ordinal,
                'row' => self::project((array) ($trigger['values'] ?? []), $old, $new),
            ];
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     */
    private static function conflictIndex(array $rows, array $incoming, array $uniqueColumns): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($uniqueColumns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new \InvalidArgumentException("SQLite UPSERT DO NOTHING conflict column {$column} is missing");
                }
                if ($row[$column] === null || $incoming[$column] === null || $row[$column] != $incoming[$column]) {
                    continue 2;
                }
            }

            return (int) $index;
        }

        return null;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,string,int):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRow(array $returning, array $new, ?array $old, string $event, int $ordinal): array
    {
        $row = [];
        foreach ($returning as $index => $term) {
            if (is_callable($term)) {
                $row['expr' . $index] = $term($new, $old, $event, $ordinal);
                continue;
            }
            if (is_array($term)) {
                $expr = (string) ($term['expr'] ?? '');
                $alias = (string) ($term['as'] ?? $expr);
                $row[self::identifier($alias, 'RETURNING alias')] = self::returningValue($expr, $new, $old);
                continue;
            }
            if (!is_string($term) || $term === '') {
                throw new \InvalidArgumentException('SQLite UPSERT DO NOTHING RETURNING term is malformed');
            }
            $alias = str_contains($term, '.') ? substr($term, (int) strrpos($term, '.') + 1) : $term;
            $row[self::identifier($alias, 'RETURNING alias')] = self::returningValue($term, $new, $old);
        }

        return $row;
    }

    private static function returningValue(string $expr, array $new, ?array $old): mixed
    {
        $expr = trim($expr);
        if (str_starts_with($expr, 'new.')) {
            return self::rowValue($new, substr($expr, 4), 'NEW row');
        }
        if (str_starts_with($expr, 'old.')) {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite UPSERT DO NOTHING RETURNING OLD row is unavailable');
            }

            return self::rowValue($old, substr($expr, 4), 'OLD row');
        }

        return self::rowValue($new, $expr, 'RETURNING row');
    }

    private static function whenMatches(mixed $when, ?array $old, array $new): bool
    {
        if ($when === true || $when === null) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when) || count($when) !== 3) {
            throw new \InvalidArgumentException('SQLite UPSERT DO NOTHING trigger WHEN clause is malformed');
        }
        [$left, $operator, $right] = array_values($when);
        $left = self::value($left, $old, $new);
        $right = self::value($right, $old, $new);

        return match (strtoupper((string) $operator)) {
            '=', '==' => $left == $right,
            '!=', '<>' => $left != $right,
            'IS' => $left === $right,
            'IS NOT' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite UPSERT DO NOTHING trigger WHEN operator is unsupported'),
        };
    }

    private static function value(mixed $value, ?array $old, array $new): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        if (str_starts_with($value, 'new.')) {
            return self::rowValue($new, substr($value, 4), 'NEW row');
        }
        if (str_starts_with($value, 'old.')) {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite UPSERT DO NOTHING OLD row is unavailable');
            }

            return self::rowValue($old, substr($value, 4), 'OLD row');
        }
        if (str_starts_with($value, 'concat:')) {
            $parts = explode(':', substr($value, 7));
            $out = '';
            foreach ($parts as $part) {
                $out .= str_starts_with($part, 'new.') ? (string) self::rowValue($new, substr($part, 4), 'NEW row') : $part;
            }

            return $out;
        }

        return $value;
    }

    /**
     * @param array<string,string> $values
     * @return array<string,mixed>
     */
    private static function project(array $values, ?array $old, array $new): array
    {
        $row = [];
        foreach ($values as $alias => $expr) {
            $row[self::identifier((string) $alias, 'trigger value alias')] = self::value($expr, $old, $new);
        }

        return $row;
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private static function key(array $row, array $columns): array
    {
        $key = [];
        foreach ($columns as $column) {
            $key[$column] = self::rowValue($row, $column, 'conflict key');
        }

        return $key;
    }

    private static function rowValue(array $row, string $column, string $label): mixed
    {
        $column = self::identifier($column, $label . ' column');
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite UPSERT DO NOTHING {$label} column {$column} is missing");
        }

        return $row[$column];
    }

    /**
     * @param list<string> $columns
     */
    private static function validateColumns(array $columns, string $label): void
    {
        if ($columns === [] || !array_is_list($columns)) {
            throw new \InvalidArgumentException("SQLite UPSERT DO NOTHING {$label}s must be a non-empty list");
        }
        foreach ($columns as $column) {
            self::identifier($column, $label);
        }
    }

    private static function source(string $source): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $source) !== 1) {
            throw new \InvalidArgumentException('SQLite UPSERT DO NOTHING source token is malformed');
        }

        return $source;
    }

    private static function identifier(string $identifier, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException("SQLite UPSERT DO NOTHING {$label} is malformed");
        }

        return $identifier;
    }
}
