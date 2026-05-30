<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectRecursiveJsonMaterialization
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $indexColumns
     * @return array{sql:string,rows:list<array<string,mixed>>,indexColumns:list<string>,orderColumns:list<string>,indexes:array<string,list<int>>,keys:array<int,string>,currentNext:list<array{key:array<string,mixed>,current:array<string,mixed>,next:?array<string,mixed>,currentPosition:int,nextPosition:int|null}>,recursiveCurrentNext:list<array{iteration:int,current:array<string,mixed>,next:?array<string,mixed>,currentJsonRows:list<array<string,mixed>>,nextJsonRows:list<array<string,mixed>>,currentPosition:int,nextPosition:int|null,acceptedNext:list<array<string,mixed>>,skippedDuplicates:list<array<string,mixed>>,emitted:bool,generatedCount:int,acceptedNextCount:int,queueAfterCount:int}>,trace:array<string,mixed>,dependencies:list<string>}
     */
    public static function materialize(string $sql, array $tables, array $indexColumns, array $orderColumns = []): array
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite recursive JSON materialization needs WITH RECURSIVE SQL');
        }
        if (stripos($sql, 'json_') === false) {
            throw new \InvalidArgumentException('SQLite recursive JSON materialization needs a JSON table source');
        }

        $plan = SQLiteJsonTableDerivedIndex::materialize($sql, $tables, $indexColumns, $orderColumns);
        $trace = self::traceFirstRecursiveCte($sql, $tables);

        return [
            'sql' => $sql,
            'rows' => $plan['rows'],
            'indexColumns' => $plan['indexColumns'],
            'orderColumns' => $plan['orderColumns'],
            'indexes' => $plan['indexes'],
            'keys' => $plan['keys'],
            'currentNext' => SQLiteJsonTableDerivedIndex::adjacentPairs($plan),
            'recursiveCurrentNext' => self::recursiveCurrentNext($trace, $plan['rows']),
            'trace' => $trace,
            'dependencies' => [
                'sqlite-select-recursive-materialized-current-source',
                'sqlite-json-table-derived-current-next',
                'sqlite-recursive-current-next-materialization',
                'sqlite-recursive-json-table-yield',
                'sqlite-recursive-current-next-json-yield-boundary',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $criteria
     * @return list<array{key:array<string,mixed>,current:array<string,mixed>,next:?array<string,mixed>,currentPosition:int,nextPosition:int|null}>
     */
    public static function currentNextFor(array $plan, array $criteria): array
    {
        $indexPlan = [
            'rows' => $plan['rows'],
            'indexColumns' => $plan['indexColumns'],
            'indexes' => $plan['indexes'],
        ];

        return SQLiteJsonTableDerivedIndex::adjacentFor($indexPlan, $criteria);
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<string> $partitionColumns
     * @param list<string> $orderColumns
     * @return list<array{partition:array<string,mixed>,row:array<string,mixed>,previous:?array<string,mixed>,next:?array<string,mixed>,rowNumber:int,partitionSize:int,first:bool,last:bool,recursiveIteration:int|null}>
     */
    public static function jsonCurrentNextWindow(array $plan, array $partitionColumns, array $orderColumns): array
    {
        $rows = $plan['rows'] ?? null;
        if (!is_array($rows)) {
            throw new \InvalidArgumentException('SQLite recursive JSON current-next window needs materialized rows');
        }
        if ($partitionColumns === [] || $orderColumns === []) {
            throw new \InvalidArgumentException('SQLite recursive JSON current-next window needs partition and order columns');
        }

        $partitions = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite recursive JSON current-next window row is malformed');
            }
            self::assertColumns($row, $partitionColumns);
            self::assertColumns($row, $orderColumns);

            $key = self::partitionKey($row, $partitionColumns);
            $partitions[$key][] = $row;
        }

        ksort($partitions);
        $iterationMap = self::recursiveIterationMap($plan);
        $iterationOffsets = [];
        $windows = [];
        foreach ($partitions as $partitionRows) {
            usort(
                $partitionRows,
                static fn (array $left, array $right): int => self::compareRowsByColumns($left, $right, $orderColumns),
            );
            $size = count($partitionRows);
            foreach ($partitionRows as $position => $row) {
                $recursiveKey = self::recursiveRowKey($row);
                $iterationOffset = $iterationOffsets[$recursiveKey] ?? 0;
                $iterationOffsets[$recursiveKey] = $iterationOffset + 1;

                $windows[] = [
                    'partition' => self::projectColumns($row, $partitionColumns),
                    'row' => $row,
                    'previous' => $partitionRows[$position - 1] ?? null,
                    'next' => $partitionRows[$position + 1] ?? null,
                    'rowNumber' => $position + 1,
                    'partitionSize' => $size,
                    'first' => $position === 0,
                    'last' => $position === $size - 1,
                    'recursiveIteration' => $iterationMap[$recursiveKey][$iterationOffset] ?? null,
                ];
            }
        }

        return $windows;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<string> $identityColumns
     * @return list<array{iteration:int,current:array<string,mixed>,next:?array<string,mixed>,currentKey:array<string,mixed>,nextKey:?array<string,mixed>,currentJsonRows:list<array<string,mixed>>,nextJsonRows:list<array<string,mixed>>,currentJsonCount:int,nextJsonCount:int,acceptedNext:list<array<string,mixed>>,acceptedNextKeys:list<array<string,mixed>>,acceptedNextJsonRows:list<list<array<string,mixed>>>,acceptedNextJsonCounts:list<int>,skippedDuplicates:list<array<string,mixed>>,skippedDuplicateKeys:list<array<string,mixed>>,emitted:bool,status:string,queueAfterCount:int}>
     */
    public static function recursiveJsonCurrentNextFrontier(array $plan, array $identityColumns): array
    {
        $rows = $plan['rows'] ?? null;
        $pairs = $plan['recursiveCurrentNext'] ?? null;
        if (!is_array($rows) || !is_array($pairs)) {
            throw new \InvalidArgumentException('SQLite recursive JSON frontier needs a recursive materialization plan');
        }
        if ($identityColumns === []) {
            throw new \InvalidArgumentException('SQLite recursive JSON frontier needs identity columns');
        }

        $frontier = [];
        foreach ($pairs as $pair) {
            if (!is_array($pair) || !is_array($pair['current'] ?? null)) {
                throw new \InvalidArgumentException('SQLite recursive JSON frontier pair is malformed');
            }

            $current = $pair['current'];
            $next = $pair['next'] ?? null;
            if ($next !== null && !is_array($next)) {
                throw new \InvalidArgumentException('SQLite recursive JSON frontier next row is malformed');
            }

            self::assertColumns($current, $identityColumns);
            if ($next !== null) {
                self::assertColumns($next, $identityColumns);
            }

            $currentJsonRows = self::jsonRowsForRecursiveRow($rows, $current);
            $nextJsonRows = $next === null ? [] : self::jsonRowsForRecursiveRow($rows, $next);
            $acceptedNext = array_values(array_filter(
                $pair['acceptedNext'] ?? [],
                static fn (mixed $row): bool => is_array($row),
            ));
            $skippedDuplicates = array_values(array_filter(
                $pair['skippedDuplicates'] ?? [],
                static fn (mixed $row): bool => is_array($row),
            ));

            $acceptedNextJsonRows = [];
            foreach ($acceptedNext as $accepted) {
                self::assertColumns($accepted, $identityColumns);
                $acceptedNextJsonRows[] = self::jsonRowsForRecursiveRow($rows, $accepted);
            }

            foreach ($skippedDuplicates as $skipped) {
                self::assertColumns($skipped, $identityColumns);
            }

            $frontier[] = [
                'iteration' => (int) ($pair['iteration'] ?? count($frontier)),
                'current' => $current,
                'next' => $next,
                'currentKey' => self::projectColumns($current, $identityColumns),
                'nextKey' => $next === null ? null : self::projectColumns($next, $identityColumns),
                'currentJsonRows' => $currentJsonRows,
                'nextJsonRows' => $nextJsonRows,
                'currentJsonCount' => count($currentJsonRows),
                'nextJsonCount' => count($nextJsonRows),
                'acceptedNext' => $acceptedNext,
                'acceptedNextKeys' => array_map(static fn (array $row): array => self::projectColumns($row, $identityColumns), $acceptedNext),
                'acceptedNextJsonRows' => $acceptedNextJsonRows,
                'acceptedNextJsonCounts' => array_map(static fn (array $jsonRows): int => count($jsonRows), $acceptedNextJsonRows),
                'skippedDuplicates' => $skippedDuplicates,
                'skippedDuplicateKeys' => array_map(static fn (array $row): array => self::projectColumns($row, $identityColumns), $skippedDuplicates),
                'emitted' => (bool) ($pair['emitted'] ?? false),
                'status' => $next === null
                    ? 'terminal-current'
                    : (((bool) ($pair['emitted'] ?? false)) ? 'emitted-current' : 'queued-current'),
                'queueAfterCount' => (int) ($pair['queueAfterCount'] ?? 0),
            ];
        }

        return $frontier;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<string> $keyColumns
     * @param list<string> $jsonColumns
     * @return list<array{iteration:int,currentKey:array<string,mixed>,nextKey:?array<string,mixed>,currentJson:list<array<string,mixed>>,nextJson:list<array<string,mixed>>,acceptedNextKeys:list<array<string,mixed>>,skippedDuplicateKeys:list<array<string,mixed>>,transition:string,emitted:bool,generatedCount:int,acceptedNextCount:int,queueAfterCount:int}>
     */
    public static function recursiveJsonYieldTape(array $plan, array $keyColumns, array $jsonColumns): array
    {
        if ($keyColumns === [] || $jsonColumns === []) {
            throw new \InvalidArgumentException('SQLite recursive JSON yield tape needs key and JSON columns');
        }

        $pairs = $plan['recursiveCurrentNext'] ?? null;
        if (!is_array($pairs)) {
            throw new \InvalidArgumentException('SQLite recursive JSON yield tape needs recursive current-next pairs');
        }

        $tape = [];
        foreach ($pairs as $pair) {
            if (!is_array($pair) || !is_array($pair['current'] ?? null)) {
                throw new \InvalidArgumentException('SQLite recursive JSON yield tape pair is malformed');
            }

            $current = $pair['current'];
            self::assertColumns($current, $keyColumns);
            $next = $pair['next'] ?? null;
            if ($next !== null) {
                if (!is_array($next)) {
                    throw new \InvalidArgumentException('SQLite recursive JSON yield tape next row is malformed');
                }
                self::assertColumns($next, $keyColumns);
            }

            $currentJson = self::projectJsonRows($pair['currentJsonRows'] ?? [], $jsonColumns);
            $nextJson = self::projectJsonRows($pair['nextJsonRows'] ?? [], $jsonColumns);
            $acceptedNext = self::projectRecursiveRows($pair['acceptedNext'] ?? [], $keyColumns);
            $skipped = self::projectRecursiveRows($pair['skippedDuplicates'] ?? [], $keyColumns);

            $tape[] = [
                'iteration' => (int) ($pair['iteration'] ?? count($tape)),
                'currentKey' => self::projectColumns($current, $keyColumns),
                'nextKey' => $next === null ? null : self::projectColumns($next, $keyColumns),
                'currentJson' => $currentJson,
                'nextJson' => $nextJson,
                'acceptedNextKeys' => $acceptedNext,
                'skippedDuplicateKeys' => $skipped,
                'transition' => self::yieldTransition($currentJson, $nextJson, $acceptedNext, $skipped),
                'emitted' => (bool) ($pair['emitted'] ?? false),
                'generatedCount' => (int) ($pair['generatedCount'] ?? 0),
                'acceptedNextCount' => (int) ($pair['acceptedNextCount'] ?? 0),
                'queueAfterCount' => (int) ($pair['queueAfterCount'] ?? 0),
            ];
        }

        return $tape;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{name:string,columns:list<string>,operator:string,rows:list<array<string,mixed>>,trace:list<array<string,mixed>>,skipped:list<array<string,mixed>>,dependencies:list<string>}
     */
    private static function traceFirstRecursiveCte(string $sql, array $tables): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        $depth = 0;
        $quote = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($quote && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && strncasecmp(substr($sql, $i), 'SELECT', 6) === 0 && self::keywordBounded($sql, $i, 6)) {
                return SQLiteSelectSql::recursiveCteCycleTrace(substr($sql, 0, $i) . 'SELECT * FROM ' . self::firstCteName($sql), $tables);
            }
        }

        throw new \InvalidArgumentException('SQLite recursive JSON materialization needs trailing SELECT');
    }

    /**
     * @param array{name:string,columns:list<string>,rows:list<array<string,mixed>>,trace:list<array<string,mixed>>} $trace
     * @param list<array<string,mixed>> $jsonRows
     * @return list<array{iteration:int,current:array<string,mixed>,next:?array<string,mixed>,currentJsonRows:list<array<string,mixed>>,nextJsonRows:list<array<string,mixed>>,currentPosition:int,nextPosition:int|null,acceptedNext:list<array<string,mixed>>,skippedDuplicates:list<array<string,mixed>>,emitted:bool,generatedCount:int,acceptedNextCount:int,queueAfterCount:int}>
     */
    private static function recursiveCurrentNext(array $trace, array $jsonRows): array
    {
        $rows = array_values(array_filter(
            $trace['rows'],
            static fn (mixed $row): bool => is_array($row),
        ));
        $pairs = [];

        $entries = $trace['trace'];
        $count = count($entries);
        foreach ($entries as $position => $entry) {
            if (!is_array($entry['current'] ?? null)) {
                continue;
            }

            $current = $entry['current'];
            $nextEntry = $entries[$position + 1] ?? null;
            $next = is_array($nextEntry) && is_array($nextEntry['current'] ?? null)
                ? $nextEntry['current']
                : null;
            $generated = $entry['generated'] ?? [];
            $acceptedNext = array_values(array_filter(
                $entry['accepted_next'] ?? [],
                static fn (mixed $row): bool => is_array($row),
            ));
            $queueAfter = $entry['queue_after'] ?? [];

            $pairs[] = [
                'iteration' => (int) ($entry['iteration'] ?? count($pairs)),
                'current' => $current,
                'next' => $next,
                'currentJsonRows' => self::jsonRowsForRecursiveRow($jsonRows, $current),
                'nextJsonRows' => $next === null ? [] : self::jsonRowsForRecursiveRow($jsonRows, $next),
                'currentPosition' => $position,
                'nextPosition' => $position + 1 < $count ? $position + 1 : null,
                'acceptedNext' => $acceptedNext,
                'skippedDuplicates' => array_values(array_filter(
                    $entry['skipped_duplicates'] ?? [],
                    static fn (mixed $row): bool => is_array($row),
                )),
                'emitted' => (bool) ($entry['emitted'] ?? false),
                'generatedCount' => is_array($generated) ? count($generated) : 0,
                'acceptedNextCount' => count($acceptedNext),
                'queueAfterCount' => is_array($queueAfter) ? count($queueAfter) : 0,
            ];
        }

        return $pairs;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $wanted
     */
    private static function recursiveRowPosition(array $rows, array $wanted): ?int
    {
        foreach ($rows as $position => $row) {
            if (self::rowsShareValues($row, $wanted, array_keys($wanted))) {
                return $position;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $jsonRows
     * @param array<string,mixed> $recursiveRow
     * @return list<array<string,mixed>>
     */
    private static function jsonRowsForRecursiveRow(array $jsonRows, array $recursiveRow): array
    {
        $columns = array_values(array_filter(
            array_keys($recursiveRow),
            static fn (string $column): bool => array_key_exists($column, $jsonRows[0] ?? []),
        ));
        if ($columns === []) {
            return [];
        }

        return array_values(array_filter(
            $jsonRows,
            static fn (array $row): bool => self::rowsShareValues($row, $recursiveRow, $columns),
        ));
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     * @param list<string> $columns
     */
    private static function rowsShareValues(array $left, array $right, array $columns): bool
    {
        foreach ($columns as $column) {
            if (($left[$column] ?? null) !== ($right[$column] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function assertColumns(array $row, array $columns): void
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException('SQLite recursive JSON current-next window missing column ' . $column);
            }
        }
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function projectColumns(array $row, array $columns): array
    {
        $projected = [];
        foreach ($columns as $column) {
            $projected[$column] = $row[$column];
        }

        return $projected;
    }

    /**
     * @param mixed $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function projectJsonRows(mixed $rows, array $columns): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $projected = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            self::assertColumns($row, $columns);
            $projected[] = self::projectColumns($row, $columns);
        }

        return $projected;
    }

    /**
     * @param mixed $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function projectRecursiveRows(mixed $rows, array $columns): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $projected = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            self::assertColumns($row, $columns);
            $projected[] = self::projectColumns($row, $columns);
        }

        return $projected;
    }

    /**
     * @param list<array<string,mixed>> $currentJson
     * @param list<array<string,mixed>> $nextJson
     * @param list<array<string,mixed>> $acceptedNext
     * @param list<array<string,mixed>> $skipped
     */
    private static function yieldTransition(array $currentJson, array $nextJson, array $acceptedNext, array $skipped): string
    {
        if ($nextJson === []) {
            return $skipped === [] ? 'terminal' : 'terminal-with-duplicate-skip';
        }
        if ($currentJson === []) {
            return 'trace-only';
        }
        if ($acceptedNext === [] && $skipped !== []) {
            return 'duplicate-skip';
        }
        if ($acceptedNext !== [] && $skipped !== []) {
            return 'yield-and-skip';
        }

        return 'yield';
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function partitionKey(array $row, array $columns): string
    {
        return json_encode(self::projectColumns($row, $columns), JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     * @param list<string> $columns
     */
    private static function compareRowsByColumns(array $left, array $right, array $columns): int
    {
        foreach ($columns as $column) {
            $comparison = self::compareValues($left[$column], $right[$column]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }

    private static function compareValues(mixed $left, mixed $right): int
    {
        if ($left === $right) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    /**
     * @param array<string,mixed> $plan
     * @return array<string,list<int>>
     */
    private static function recursiveIterationMap(array $plan): array
    {
        $map = [];
        $pairs = $plan['recursiveCurrentNext'] ?? [];
        if (!is_array($pairs)) {
            return $map;
        }

        foreach ($pairs as $pair) {
            if (!is_array($pair)) {
                continue;
            }
            $iteration = $pair['iteration'] ?? null;
            $rows = $pair['currentJsonRows'] ?? [];
            if (!is_int($iteration) || !is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $map[self::recursiveRowKey($row)][] = $iteration;
                }
            }
        }

        return $map;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function recursiveRowKey(array $row): string
    {
        return json_encode($row, JSON_THROW_ON_ERROR);
    }

    private static function firstCteName(string $sql): string
    {
        if (preg_match('/^with\s+recursive\s+([A-Za-z_][A-Za-z0-9_]*)/i', trim($sql), $match) !== 1) {
            throw new \InvalidArgumentException('SQLite recursive JSON materialization CTE name is malformed');
        }

        return $match[1];
    }

    /**
     * @param array{name:string,columns:list<string>,operator:string,rows:list<array<string,mixed>>,trace:list<array<string,mixed>>,skipped:list<array<string,mixed>>,dependencies:list<string>} $trace
     * @return list<array{current:array<string,mixed>,next:?array<string,mixed>,currentPosition:int,nextPosition:int|null,emitted:bool,generatedCount:int,acceptedNextCount:int,queueAfterCount:int}>
     */
    private static function traceCurrentNext(array $trace): array
    {
        $entries = $trace['trace'];
        $pairs = [];
        $count = count($entries);
        for ($position = 0; $position < $count; $position++) {
            $entry = $entries[$position];
            $nextEntry = $entries[$position + 1] ?? null;
            $current = $entry['current'] ?? null;
            if (!is_array($current)) {
                throw new \InvalidArgumentException('SQLite recursive JSON trace current row is malformed');
            }

            $next = null;
            if (is_array($nextEntry)) {
                $next = $nextEntry['current'] ?? null;
                if (!is_array($next)) {
                    throw new \InvalidArgumentException('SQLite recursive JSON trace next row is malformed');
                }
            }

            $generated = $entry['generated'] ?? [];
            $acceptedNext = $entry['accepted_next'] ?? [];
            $queueAfter = $entry['queue_after'] ?? [];
            $pairs[] = [
                'current' => $current,
                'next' => $next,
                'currentPosition' => $position,
                'nextPosition' => $next === null ? null : $position + 1,
                'emitted' => (bool) ($entry['emitted'] ?? false),
                'generatedCount' => is_array($generated) ? count($generated) : 0,
                'acceptedNextCount' => is_array($acceptedNext) ? count($acceptedNext) : 0,
                'queueAfterCount' => is_array($queueAfter) ? count($queueAfter) : 0,
            ];
        }

        return $pairs;
    }

    private static function keywordBounded(string $sql, int $offset, int $length): bool
    {
        $before = $offset === 0 ? '' : $sql[$offset - 1];
        $after = $sql[$offset + $length] ?? '';

        return ($before === '' || !preg_match('/[A-Za-z0-9_]/', $before))
            && ($after === '' || !preg_match('/[A-Za-z0-9_]/', $after));
    }
}
