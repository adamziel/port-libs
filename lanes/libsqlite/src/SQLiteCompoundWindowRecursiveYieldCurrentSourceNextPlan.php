<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundWindowRecursiveYieldCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundWindowRecursiveYieldCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareNext159(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            self::assertSupportedNext159($sql, $currentPlan, $nextPlan);

            $preLimitSql = self::withoutFinalLimitNext159($sql);
            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $currentTables);
            $nextPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $nextTables);
            $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($sql, $currentTables);
            $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($sql, $nextTables);

            return [
                'status' => 'compound-window-recursive-yield-current-source-next159-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentPreLimitRows' => $currentPreLimitRows,
                'nextPreLimitRows' => $nextPreLimitRows,
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext159($currentPlan),
                    'limit' => $currentPlan['compound']['limit'],
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                    'commaLimit' => preg_match('/\s+LIMIT\s+\d+\s*,\s*\d+\s*$/i', rtrim(trim($sql), ';')) === 1,
                ],
                'windows' => [
                    'current' => self::windowTermsNext159($currentPlan),
                    'next' => self::windowTermsNext159($nextPlan),
                    'functions' => array_values(array_unique(array_column(self::windowTermsNext159($currentPlan), 'function'))),
                ],
                'recursive' => [
                    'name' => $currentTrace['name'] ?? null,
                    'columns' => $currentTrace['columns'] ?? [],
                    'operator' => $currentTrace['operator'] ?? null,
                    'currentRows' => $currentTrace['rows'] ?? [],
                    'nextRows' => $nextTrace['rows'] ?? [],
                    'currentTraceCount' => is_array($currentTrace['trace'] ?? null) ? count($currentTrace['trace']) : 0,
                    'nextTraceCount' => is_array($nextTrace['trace'] ?? null) ? count($nextTrace['trace']) : 0,
                    'currentLimitRemaining' => self::lastTraceValueNext159($currentTrace, 'limit_remaining'),
                    'nextLimitRemaining' => self::lastTraceValueNext159($nextTrace, 'limit_remaining'),
                    'dependencies' => array_values(array_unique(array_merge(
                        is_array($currentTrace['dependencies'] ?? null) ? $currentTrace['dependencies'] : [],
                        is_array($nextTrace['dependencies'] ?? null) ? $nextTrace['dependencies'] : [],
                    ))),
                ],
                'yieldSlots' => [
                    'current' => self::yieldSlotsNext159($currentPreLimitRows, $currentRows, $currentPlan),
                    'next' => self::yieldSlotsNext159($nextPreLimitRows, $nextRows, $nextPlan),
                ],
                'sourceClasses' => [
                    'current' => self::sourceClassesNext159($currentRows),
                    'next' => self::sourceClassesNext159($nextRows),
                ],
                'boundary' => self::boundaryDeltaNext159($currentRows, $nextRows),
                'changedSignatures' => self::changedSignaturesNext159($currentRows, $nextRows),
                'replanReasons' => self::replanReasonsNext159($currentRows, $nextRows, $currentPreLimitRows, $nextPreLimitRows, $currentTrace, $nextTrace),
                'dependencies' => [
                    'sqlite-recursive-cte-limit-before-window-yield-next159',
                    'sqlite-compound-window-ntile-percent-rank-yield-next159',
                    'sqlite-compound-comma-limit-current-next-yield-boundary-next159',
                ],
                'dependency_closure' => 'no new support component needed; this reuses lane-local SELECT SQL, recursive CTE queue, compound combiner, window execution, and comma LIMIT helpers',
            ];
        }

        /**
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         */
        private static function assertSupportedNext159(string $sql, array $currentPlan, array $nextPlan): void
        {
            if (stripos($sql, 'WITH RECURSIVE') === false) {
                throw new \InvalidArgumentException('SQLite compound window recursive yield next159 needs WITH RECURSIVE SQL');
            }
            if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
                throw new \InvalidArgumentException('SQLite compound window recursive yield next159 needs a compound SELECT');
            }
            if (($currentPlan['compound']['limit'] ?? null) === null || preg_match('/\s+LIMIT\s+\d+\s*,\s*\d+\s*$/i', rtrim(trim($sql), ';')) !== 1) {
                throw new \InvalidArgumentException('SQLite compound window recursive yield next159 needs a final comma LIMIT');
            }
            $functions = array_map('strtolower', array_column(self::windowTermsNext159($currentPlan), 'function'));
            if (!in_array('ntile', $functions, true) || !in_array('percent_rank', $functions, true)) {
                throw new \InvalidArgumentException('SQLite compound window recursive yield next159 needs ntile() and percent_rank() window arms');
            }
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumnsNext159(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['orderBy'] ?? null)) {
                return [];
            }

            return array_values(array_map(static fn (array $term): string => (string) ($term['column'] ?? ''), $compound['orderBy']));
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function windowTermsNext159(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            $arms = is_array($compound) && is_array($compound['arms'] ?? null) ? $compound['arms'] : [];
            $windows = [];
            foreach ($arms as $armIndex => $arm) {
                $select = is_array($arm) && is_array($arm['select'] ?? null) ? $arm['select'] : [];
                foreach ($select as $selectIndex => $term) {
                    if (!is_array($term) || ($term['type'] ?? null) !== 'window') {
                        continue;
                    }
                    $windows[] = [
                        'arm' => $armIndex,
                        'selectIndex' => $selectIndex,
                        'alias' => isset($term['alias']) && is_string($term['alias']) ? $term['alias'] : 'expr' . ($selectIndex + 1),
                        'function' => (string) ($term['function'] ?? ''),
                        'argumentCount' => is_array($term['arguments'] ?? null) ? count($term['arguments']) : 0,
                        'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                    ];
                }
            }

            return $windows;
        }

        private static function withoutFinalLimitNext159(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            $without = preg_replace('/\s+LIMIT\s+\d+\s*,\s*\d+\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound window recursive yield next159 cannot isolate final comma LIMIT');
            }

            return $without;
        }

        /**
         * @param list<array<string,mixed>> $preLimitRows
         * @param list<array<string,mixed>> $limitedRows
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function yieldSlotsNext159(array $preLimitRows, array $limitedRows, array $plan): array
        {
            $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
            $offset = isset($compound['offset']) && is_int($compound['offset']) ? $compound['offset'] : 0;
            $limit = isset($compound['limit']) && is_int($compound['limit']) ? $compound['limit'] : count($limitedRows);

            $slots = [];
            foreach ($limitedRows as $slot => $row) {
                $slots[] = [
                    'slot' => $slot,
                    'preLimitIndex' => $offset + $slot,
                    'sourceClass' => self::sourceClassNext159($row),
                    'row' => $row,
                ];
            }

            return [
                'offset' => $offset,
                'limit' => $limit,
                'preLimitCount' => count($preLimitRows),
                'yieldedCount' => count($limitedRows),
                'skipped' => array_slice($preLimitRows, 0, $offset),
                'slots' => $slots,
                'truncated' => array_slice($preLimitRows, $offset + $limit),
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return array<string,int>
         */
        private static function sourceClassesNext159(array $rows): array
        {
            $classes = [];
            foreach ($rows as $row) {
                $class = self::sourceClassNext159($row);
                $classes[$class] = ($classes[$class] ?? 0) + 1;
            }
            ksort($classes);

            return $classes;
        }

        /**
         * @param array<string,mixed> $row
         */
        private static function sourceClassNext159(array $row): string
        {
            $label = (string) ($row['label'] ?? '');

            return str_starts_with($label, 'seed') ? 'recursive' : 'table';
        }

        /**
         * @param array<string,mixed> $trace
         */
        private static function lastTraceValueNext159(array $trace, string $key): mixed
        {
            $rows = is_array($trace['trace'] ?? null) ? $trace['trace'] : [];
            $last = $rows === [] ? null : $rows[count($rows) - 1];

            return is_array($last) ? ($last[$key] ?? null) : null;
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return array<string,mixed>
         */
        private static function boundaryDeltaNext159(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext159($currentRows);
            $next = self::rowSignaturesNext159($nextRows);

            return [
                'currentFirst' => $currentRows[0] ?? null,
                'nextFirst' => $nextRows[0] ?? null,
                'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                'lostRows' => array_values(array_diff($current, $next)),
                'gainedRows' => array_values(array_diff($next, $current)),
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignaturesNext159(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext159(array $currentRows, array $nextRows): array
        {
            return array_values(array_merge(array_diff(self::rowSignaturesNext159($currentRows), self::rowSignaturesNext159($nextRows)), array_diff(self::rowSignaturesNext159($nextRows), self::rowSignaturesNext159($currentRows))));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentPreLimitRows
         * @param list<array<string,mixed>> $nextPreLimitRows
         * @param array<string,mixed> $currentTrace
         * @param array<string,mixed> $nextTrace
         * @return list<string>
         */
        private static function replanReasonsNext159(array $currentRows, array $nextRows, array $currentPreLimitRows, array $nextPreLimitRows, array $currentTrace, array $nextTrace): array
        {
            $reasons = ['recursive-limit-before-window-yield', 'compound-comma-limit-yield-boundary'];
            if (self::rowSignaturesNext159($currentRows) !== self::rowSignaturesNext159($nextRows)) {
                $reasons[] = 'yielded-compound-rowset-changed';
            }
            if (self::rowSignaturesNext159($currentPreLimitRows) !== self::rowSignaturesNext159($nextPreLimitRows)) {
                $reasons[] = 'prelimit-window-rowset-changed';
            }
            if (($currentTrace['rows'] ?? []) !== ($nextTrace['rows'] ?? [])) {
                $reasons[] = 'recursive-source-rowset-changed';
            }
            if (self::lastTraceValueNext159($currentTrace, 'limit_remaining') === 0) {
                $reasons[] = 'recursive-limit-exhausted-before-window';
            }

            return $reasons;
        }

}
