<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerPartialExpressionSkipScanCurrentSourceNext139Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<array{expression:string,column?:string,direction?:string}> $orderByExpressions
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        SQLiteIndexPredicate $preparedPredicate,
        SQLiteIndexPredicate $currentPredicate,
        array $queryTerms,
        array $orderByExpressions,
        array $neededColumns,
    ): array {
        $preparedView = SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScanCurrentSourceNext129(
            $preparedSource,
            $preparedSource,
            $preparedPredicate,
            $queryTerms,
            $orderByExpressions,
            $neededColumns,
        );
        $currentView = SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScanCurrentSourceNext129(
            $preparedSource,
            $currentSource,
            $currentPredicate,
            $queryTerms,
            $orderByExpressions,
            $neededColumns,
        );

        $preparedPlan = self::arrayValue($preparedView, 'selectedPlan');
        $currentPlan = self::arrayValue($currentView, 'selectedPlan');
        $preparedPredicateSignature = self::predicateSignature($preparedPredicate);
        $currentPredicateSignature = self::predicateSignature($currentPredicate);
        $predicateChanged = $preparedPredicateSignature !== $currentPredicateSignature;
        $preparedRowids = self::intList($preparedPlan['rowids'] ?? []);
        $currentRowids = self::intList($currentPlan['rowids'] ?? []);
        $currentRows = self::arrayList($currentSource['rows'] ?? []);
        $predicateDelta = self::predicateDelta($currentRows, $preparedPredicate, $currentPredicate, (string) ($currentSource['collation'] ?? 'BINARY'));
        $ready = ($currentView['status'] ?? null) === 'usable'
            && ($currentPlan['expressionSkipScan'] ?? false) === true
            && ($currentPlan['usesSkipScan'] ?? false) === true
            && ((bool) ($currentView['stalePreparedStatement'] ?? false) || $predicateChanged);

        return array_replace($currentView, [
            'status' => $ready ? 'partial-expression-skipscan-current-source-next139-ready' : 'requires-next-stage',
            'preparedPredicateSignature' => $preparedPredicateSignature,
            'currentPredicateSignature' => $currentPredicateSignature,
            'partialPredicateChanged' => $predicateChanged,
            'partialPredicateChangedOnly' => $predicateChanged && !((bool) ($currentView['schemaCookieChanged'] ?? false)) && !((bool) ($currentView['stat4GenerationChanged'] ?? false)),
            'preparedPartialTerms' => self::predicateTerms($preparedPredicate),
            'currentPartialTerms' => self::predicateTerms($currentPredicate),
            'preparedSkipScanRowids' => $preparedRowids,
            'currentSkipScanRowids' => $currentRowids,
            'stalePredicateRejectedRowids' => array_values(array_diff($preparedRowids, $currentRowids)),
            'currentPredicateAdmittedRowids' => array_values(array_diff($currentRowids, $preparedRowids)),
            'stablePredicateRowids' => array_values(array_intersect($currentRowids, $preparedRowids)),
            'currentRowsRejectedByPredicateChange' => $predicateDelta['rejected'],
            'currentRowsAdmittedByPredicateChange' => $predicateDelta['admitted'],
            'predicateRecheckRequired' => $predicateChanged,
            'predicateRecheckOpcode' => $predicateChanged ? 'IfNotPartialPredicate' : null,
            'currentSourceFence' => array_replace(
                self::arrayValue($currentView, 'currentSourceFence'),
                [
                    'partialPredicateSignature' => $currentPredicateSignature,
                    'partialPredicateChanged' => $predicateChanged,
                    'predicateRecheckOpcode' => $predicateChanged ? 'IfNotPartialPredicate' : null,
                    'skipScanRowCount' => count($currentRowids),
                ],
            ),
            'cursorTape' => self::cursorTape($currentPlan, $currentSource, $currentPredicateSignature, $predicateChanged),
            'detail' => ($currentView['detail'] ?? 'PARTIAL EXPRESSION SKIP-SCAN')
                . ' current-partial-predicate=' . ($predicateChanged ? 'changed' : 'stable'),
            'dependencies' => [
                'SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScanCurrentSourceNext129',
                'sqlite-sqlplanner-partial-expression-skipscan-current-source-next139',
            ],
            'dependency_closure' => 'no new support component needed; next139 reuses native PHP expression skip-scan materialization, partial predicate proof, and current-source fences',
            'non_overlap' => 'avoids next129 expression-key materialization, next132 expression covering, next137 STAT4 stale-source deltas, range-cost ranking, and SQL expression ORDER BY; this slice fences stale prepared partial expression skip-scan plans when the partial index predicate changes in the current schema',
        ]);
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function cursorTape(array $plan, array $source, string $predicateSignature, bool $predicateChanged): array
    {
        $program = [
            [
                'opcode' => 'ReprepareIfPartialPredicateStale',
                'source' => self::stringValue($source, 'name'),
                'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
                'partialPredicateSignature' => $predicateSignature,
            ],
            [
                'opcode' => 'SeekScan',
                'index' => (string) ($plan['indexName'] ?? ''),
                'skippedColumn' => (string) ($plan['skippedColumn'] ?? ''),
                'rangeExpression' => (string) ($plan['rangeExpression'] ?? ''),
                'loopCount' => count(self::arrayList($plan['loops'] ?? [])),
            ],
            [
                'opcode' => ((bool) ($plan['upperInclusive'] ?? true)) ? 'IdxGT' : 'IdxGE',
                'column' => (string) ($plan['rangeExpressionColumn'] ?? $plan['rangeColumn'] ?? ''),
                'upper' => $plan['upperBound'] ?? null,
            ],
        ];
        if ($predicateChanged) {
            $program[] = [
                'opcode' => 'IfNotPartialPredicate',
                'filteredRowids' => self::intList($plan['partialPredicateFilteredRowids'] ?? []),
            ];
        }
        $program[] = [
            'opcode' => 'Column',
            'source' => 'index',
            'columns' => self::stringList($plan['neededColumns'] ?? []),
        ];
        $program[] = [
            'opcode' => ($plan['reverseScan'] ?? false) === true ? 'Prev' : 'Next',
            'target' => 'index',
        ];

        return [
            'source' => 'current',
            'program' => $program,
            'rowids' => self::intList($plan['rowids'] ?? []),
            'estimatedRows' => (int) ($plan['estimatedRows'] ?? 0),
            'estimatedCost' => (int) ($plan['estimatedCost'] ?? 0),
            'predicateChanged' => $predicateChanged,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rejected:list<int>,admitted:list<int>}
     */
    private static function predicateDelta(array $rows, SQLiteIndexPredicate $prepared, SQLiteIndexPredicate $current, string $collation): array
    {
        $rejected = [];
        $admitted = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite partial expression skip-scan current rows must be arrays');
            }
            $rowid = self::rowid($row);
            $preparedMatch = self::predicateMatchesRow($prepared, $row, $collation);
            $currentMatch = self::predicateMatchesRow($current, $row, $collation);
            if ($preparedMatch && !$currentMatch) {
                $rejected[] = $rowid;
            }
            if (!$preparedMatch && $currentMatch) {
                $admitted[] = $rowid;
            }
        }

        return ['rejected' => $rejected, 'admitted' => $admitted];
    }

    /** @param array<string,mixed> $row */
    private static function predicateMatchesRow(SQLiteIndexPredicate $predicate, array $row, string $collation): bool
    {
        if ($predicate->operator === SQLiteIndexPredicate::AND) {
            if (!is_array($predicate->value) || $predicate->value === []) {
                return false;
            }
            foreach ($predicate->value as $child) {
                if (!$child instanceof SQLiteIndexPredicate || !self::predicateMatchesRow($child, $row, $collation)) {
                    return false;
                }
            }

            return true;
        }
        if ($predicate->operator === SQLiteIndexPredicate::OR) {
            if (!is_array($predicate->value)) {
                return false;
            }
            foreach ($predicate->value as $child) {
                if ($child instanceof SQLiteIndexPredicate && self::predicateMatchesRow($child, $row, $collation)) {
                    return true;
                }
            }

            return false;
        }

        $value = self::rowColumn($row, $predicate->columnName);

        return match ($predicate->operator) {
            SQLiteIndexPredicate::IS_NOT_NULL => $value !== null,
            SQLiteIndexPredicate::EQUALS => self::compare($value, $predicate->value, $collation) === 0,
            SQLiteIndexPredicate::NOT_EQUALS => self::compare($value, $predicate->value, $collation) !== 0,
            SQLiteIndexPredicate::LESS_THAN => ($comparison = self::compare($value, $predicate->value, $collation)) !== null && $comparison < 0,
            SQLiteIndexPredicate::LESS_THAN_OR_EQUAL => ($comparison = self::compare($value, $predicate->value, $collation)) !== null && $comparison <= 0,
            SQLiteIndexPredicate::GREATER_THAN => ($comparison = self::compare($value, $predicate->value, $collation)) !== null && $comparison > 0,
            SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL => ($comparison = self::compare($value, $predicate->value, $collation)) !== null && $comparison >= 0,
            SQLiteIndexPredicate::BETWEEN => is_array($predicate->value)
                && array_key_exists('lower', $predicate->value)
                && array_key_exists('upper', $predicate->value)
                && ($lower = self::compare($value, $predicate->value['lower'], $collation)) !== null
                && ($upper = self::compare($value, $predicate->value['upper'], $collation)) !== null
                && $lower >= 0
                && $upper <= 0,
            SQLiteIndexPredicate::IN_LIST => is_array($predicate->value) && self::inList($value, $predicate->value, $collation),
            default => false,
        };
    }

    /** @param list<mixed> $values */
    private static function inList(mixed $value, array $values, string $collation): bool
    {
        foreach ($values as $candidate) {
            if (self::compare($value, $candidate, $collation) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function compare(mixed $left, mixed $right, string $collation): ?int
    {
        if ($left === null || $right === null) {
            return null;
        }
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }
        if (is_string($left) && is_string($right)) {
            return match (strtoupper($collation)) {
                'NOCASE' => strcmp(strtolower($left), strtolower($right)),
                'RTRIM' => strcmp(rtrim($left, " \t\r\n"), rtrim($right, " \t\r\n")),
                default => strcmp($left, $right),
            };
        }

        return $left === $right ? 0 : null;
    }

    private static function predicateSignature(SQLiteIndexPredicate $predicate): string
    {
        return hash('sha256', serialize(self::predicateTerms($predicate)));
    }

    /**
     * @return array<string,mixed>
     */
    private static function predicateTerms(SQLiteIndexPredicate $predicate): array
    {
        $value = $predicate->value;
        if (is_array($value)) {
            $value = array_map(
                static fn (mixed $item): mixed => $item instanceof SQLiteIndexPredicate ? self::predicateTerms($item) : $item,
                $value,
            );
        }

        return [
            'column' => $predicate->columnName,
            'operator' => $predicate->operator,
            'value' => $value,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function rowColumn(array $row, string $column): mixed
    {
        foreach ($row as $key => $value) {
            if (is_string($key) && strcasecmp($key, $column) === 0) {
                return $value;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        $rowid = $row['rowid'] ?? $row['_rowid_'] ?? $row['oid'] ?? null;
        if (!is_int($rowid) || $rowid < 0) {
            throw new \InvalidArgumentException('SQLite partial expression skip-scan rows need non-negative integer rowids');
        }

        return $rowid;
    }

    /** @return array<string,mixed> */
    private static function arrayValue(array $source, string $key): array
    {
        $value = $source[$key] ?? [];
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite partial expression skip-scan current-source metadata must be arrays');
        }

        return $value;
    }

    /** @return list<array<string,mixed>> */
    private static function arrayList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /** @return list<int> */
    private static function intList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map('intval', $value));
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map('strval', $value));
    }

    private static function stringValue(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite partial expression skip-scan current-source needs non-empty string metadata');
        }

        return $value;
    }

    private static function nonNegativeInt(array $source, string $key): int
    {
        $value = $source[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite partial expression skip-scan current-source needs non-negative integer metadata');
        }

        return $value;
    }
}
