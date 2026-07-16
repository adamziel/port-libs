<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerPartialExpressionSkipScanCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerPartialExpressionSkipScanCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param list<array<string,mixed>> $queryTerms
         * @param list<array{expression:string,column?:string,direction?:string}> $orderByExpressions
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeCurrentPredicateFence(
            array $preparedSource,
            array $currentSource,
            SQLiteIndexPredicate $preparedPredicate,
            SQLiteIndexPredicate $currentPredicate,
            array $queryTerms,
            array $orderByExpressions,
            array $neededColumns,
        ): array {
            $preparedView = SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScan(
                $preparedSource,
                $preparedSource,
                $preparedPredicate,
                $queryTerms,
                $orderByExpressions,
                $neededColumns,
            );
            $currentView = SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScan(
                $preparedSource,
                $currentSource,
                $currentPredicate,
                $queryTerms,
                $orderByExpressions,
                $neededColumns,
            );

            $preparedPlan = self::arrayValueCurrentPredicateFence($preparedView, 'selectedPlan');
            $currentPlan = self::arrayValueCurrentPredicateFence($currentView, 'selectedPlan');
            $preparedPredicateSignature = self::predicateSignatureCurrentPredicateFence($preparedPredicate);
            $currentPredicateSignature = self::predicateSignatureCurrentPredicateFence($currentPredicate);
            $predicateChanged = $preparedPredicateSignature !== $currentPredicateSignature;
            $preparedRowids = self::intListCurrentPredicateFence($preparedPlan['rowids'] ?? []);
            $currentRowids = self::intListCurrentPredicateFence($currentPlan['rowids'] ?? []);
            $currentRows = self::arrayListCurrentPredicateFence($currentSource['rows'] ?? []);
            $predicateDelta = self::predicateDeltaCurrentPredicateFence($currentRows, $preparedPredicate, $currentPredicate, (string) ($currentSource['collation'] ?? 'BINARY'));
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
                'preparedPartialTerms' => self::predicateTermsCurrentPredicateFence($preparedPredicate),
                'currentPartialTerms' => self::predicateTermsCurrentPredicateFence($currentPredicate),
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
                    self::arrayValueCurrentPredicateFence($currentView, 'currentSourceFence'),
                    [
                        'partialPredicateSignature' => $currentPredicateSignature,
                        'partialPredicateChanged' => $predicateChanged,
                        'predicateRecheckOpcode' => $predicateChanged ? 'IfNotPartialPredicate' : null,
                        'skipScanRowCount' => count($currentRowids),
                    ],
                ),
                'cursorTape' => self::cursorTapeCurrentPredicateFence($currentPlan, $currentSource, $currentPredicateSignature, $predicateChanged),
                'detail' => ($currentView['detail'] ?? 'PARTIAL EXPRESSION SKIP-SCAN')
                    . ' current-partial-predicate=' . ($predicateChanged ? 'changed' : 'stable'),
                'dependencies' => [
                    'SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScan',
                    'sqlite-sqlplanner-partial-expression-skipscan-current-source-next139',
                ],
                'dependency_closure' => 'no new support component needed; next139 reuses native PHP expression skip-scan materialization, partial predicate proof, and current-source fences',
                'non_overlap' => 'avoids current-source expression-key materialization, current-source expression covering, next137 STAT4 stale-source deltas, range-cost ranking, and SQL expression ORDER BY; this slice fences stale prepared partial expression skip-scan plans when the partial index predicate changes in the current schema',
            ]);
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $source
         * @return array<string,mixed>
         */
        private static function cursorTapeCurrentPredicateFence(array $plan, array $source, string $predicateSignature, bool $predicateChanged): array
        {
            $program = [
                [
                    'opcode' => 'ReprepareIfPartialPredicateStale',
                    'source' => self::stringValueCurrentPredicateFence($source, 'name'),
                    'schemaCookie' => self::nonNegativeIntCurrentPredicateFence($source, 'schemaCookie'),
                    'partialPredicateSignature' => $predicateSignature,
                ],
                [
                    'opcode' => 'SeekScan',
                    'index' => (string) ($plan['indexName'] ?? ''),
                    'skippedColumn' => (string) ($plan['skippedColumn'] ?? ''),
                    'rangeExpression' => (string) ($plan['rangeExpression'] ?? ''),
                    'loopCount' => count(self::arrayListCurrentPredicateFence($plan['loops'] ?? [])),
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
                    'filteredRowids' => self::intListCurrentPredicateFence($plan['partialPredicateFilteredRowids'] ?? []),
                ];
            }
            $program[] = [
                'opcode' => 'Column',
                'source' => 'index',
                'columns' => self::stringListCurrentPredicateFence($plan['neededColumns'] ?? []),
            ];
            $program[] = [
                'opcode' => ($plan['reverseScan'] ?? false) === true ? 'Prev' : 'Next',
                'target' => 'index',
            ];

            return [
                'source' => 'current',
                'program' => $program,
                'rowids' => self::intListCurrentPredicateFence($plan['rowids'] ?? []),
                'estimatedRows' => (int) ($plan['estimatedRows'] ?? 0),
                'estimatedCost' => (int) ($plan['estimatedCost'] ?? 0),
                'predicateChanged' => $predicateChanged,
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return array{rejected:list<int>,admitted:list<int>}
         */
        private static function predicateDeltaCurrentPredicateFence(array $rows, SQLiteIndexPredicate $prepared, SQLiteIndexPredicate $current, string $collation): array
        {
            $rejected = [];
            $admitted = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite partial expression skip-scan current rows must be arrays');
                }
                $rowid = self::rowidCurrentPredicateFence($row);
                $preparedMatch = self::predicateMatchesRowCurrentPredicateFence($prepared, $row, $collation);
                $currentMatch = self::predicateMatchesRowCurrentPredicateFence($current, $row, $collation);
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
        private static function predicateMatchesRowCurrentPredicateFence(SQLiteIndexPredicate $predicate, array $row, string $collation): bool
        {
            if ($predicate->operator === SQLiteIndexPredicate::AND) {
                if (!is_array($predicate->value) || $predicate->value === []) {
                    return false;
                }
                foreach ($predicate->value as $child) {
                    if (!$child instanceof SQLiteIndexPredicate || !self::predicateMatchesRowCurrentPredicateFence($child, $row, $collation)) {
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
                    if ($child instanceof SQLiteIndexPredicate && self::predicateMatchesRowCurrentPredicateFence($child, $row, $collation)) {
                        return true;
                    }
                }

                return false;
            }

            $value = self::rowColumnCurrentPredicateFence($row, $predicate->columnName);

            return match ($predicate->operator) {
                SQLiteIndexPredicate::IS_NOT_NULL => $value !== null,
                SQLiteIndexPredicate::EQUALS => self::compareCurrentPredicateFence($value, $predicate->value, $collation) === 0,
                SQLiteIndexPredicate::NOT_EQUALS => self::compareCurrentPredicateFence($value, $predicate->value, $collation) !== 0,
                SQLiteIndexPredicate::LESS_THAN => ($comparison = self::compareCurrentPredicateFence($value, $predicate->value, $collation)) !== null && $comparison < 0,
                SQLiteIndexPredicate::LESS_THAN_OR_EQUAL => ($comparison = self::compareCurrentPredicateFence($value, $predicate->value, $collation)) !== null && $comparison <= 0,
                SQLiteIndexPredicate::GREATER_THAN => ($comparison = self::compareCurrentPredicateFence($value, $predicate->value, $collation)) !== null && $comparison > 0,
                SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL => ($comparison = self::compareCurrentPredicateFence($value, $predicate->value, $collation)) !== null && $comparison >= 0,
                SQLiteIndexPredicate::BETWEEN => is_array($predicate->value)
                    && array_key_exists('lower', $predicate->value)
                    && array_key_exists('upper', $predicate->value)
                    && ($lower = self::compareCurrentPredicateFence($value, $predicate->value['lower'], $collation)) !== null
                    && ($upper = self::compareCurrentPredicateFence($value, $predicate->value['upper'], $collation)) !== null
                    && $lower >= 0
                    && $upper <= 0,
                SQLiteIndexPredicate::IN_LIST => is_array($predicate->value) && self::inListCurrentPredicateFence($value, $predicate->value, $collation),
                default => false,
            };
        }

        /** @param list<mixed> $values */
        private static function inListCurrentPredicateFence(mixed $value, array $values, string $collation): bool
        {
            foreach ($values as $candidate) {
                if (self::compareCurrentPredicateFence($value, $candidate, $collation) === 0) {
                    return true;
                }
            }

            return false;
        }

        private static function compareCurrentPredicateFence(mixed $left, mixed $right, string $collation): ?int
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

        private static function predicateSignatureCurrentPredicateFence(SQLiteIndexPredicate $predicate): string
        {
            return hash('sha256', serialize(self::predicateTermsCurrentPredicateFence($predicate)));
        }

        /**
         * @return array<string,mixed>
         */
        private static function predicateTermsCurrentPredicateFence(SQLiteIndexPredicate $predicate): array
        {
            $value = $predicate->value;
            if (is_array($value)) {
                $value = array_map(
                    static fn (mixed $item): mixed => $item instanceof SQLiteIndexPredicate ? self::predicateTermsCurrentPredicateFence($item) : $item,
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
        private static function rowColumnCurrentPredicateFence(array $row, string $column): mixed
        {
            foreach ($row as $key => $value) {
                if (is_string($key) && strcasecmp($key, $column) === 0) {
                    return $value;
                }
            }

            return null;
        }

        /** @param array<string,mixed> $row */
        private static function rowidCurrentPredicateFence(array $row): int
        {
            $rowid = $row['rowid'] ?? $row['_rowid_'] ?? $row['oid'] ?? null;
            if (!is_int($rowid) || $rowid < 0) {
                throw new \InvalidArgumentException('SQLite partial expression skip-scan rows need non-negative integer rowids');
            }

            return $rowid;
        }

        /** @return array<string,mixed> */
        private static function arrayValueCurrentPredicateFence(array $source, string $key): array
        {
            $value = $source[$key] ?? [];
            if (!is_array($value)) {
                throw new \InvalidArgumentException('SQLite partial expression skip-scan current-source metadata must be arrays');
            }

            return $value;
        }

        /** @return list<array<string,mixed>> */
        private static function arrayListCurrentPredicateFence(mixed $value): array
        {
            if (!is_array($value)) {
                return [];
            }

            return array_values(array_filter($value, 'is_array'));
        }

        /** @return list<int> */
        private static function intListCurrentPredicateFence(mixed $value): array
        {
            if (!is_array($value)) {
                return [];
            }

            return array_values(array_map('intval', $value));
        }

        /** @return list<string> */
        private static function stringListCurrentPredicateFence(mixed $value): array
        {
            if (!is_array($value)) {
                return [];
            }

            return array_values(array_map('strval', $value));
        }

        private static function stringValueCurrentPredicateFence(array $source, string $key): string
        {
            $value = $source[$key] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException('SQLite partial expression skip-scan current-source needs non-empty string metadata');
            }

            return $value;
        }

        private static function nonNegativeIntCurrentPredicateFence(array $source, string $key): int
        {
            $value = $source[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException('SQLite partial expression skip-scan current-source needs non-negative integer metadata');
            }

            return $value;
        }

}
