<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext156Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $predicate,
        array $orderBy,
        array $neededColumns
    ): array {
        self::validateNeededColumns($neededColumns);

        $preparedPlan = self::sourcePlan($preparedSource, $predicate, $orderBy, $neededColumns);
        $currentPlan = self::sourcePlan($currentSource, $predicate, $orderBy, $neededColumns);
        $preparedSignature = self::sourceSignature($preparedSource);
        $currentSignature = self::sourceSignature($currentSource);
        $preparedCookie = self::sourceInt($preparedSource, 'schemaCookie');
        $currentCookie = self::sourceInt($currentSource, 'schemaCookie');
        $preparedStat4 = self::sourceInt($preparedSource, 'stat4Generation');
        $currentStat4 = self::sourceInt($currentSource, 'stat4Generation');
        $stale = $preparedCookie !== $currentCookie
            || $preparedStat4 !== $currentStat4
            || $preparedSignature !== $currentSignature;
        $selectedPlan = $stale ? $currentPlan : $preparedPlan;
        $selectedSource = $stale ? $currentSource : $preparedSource;
        $rows = self::rowStream($selectedSource, $selectedPlan, $predicate, $neededColumns);
        $ready = $selectedPlan !== null
            && ($selectedPlan['partial'] ?? false) === true
            && ($selectedPlan['stat4Used'] ?? false) === true
            && $rows !== [];

        return [
            'status' => $ready ? 'stat4-expression-partial-current-source-next156-ready' : 'requires-next-stage',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => $preparedCookie !== $currentCookie,
            'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
            'indexSignatureChanged' => $preparedSignature !== $currentSignature,
            'preparedSource' => self::sourceSummary($preparedSource, $preparedPlan, $preparedSignature),
            'currentSource' => self::sourceSummary($currentSource, $currentPlan, $currentSignature),
            'selectedPlan' => ($selectedPlan ?? ['usable' => false]) + [
                'partialRowCount' => count($rows),
                'partialRowids' => array_column($rows, 'rowid'),
                'tableLookupRequired' => true,
            ],
            'partialRows' => $rows,
            'currentNextRows' => self::currentNextRows($rows),
            'cursorTape' => self::cursorTape($selectedPlan, $selectedSource, $rows, $orderBy, $neededColumns, $stale ? 'current' : 'prepared'),
            'currentSourceFence' => [
                'schemaCookie' => self::sourceInt($selectedSource, 'schemaCookie'),
                'stat4Generation' => self::sourceInt($selectedSource, 'stat4Generation'),
                'indexSignature' => $stale ? $currentSignature : $preparedSignature,
                'orderSignature' => self::orderSignature($orderBy),
                'rowStreamSignature' => hash('sha256', json_encode(array_column($rows, 'rowid'), JSON_THROW_ON_ERROR)),
            ],
            'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT156 '
                . (string) ($selectedPlan['name'] ?? 'NO INDEX')
                . ($ready ? ' WITH DEFERRED TABLE LOOKUP' : ' FALLBACK TABLE SCAN'),
            'dependencies' => [
                'SQLiteSelectExpressionIndexPlan::chooseLowestCost',
                'sqlite-sqlplanner-stat4-expression-partial-current-source-next156',
            ],
            'dependency_closure' => 'no new support component needed; next156 reuses native expression-index parsing, partial predicate proof, STAT4 estimates, and bounded table row materialization',
            'non_overlap' => 'avoids accepted STAT4 expression covering current-source, partial collation STAT4, expression partial covering, expression ORDER BY, range-cost, JSON, WAL/VFS, and B-tree clusters; this slice covers non-covering partial expression STAT4 current-source selection with deferred table lookup',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>|null
     */
    private static function sourcePlan(array $source, array $predicate, array $orderBy, array $neededColumns): ?array
    {
        return SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost(
            self::list($source, 'indexes'),
            $predicate,
            $orderBy,
            $neededColumns,
        ) ?? SQLiteSelectExpressionIndexPlan::chooseLowestCost(
            self::list($source, 'indexes'),
            $predicate,
            $orderBy,
            $neededColumns,
        );
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed>|null $plan
     * @param array<string,mixed> $predicate
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function rowStream(array $source, ?array $plan, array $predicate, array $neededColumns): array
    {
        if ($plan === null || ($plan['partial'] ?? false) !== true || ($plan['stat4Used'] ?? false) !== true) {
            return [];
        }

        $rows = [];
        foreach (self::list($source, 'rows') as $offset => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source rows must be arrays');
            }
            if (!self::rowMatches($row, $predicate)) {
                continue;
            }

            $rows[] = [
                'sourceOffset' => $offset,
                'rowid' => $row['rowid'] ?? $row['_rowid_'] ?? null,
                'expressionKey' => self::expressionValue((string) ($plan['type'] ?? ''), (string) ($plan['column'] ?? ''), $row),
                'payload' => self::payload($row, $neededColumns),
            ];
        }

        usort($rows, static function (array $left, array $right) use ($plan): int {
            $comparison = self::compare($left['expressionKey'] ?? null, $right['expressionKey'] ?? null);
            if (($plan['descending'] ?? false) === true) {
                $comparison *= -1;
            }
            if ($comparison !== 0) {
                return $comparison;
            }

            return ((int) ($left['rowid'] ?? $left['sourceOffset'])) <=> ((int) ($right['rowid'] ?? $right['sourceOffset']));
        });

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{current:array<string,mixed>,next:?array<string,mixed>}>
     */
    private static function currentNextRows(array $rows): array
    {
        $pairs = [];
        foreach ($rows as $offset => $row) {
            $pairs[] = ['current' => $row, 'next' => $rows[$offset + 1] ?? null];
        }

        return $pairs;
    }

    /**
     * @param array<string,mixed>|null $plan
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function cursorTape(?array $plan, array $source, array $rows, array $orderBy, array $neededColumns, string $sourceName): array
    {
        if ($plan === null || $rows === []) {
            return [
                'source' => $sourceName,
                'status' => 'no-partial-stat4-plan',
                'program' => [['opcode' => 'Rewind', 'target' => 'table']],
                'deferredSeekOpcode' => 'DeferredSeek',
                'sorterOpen' => $orderBy !== [],
            ];
        }

        $program = [
            ['opcode' => 'OpenRead', 'target' => 'index', 'rootPage' => $plan['rootPage'] ?? null, 'source' => $sourceName],
            ['opcode' => 'SeekStat4', 'target' => 'index', 'operator' => $plan['operator'] ?? null, 'values' => $plan['values'] ?? null],
            ['opcode' => 'DeferredSeek', 'target' => 'table', 'reason' => 'non-covering partial expression index'],
        ];
        foreach ($neededColumns as $column) {
            $program[] = ['opcode' => 'Column', 'source' => 'table', 'column' => $column];
        }
        $program[] = ['opcode' => 'ResultRow', 'rowCount' => count($rows)];
        $program[] = ['opcode' => 'Next', 'target' => 'index'];

        return [
            'source' => $sourceName,
            'status' => 'partial-stat4-current-source',
            'indexName' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'schemaCookie' => self::sourceInt($source, 'schemaCookie'),
            'stat4Generation' => self::sourceInt($source, 'stat4Generation'),
            'orderSignature' => self::orderSignature($orderBy),
            'expressionKeys' => array_column($rows, 'expressionKey'),
            'rowids' => array_column($rows, 'rowid'),
            'neededColumns' => $neededColumns,
            'stat4MatchedSamples' => $plan['stat4MatchedSamples'] ?? 0,
            'stat4Estimate' => $plan['stat4Estimate'] ?? null,
            'stat4RangeCurrentNext' => $plan['stat4RangeCurrentNext'] ?? null,
            'tableLookupElided' => false,
            'deferredSeekOpcode' => 'DeferredSeek',
            'sorterOpen' => ($plan['orderBySatisfied'] ?? false) !== true,
            'program' => $program,
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed>|null $plan
     * @return array<string,mixed>
     */
    private static function sourceSummary(array $source, ?array $plan, string $signature): array
    {
        return [
            'name' => self::string($source, 'name'),
            'schemaCookie' => self::sourceInt($source, 'schemaCookie'),
            'stat4Generation' => self::sourceInt($source, 'stat4Generation'),
            'indexSignature' => $signature,
            'indexName' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'partial' => $plan['partial'] ?? false,
            'stat4Used' => $plan['stat4Used'] ?? false,
            'estimatedRows' => $plan['estimatedRows'] ?? null,
            'ready' => $plan !== null && ($plan['partial'] ?? false) === true && ($plan['stat4Used'] ?? false) === true,
        ];
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceSignature(array $source): string
    {
        $indexes = [];
        foreach (self::list($source, 'indexes') as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source indexes must be arrays');
            }
            $indexes[] = [
                'name' => $index['name'] ?? null,
                'rootPage' => $index['rootPage'] ?? null,
                'sql' => $index['sql'] ?? null,
                'stat4Samples' => $index['stat4Samples'] ?? [],
                'estimatedRows' => $index['estimatedRows'] ?? null,
                'coveringColumns' => $index['coveringColumns'] ?? [],
            ];
        }

        return hash('sha256', serialize($indexes));
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function rowMatches(array $row, array $predicate): bool
    {
        $operator = strtoupper((string) ($predicate['operator'] ?? ''));
        if ($operator === 'AND') {
            $terms = $predicate['terms'] ?? null;
            if (!is_array($terms) || !array_is_list($terms)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source AND predicate needs list terms');
            }
            foreach ($terms as $term) {
                if (!is_array($term) || !self::rowMatches($row, $term)) {
                    return false;
                }
            }

            return true;
        }

        $left = $predicate['left'] ?? null;
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source predicate needs left expression');
        }
        $value = isset($left['function'], $left['column'])
            ? self::expressionValue((string) $left['function'], (string) $left['column'], $row)
            : ($row[(string) ($left['column'] ?? '')] ?? null);

        return match ($operator) {
            '=' => self::compare($value, $predicate['right'] ?? null) === 0,
            '>' => self::compare($value, $predicate['right'] ?? null) > 0,
            '>=' => self::compare($value, $predicate['right'] ?? null) >= 0,
            '<' => self::compare($value, $predicate['right'] ?? null) < 0,
            '<=' => self::compare($value, $predicate['right'] ?? null) <= 0,
            'BETWEEN' => self::compare($value, $predicate['lower'] ?? null) >= 0
                && self::compare($value, $predicate['upper'] ?? null) <= 0,
            'IN' => in_array($value, $predicate['values'] ?? [], true),
            'IS NOT NULL' => $value !== null,
            default => throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source unsupported predicate operator ' . $operator),
        };
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function expressionValue(string $function, string $column, array $row): mixed
    {
        $value = $row[$column] ?? null;
        return match (strtolower($function)) {
            'lower' => is_string($value) ? strtolower($value) : $value,
            'upper' => is_string($value) ? strtoupper($value) : $value,
            'length' => is_string($value) ? strlen($value) : (is_scalar($value) ? strlen((string) $value) : null),
            default => $value,
        };
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function payload(array $row, array $columns): array
    {
        $payload = [];
        foreach ($columns as $column) {
            $payload[$column] = $row[$column] ?? null;
        }

        return $payload;
    }

    private static function compare(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    /**
     * @param list<array<string,string>> $orderBy
     */
    private static function orderSignature(array $orderBy): string
    {
        if ($orderBy === []) {
            return 'rowid ASC';
        }

        return implode(', ', array_map(static function (array $term): string {
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source order direction must be ASC or DESC');
            }
            if (isset($term['function'], $term['column'])) {
                return strtolower($term['function']) . '(' . $term['column'] . ') ' . $direction;
            }

            return (string) ($term['column'] ?? '') . ' ' . $direction;
        }, $orderBy));
    }

    /**
     * @param array<string,mixed> $source
     * @return list<mixed>
     */
    private static function list(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source needs list key ' . $key);
        }

        return $value;
    }

    /**
     * @param list<string> $columns
     */
    private static function validateNeededColumns(array $columns): void
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source needs at least one output column');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source output columns must be non-empty strings');
            }
        }
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceInt(array $source, string $key): int
    {
        $value = $source[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source needs non-negative integer ' . $key);
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function string(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source needs string ' . $key);
        }

        return $value;
    }
}
