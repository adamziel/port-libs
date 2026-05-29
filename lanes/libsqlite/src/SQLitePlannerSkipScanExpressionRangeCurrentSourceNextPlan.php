<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerSkipScanExpressionRangeCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerSkipScanExpressionRangeCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param list<array<string,mixed>> $queryTerms
         * @param list<array{expression:string,column?:string,direction?:string}> $orderByExpressions
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeNext149(
            array $preparedSource,
            array $currentSource,
            SQLiteIndexPredicate $partialPredicate,
            array $queryTerms,
            array $orderByExpressions,
            array $neededColumns,
        ): array {
            $base = SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan::materializeNext143(
                $preparedSource,
                $currentSource,
                $partialPredicate,
                $queryTerms,
                $orderByExpressions,
                $neededColumns,
            );

            $selectedPlan = self::arrayValueNext149($base, 'selectedPlan');
            $currentRows = self::rowsByRowidNext149($currentSource);
            $rangeAudit = self::rangeAuditNext149($selectedPlan, $currentSource, $currentRows);
            $residualProgram = self::residualProgramNext149($selectedPlan, $currentSource, $rangeAudit);
            $ready = ($base['status'] ?? null) === 'expression-skipscan-range-current-source-next143-ready'
                && ($selectedPlan['expressionSkipScan'] ?? false) === true
                && ($selectedPlan['usesSkipScan'] ?? false) === true
                && $rangeAudit['rejectedRowids'] === [];

            return array_replace($base, [
                'status' => $ready ? 'skipscan-expression-range-current-source-next149-ready' : 'requires-current-source-range-recheck',
                'selectedPlan' => array_replace($selectedPlan, [
                    'currentSourceExpressionRangeRecheck' => true,
                    'expressionRangeRecheckOpcode' => 'RecheckExpressionRange',
                    'expressionRangeRowCount' => count($rangeAudit['acceptedRowids']),
                    'expressionRangeRejectedCount' => count($rangeAudit['rejectedRowids']),
                    'detail' => ($selectedPlan['detail'] ?? 'SEARCH USING SKIP-SCAN')
                        . ' CURRENT-SOURCE EXPRESSION RANGE RECHECK next149',
                ]),
                'expressionRangeAudit' => $rangeAudit,
                'expressionRangeResidualProgram' => $residualProgram,
                'currentSourceFence' => array_replace(
                    self::arrayValueNext149($base, 'currentSourceFence'),
                    [
                        'expressionRangeAuditSignature' => self::signatureNext149($rangeAudit),
                        'expressionRangeRecheckOpcode' => 'RecheckExpressionRange',
                        'acceptedExpressionRangeRows' => count($rangeAudit['acceptedRowids']),
                        'rejectedExpressionRangeRows' => count($rangeAudit['rejectedRowids']),
                    ],
                ),
                'cursorTape' => self::cursorTapeNext149($base, $residualProgram, $rangeAudit),
                'detail' => ($base['detail'] ?? 'PARTIAL EXPRESSION SKIP-SCAN')
                    . ' current-source-expression-range-recheck=' . ($rangeAudit['rejectedRowids'] === [] ? 'clean' : 'filtered'),
                'dependencies' => [
                    'SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan',
                    'sqlite-sqlplanner-skipscan-expression-range-current-source-next149',
                ],
                'dependency_closure' => 'no new support component needed; next149 reuses native PHP expression skip-scan range fences and adds current-source residual expression-range rechecks',
                'non_overlap' => 'avoids accepted next143 range-fence selection, next145 STAT4 prefix programs, expression-index range cost, SQL expression ORDER BY, and partial predicate change surfaces; this slice verifies selected skip-scan row expression values against current-source lower/upper range bounds before cursor yield',
            ]);
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $source
         * @param array<int,array<string,mixed>> $rowsByRowid
         * @return array<string,mixed>
         */
        private static function rangeAuditNext149(array $plan, array $source, array $rowsByRowid): array
        {
            $expression = self::stringValueNext149($source, 'rangeExpression');
            $column = self::stringValueNext149($source, 'rangeColumn');
            $rangeColumn = self::stringValueNext149($source, 'rangeExpressionColumn');
            $lower = $source['lowerInclusive'] ?? null;
            $upper = $source['upperBound'] ?? null;
            $upperInclusive = self::boolValueNext149($source, 'upperInclusive', true);
            $collation = strtoupper((string) ($source['collation'] ?? 'BINARY'));
            $accepted = [];
            $rejected = [];
            $tape = [];

            foreach (self::intListNext149($plan['rowids'] ?? []) as $rowid) {
                $row = $rowsByRowid[$rowid] ?? null;
                if ($row === null) {
                    $rejected[] = $rowid;
                    $tape[] = self::auditRowNext149($rowid, null, null, $lower, $upper, false, 'missing-current-row');
                    continue;
                }
                $value = self::expressionValueNext149($expression, $column, $rangeColumn, $row);
                $lowerOk = $lower === null || (($lowerCmp = self::compareNext149($value, $lower, $collation)) !== null && $lowerCmp >= 0);
                $upperCmp = $upper === null ? null : self::compareNext149($value, $upper, $collation);
                $upperOk = $upper === null || ($upperCmp !== null && ($upperInclusive ? $upperCmp <= 0 : $upperCmp < 0));
                $matched = $lowerOk && $upperOk;
                if ($matched) {
                    $accepted[] = $rowid;
                } else {
                    $rejected[] = $rowid;
                }
                $tape[] = self::auditRowNext149($rowid, $row, $value, $lower, $upper, $matched, $matched ? 'accepted' : 'range-filtered');
            }

            return [
                'expression' => $expression,
                'rangeColumn' => $rangeColumn,
                'sourceColumn' => $column,
                'lowerInclusive' => $lower,
                'upperBound' => $upper,
                'upperInclusive' => $upperInclusive,
                'collation' => $collation,
                'acceptedRowids' => $accepted,
                'rejectedRowids' => $rejected,
                'auditTape' => $tape,
            ];
        }

        /**
         * @param array<string,mixed>|null $row
         * @return array<string,mixed>
         */
        private static function auditRowNext149(int $rowid, ?array $row, mixed $value, mixed $lower, mixed $upper, bool $matched, string $reason): array
        {
            return [
                'rowid' => $rowid,
                'option_name' => $row['option_name'] ?? null,
                'expressionValue' => $value,
                'lower' => $lower,
                'upper' => $upper,
                'matched' => $matched,
                'reason' => $reason,
            ];
        }

        /** @param array<string,mixed> $plan @param array<string,mixed> $source @param array<string,mixed> $audit @return list<array<string,mixed>> */
        private static function residualProgramNext149(array $plan, array $source, array $audit): array
        {
            return [
                [
                    'opcode' => 'Column',
                    'source' => 'index',
                    'column' => self::stringValueNext149($source, 'rangeExpressionColumn'),
                ],
                [
                    'opcode' => 'RecheckExpressionRange',
                    'expression' => self::stringValueNext149($source, 'rangeExpression'),
                    'lower' => $source['lowerInclusive'] ?? null,
                    'upper' => $source['upperBound'] ?? null,
                    'upperInclusive' => self::boolValueNext149($source, 'upperInclusive', true),
                    'collation' => strtoupper((string) ($source['collation'] ?? 'BINARY')),
                ],
                [
                    'opcode' => 'IfNot',
                    'target' => ($plan['reverseScan'] ?? false) === true ? 'Prev' : 'Next',
                    'filteredRowids' => self::intListNext149($audit['rejectedRowids'] ?? []),
                ],
                [
                    'opcode' => 'ResultRow',
                    'rowids' => self::intListNext149($audit['acceptedRowids'] ?? []),
                ],
            ];
        }

        /** @param array<string,mixed> $base @param list<array<string,mixed>> $program @param array<string,mixed> $audit @return array<string,mixed> */
        private static function cursorTapeNext149(array $base, array $program, array $audit): array
        {
            $tape = self::arrayValueNext149($base, 'cursorTape');
            $existing = self::arrayListNext149($tape['program'] ?? []);
            array_splice($existing, max(0, count($existing) - 1), 0, $program);

            return array_replace($tape, [
                'program' => $existing,
                'rowids' => self::intListNext149($audit['acceptedRowids'] ?? []),
                'rejectedRowids' => self::intListNext149($audit['rejectedRowids'] ?? []),
                'residualRecheck' => true,
            ]);
        }

        /** @param array<string,mixed> $source @return array<int,array<string,mixed>> */
        private static function rowsByRowidNext149(array $source): array
        {
            $rows = [];
            foreach (self::arrayListNext149($source['rows'] ?? []) as $row) {
                $rowid = $row['rowid'] ?? $row['_rowid_'] ?? $row['oid'] ?? null;
                if (!is_int($rowid) || $rowid < 0) {
                    throw new \InvalidArgumentException('SQLite skip-scan expression range next149 rows need non-negative integer rowids');
                }
                $rows[$rowid] = $row;
            }

            return $rows;
        }

        /** @param array<string,mixed> $row */
        private static function expressionValueNext149(string $expression, string $column, string $rangeColumn, array $row): mixed
        {
            if (array_key_exists($rangeColumn, $row)) {
                return $row[$rangeColumn];
            }
            $value = self::rowColumnNext149($row, $column);
            if (preg_match('/^lower\\(([^)]+)\\)$/i', $expression) === 1) {
                return is_string($value) ? strtolower($value) : $value;
            }
            if (preg_match('/^upper\\(([^)]+)\\)$/i', $expression) === 1) {
                return is_string($value) ? strtoupper($value) : $value;
            }
            if (preg_match('/^length\\(([^)]+)\\)$/i', $expression) === 1) {
                return is_string($value) ? strlen($value) : null;
            }

            return $value;
        }

        /** @param array<string,mixed> $row */
        private static function rowColumnNext149(array $row, string $column): mixed
        {
            foreach ($row as $key => $value) {
                if (is_string($key) && strcasecmp($key, $column) === 0) {
                    return $value;
                }
            }

            return null;
        }

        private static function compareNext149(mixed $left, mixed $right, string $collation): ?int
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

        /** @return array<string,mixed> */
        private static function arrayValueNext149(array $source, string $key): array
        {
            $value = $source[$key] ?? [];
            if (!is_array($value)) {
                throw new \InvalidArgumentException('SQLite skip-scan expression range next149 metadata must be arrays');
            }

            return $value;
        }

        /** @return list<array<string,mixed>> */
        private static function arrayListNext149(mixed $value): array
        {
            return is_array($value) && array_is_list($value) ? array_values(array_filter($value, 'is_array')) : [];
        }

        /** @return list<int> */
        private static function intListNext149(mixed $value): array
        {
            return is_array($value) ? array_values(array_map(static fn (mixed $item): int => (int) $item, $value)) : [];
        }

        private static function stringValueNext149(array $source, string $key): string
        {
            $value = $source[$key] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite skip-scan expression range next149 needs {$key}");
            }

            return $value;
        }

        private static function boolValueNext149(array $source, string $key, bool $default): bool
        {
            $value = $source[$key] ?? $default;
            if (!is_bool($value)) {
                throw new \InvalidArgumentException("SQLite skip-scan expression range next149 needs boolean {$key}");
            }

            return $value;
        }

        private static function signatureNext149(array $payload): string
        {
            return hash('sha256', serialize($payload));
        }

}
