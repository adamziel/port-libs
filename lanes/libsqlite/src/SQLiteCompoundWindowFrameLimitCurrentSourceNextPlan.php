<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundWindowFrameLimitCurrentSourceNextPlan
{
    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareWindowFrameLimit(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound window frame LIMIT current-source plan needs a compound SELECT');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);

            return [
                'status' => 'compound-window-frame-limit-current-source-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentSignatures' => self::rowSignatures($currentRows),
                'nextSignatures' => self::rowSignatures($nextRows),
                'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumns($currentPlan),
                    'limit' => $currentPlan['compound']['limit'] ?? null,
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                ],
                'windows' => [
                    'current' => self::windowTerms($currentPlan),
                    'next' => self::windowTerms($nextPlan),
                ],
                'limitBoundary' => self::limitBoundary($currentRows, $nextRows),
                'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPlan, $nextPlan),
                'dependencies' => [
                    'sqlite-select-sql-compound-tail-limit',
                    'sqlite-select-sql-window-current-row-frame',
                    'sqlite-select-sql-current-source-next-rowset',
                ],
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumns(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['orderBy'] ?? null)) {
                return [];
            }

            return array_values(array_map(
                static fn (array $term): string => (string) ($term['column'] ?? ''),
                $compound['orderBy'],
            ));
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function windowTerms(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['arms'] ?? null)) {
                return [];
            }

            $windows = [];
            foreach ($compound['arms'] as $armIndex => $arm) {
                if (!is_array($arm) || !is_array($arm['select'] ?? null)) {
                    continue;
                }
                foreach ($arm['select'] as $selectIndex => $term) {
                    if (!is_array($term) || ($term['type'] ?? null) !== 'window') {
                        continue;
                    }
                    $frame = is_array($term['frame'] ?? null) ? $term['frame'] : [];
                    $windows[] = [
                        'arm' => $armIndex,
                        'selectIndex' => $selectIndex,
                        'alias' => isset($term['alias']) && is_string($term['alias']) ? $term['alias'] : 'expr' . ($selectIndex + 1),
                        'function' => (string) ($term['function'] ?? ''),
                        'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                        'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                        'frameUnit' => isset($frame['unit']) ? (string) $frame['unit'] : null,
                        'preceding' => $frame['preceding'] ?? null,
                        'following' => $frame['following'] ?? null,
                        'exclude' => isset($frame['exclude']) ? (string) $frame['exclude'] : null,
                    ];
                }
            }

            return $windows;
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return array{currentCount:int,nextCount:int,currentLast:array<string,mixed>|null,nextLast:array<string,mixed>|null}
         */
        private static function limitBoundary(array $currentRows, array $nextRows): array
        {
            return [
                'currentCount' => count($currentRows),
                'nextCount' => count($nextRows),
                'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignatures(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignatures(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignatures($currentRows);
            $next = self::rowSignatures($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         * @return list<string>
         */
        private static function replanReasons(array $currentRows, array $nextRows, array $currentPlan, array $nextPlan): array
        {
            $reasons = [];
            if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
                $reasons[] = 'limited-compound-rowset-changed';
            }
            if (self::windowTerms($currentPlan) !== []) {
                $reasons[] = 'compound-window-frame-source';
            }
            if (($currentPlan['compound']['limit'] ?? null) !== null) {
                $reasons[] = 'compound-tail-limit';
            }
            if (self::windowTerms($currentPlan) !== self::windowTerms($nextPlan)) {
                $reasons[] = 'window-plan-changed';
            }

            return $reasons;
        }

}
