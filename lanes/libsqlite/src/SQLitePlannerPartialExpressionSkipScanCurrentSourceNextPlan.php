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
        public static function materializeNext139(
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

            $preparedPlan = self::arrayValueNext139($preparedView, 'selectedPlan');
            $currentPlan = self::arrayValueNext139($currentView, 'selectedPlan');
            $preparedPredicateSignature = self::predicateSignatureNext139($preparedPredicate);
            $currentPredicateSignature = self::predicateSignatureNext139($currentPredicate);
            $predicateChanged = $preparedPredicateSignature !== $currentPredicateSignature;
            $preparedRowids = self::intListNext139($preparedPlan['rowids'] ?? []);
            $currentRowids = self::intListNext139($currentPlan['rowids'] ?? []);
            $currentRows = self::arrayListNext139($currentSource['rows'] ?? []);
            $predicateDelta = self::predicateDeltaNext139($currentRows, $preparedPredicate, $currentPredicate, (string) ($currentSource['collation'] ?? 'BINARY'));
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
                'preparedPartialTerms' => self::predicateTermsNext139($preparedPredicate),
                'currentPartialTerms' => self::predicateTermsNext139($currentPredicate),
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
                    self::arrayValueNext139($currentView, 'currentSourceFence'),
                    [
                        'partialPredicateSignature' => $currentPredicateSignature,
                        'partialPredicateChanged' => $predicateChanged,
                        'predicateRecheckOpcode' => $predicateChanged ? 'IfNotPartialPredicate' : null,
                        'skipScanRowCount' => count($currentRowids),
                    ],
                ),
                'cursorTape' => self::cursorTapeNext139($currentPlan, $currentSource, $currentPredicateSignature, $predicateChanged),
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
        private static function cursorTapeNext139(array $plan, array $source, string $predicateSignature, bool $predicateChanged): array
        {
            $program = [
                [
                    'opcode' => 'ReprepareIfPartialPredicateStale',
                    'source' => self::stringValueNext139($source, 'name'),
                    'schemaCookie' => self::nonNegativeIntNext139($source, 'schemaCookie'),
                    'partialPredicateSignature' => $predicateSignature,
                ],
                [
                    'opcode' => 'SeekScan',
                    'index' => (string) ($plan['indexName'] ?? ''),
                    'skippedColumn' => (string) ($plan['skippedColumn'] ?? ''),
                    'rangeExpression' => (string) ($plan['rangeExpression'] ?? ''),
                    'loopCount' => count(self::arrayListNext139($plan['loops'] ?? [])),
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
                    'filteredRowids' => self::intListNext139($plan['partialPredicateFilteredRowids'] ?? []),
                ];
            }
            $program[] = [
                'opcode' => 'Column',
                'source' => 'index',
                'columns' => self::stringListNext139($plan['neededColumns'] ?? []),
            ];
            $program[] = [
                'opcode' => ($plan['reverseScan'] ?? false) === true ? 'Prev' : 'Next',
                'target' => 'index',
            ];

            return [
                'source' => 'current',
                'program' => $program,
                'rowids' => self::intListNext139($plan['rowids'] ?? []),
                'estimatedRows' => (int) ($plan['estimatedRows'] ?? 0),
                'estimatedCost' => (int) ($plan['estimatedCost'] ?? 0),
                'predicateChanged' => $predicateChanged,
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return array{rejected:list<int>,admitted:list<int>}
         */
        private static function predicateDeltaNext139(array $rows, SQLiteIndexPredicate $prepared, SQLiteIndexPredicate $current, string $collation): array
        {
            $rejected = [];
            $admitted = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite partial expression skip-scan current rows must be arrays');
                }
                $rowid = self::rowidNext139($row);
                $preparedMatch = self::predicateMatchesRowNext139($prepared, $row, $collation);
                $currentMatch = self::predicateMatchesRowNext139($current, $row, $collation);
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
        private static function predicateMatchesRowNext139(SQLiteIndexPredicate $predicate, array $row, string $collation): bool
        {
            if ($predicate->operator === SQLiteIndexPredicate::AND) {
                if (!is_array($predicate->value) || $predicate->value === []) {
                    return false;
                }
                foreach ($predicate->value as $child) {
                    if (!$child instanceof SQLiteIndexPredicate || !self::predicateMatchesRowNext139($child, $row, $collation)) {
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
                    if ($child instanceof SQLiteIndexPredicate && self::predicateMatchesRowNext139($child, $row, $collation)) {
                        return true;
                    }
                }

                return false;
            }

            $value = self::rowColumnNext139($row, $predicate->columnName);

            return match ($predicate->operator) {
                SQLiteIndexPredicate::IS_NOT_NULL => $value !== null,
                SQLiteIndexPredicate::EQUALS => self::compareNext139($value, $predicate->value, $collation) === 0,
                SQLiteIndexPredicate::NOT_EQUALS => self::compareNext139($value, $predicate->value, $collation) !== 0,
                SQLiteIndexPredicate::LESS_THAN => ($comparison = self::compareNext139($value, $predicate->value, $collation)) !== null && $comparison < 0,
                SQLiteIndexPredicate::LESS_THAN_OR_EQUAL => ($comparison = self::compareNext139($value, $predicate->value, $collation)) !== null && $comparison <= 0,
                SQLiteIndexPredicate::GREATER_THAN => ($comparison = self::compareNext139($value, $predicate->value, $collation)) !== null && $comparison > 0,
                SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL => ($comparison = self::compareNext139($value, $predicate->value, $collation)) !== null && $comparison >= 0,
                SQLiteIndexPredicate::BETWEEN => is_array($predicate->value)
                    && array_key_exists('lower', $predicate->value)
                    && array_key_exists('upper', $predicate->value)
                    && ($lower = self::compareNext139($value, $predicate->value['lower'], $collation)) !== null
                    && ($upper = self::compareNext139($value, $predicate->value['upper'], $collation)) !== null
                    && $lower >= 0
                    && $upper <= 0,
                SQLiteIndexPredicate::IN_LIST => is_array($predicate->value) && self::inListNext139($value, $predicate->value, $collation),
                default => false,
            };
        }

        /** @param list<mixed> $values */
        private static function inListNext139(mixed $value, array $values, string $collation): bool
        {
            foreach ($values as $candidate) {
                if (self::compareNext139($value, $candidate, $collation) === 0) {
                    return true;
                }
            }

            return false;
        }

        private static function compareNext139(mixed $left, mixed $right, string $collation): ?int
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

        private static function predicateSignatureNext139(SQLiteIndexPredicate $predicate): string
        {
            return hash('sha256', serialize(self::predicateTermsNext139($predicate)));
        }

        /**
         * @return array<string,mixed>
         */
        private static function predicateTermsNext139(SQLiteIndexPredicate $predicate): array
        {
            $value = $predicate->value;
            if (is_array($value)) {
                $value = array_map(
                    static fn (mixed $item): mixed => $item instanceof SQLiteIndexPredicate ? self::predicateTermsNext139($item) : $item,
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
        private static function rowColumnNext139(array $row, string $column): mixed
        {
            foreach ($row as $key => $value) {
                if (is_string($key) && strcasecmp($key, $column) === 0) {
                    return $value;
                }
            }

            return null;
        }

        /** @param array<string,mixed> $row */
        private static function rowidNext139(array $row): int
        {
            $rowid = $row['rowid'] ?? $row['_rowid_'] ?? $row['oid'] ?? null;
            if (!is_int($rowid) || $rowid < 0) {
                throw new \InvalidArgumentException('SQLite partial expression skip-scan rows need non-negative integer rowids');
            }

            return $rowid;
        }

        /** @return array<string,mixed> */
        private static function arrayValueNext139(array $source, string $key): array
        {
            $value = $source[$key] ?? [];
            if (!is_array($value)) {
                throw new \InvalidArgumentException('SQLite partial expression skip-scan current-source metadata must be arrays');
            }

            return $value;
        }

        /** @return list<array<string,mixed>> */
        private static function arrayListNext139(mixed $value): array
        {
            if (!is_array($value)) {
                return [];
            }

            return array_values(array_filter($value, 'is_array'));
        }

        /** @return list<int> */
        private static function intListNext139(mixed $value): array
        {
            if (!is_array($value)) {
                return [];
            }

            return array_values(array_map('intval', $value));
        }

        /** @return list<string> */
        private static function stringListNext139(mixed $value): array
        {
            if (!is_array($value)) {
                return [];
            }

            return array_values(array_map('strval', $value));
        }

        private static function stringValueNext139(array $source, string $key): string
        {
            $value = $source[$key] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException('SQLite partial expression skip-scan current-source needs non-empty string metadata');
            }

            return $value;
        }

        private static function nonNegativeIntNext139(array $source, string $key): int
        {
            $value = $source[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException('SQLite partial expression skip-scan current-source needs non-negative integer metadata');
            }

            return $value;
        }

}
