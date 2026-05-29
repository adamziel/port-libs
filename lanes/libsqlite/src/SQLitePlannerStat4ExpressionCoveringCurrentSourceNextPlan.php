<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionCoveringCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerStat4ExpressionCoveringCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array<string,string>> $orderBy
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return array<string,mixed>
         */
        public static function materializeNext144(
            array $preparedSource,
            array $currentSource,
            array $predicate,
            array $orderBy,
            array $neededColumns,
            array $neededExpressions
        ): array {
            self::validateSourceNext144($preparedSource);
            self::validateSourceNext144($currentSource);
            self::validateNeededColumnsNext144($neededColumns);

            $preparedView = SQLiteStat4ExpressionCoveringCurrentSourceNextPlan::materializeNext117(
                $preparedSource,
                $preparedSource,
                $predicate,
                $orderBy,
                $neededColumns,
                $neededExpressions,
            );
            $currentView = SQLiteStat4ExpressionCoveringCurrentSourceNextPlan::materializeNext117(
                $preparedSource,
                $currentSource,
                $predicate,
                $orderBy,
                $neededColumns,
                $neededExpressions,
            );

            $preparedRows = self::rowsNext144($preparedView);
            $currentRows = self::rowsNext144($currentView);
            $preparedRowids = self::rowidsNext144($preparedRows);
            $currentRowids = self::rowidsNext144($currentRows);
            $rejected = array_values(array_diff($preparedRowids, $currentRowids));
            $admitted = array_values(array_diff($currentRowids, $preparedRowids));
            $stable = array_values(array_intersect($currentRowids, $preparedRowids));
            $selectedPlan = self::arrayValueNext144($currentView, 'selectedPlan');
            $ready = ($currentView['status'] ?? null) === 'stat4-expression-covering-current-source-ready'
                && ($currentView['selectedSource'] ?? null) === 'current'
                && ($currentView['stalePreparedStatement'] ?? false) === true
                && ($selectedPlan['operator'] ?? null) === 'point'
                && ($selectedPlan['covering'] ?? false) === true
                && ($selectedPlan['orderBySatisfied'] ?? false) === true
                && $currentRows !== []
                && ($rejected !== [] || $admitted !== []);

            return array_replace($currentView, [
                'status' => $ready ? 'stat4-expression-covering-current-source-next144-ready' : 'requires-next-stage',
                'preparedCoveringRows' => $preparedRows,
                'currentCoveringRows' => $currentRows,
                'preparedCoveringRowids' => $preparedRowids,
                'currentCoveringRowids' => $currentRowids,
                'staleCoveringRejectedRowids' => $rejected,
                'currentCoveringAdmittedRowids' => $admitted,
                'stableCoveringRowids' => $stable,
                'currentSourceRowStreamChanged' => $preparedRowids !== $currentRowids,
                'currentSourcePayloadChanged' => self::payloadSignatureNext144($preparedRows) !== self::payloadSignatureNext144($currentRows),
                'pointPredicateSignature' => hash('sha256', json_encode($predicate, JSON_THROW_ON_ERROR)),
                'coveringExpressionSignature' => self::expressionSignatureNext144($neededExpressions),
                'coveringColumnSignature' => implode(',', array_map('strtolower', $neededColumns)),
                'currentSourceFence' => array_replace(
                    self::arrayValueNext144($currentView, 'currentSourceFence'),
                    [
                        'next144PointPredicateSignature' => hash('sha256', json_encode($predicate, JSON_THROW_ON_ERROR)),
                        'next144PreparedRowStreamSignature' => self::rowStreamSignatureNext144($preparedRows),
                        'next144CurrentRowStreamSignature' => self::rowStreamSignatureNext144($currentRows),
                        'next144PayloadSignature' => self::payloadSignatureNext144($currentRows),
                    ],
                ),
                'cursorTape' => self::cursorTapeNext144($currentView, $preparedRows, $currentRows, $rejected, $admitted, $stable),
                'detail' => (($currentView['stalePreparedStatement'] ?? false) ? 'REPREPARE' : 'REUSE')
                    . ' STAT4 EXPRESSION COVERING CURRENT-SOURCE NEXT144 '
                    . (string) ($selectedPlan['name'] ?? 'NO INDEX'),
                'dependencies' => array_values(array_unique(array_merge(
                    is_array($currentView['dependencies'] ?? null) ? $currentView['dependencies'] : [],
                    [
                        'SQLiteStat4ExpressionCoveringCurrentSourceNextPlan',
                        'sqlite-sqlplanner-stat4-expression-covering-current-source-next144',
                    ],
                ))),
                'dependency_closure' => 'no new support component needed; next144 reuses native STAT4 expression-covering current-source planning and adds point-predicate row-stream replacement diagnostics',
                'non_overlap' => 'avoids accepted STAT4 expression IN next126, expression range next128, expression skip-scan next137, range-cost ranking, and expression ORDER BY; this slice proves stale prepared point-predicate covering rows are replaced by the current sqlite_stat4/source row stream without table lookup',
            ]);
        }

        /**
         * @param array<string,mixed> $view
         * @return list<array<string,mixed>>
         */
        private static function rowsNext144(array $view): array
        {
            $rows = [];
            foreach (($view['cursorTape']['rowids'] ?? []) as $offset => $rowid) {
                $pair = $view['selectedPlan']['currentNextRows'][$offset] ?? null;
                if (!is_array($pair) || !is_array($pair['current'] ?? null)) {
                    continue;
                }
                $current = $pair['current'];
                $rows[] = [
                    'ordinal' => $offset,
                    'rowid' => $rowid,
                    'key' => $view['cursorTape']['expressionKeys'][$offset] ?? $current['key'] ?? null,
                    'covering' => is_array($current['covering'] ?? null) ? $current['covering'] : [],
                    'coveringExpressions' => is_array($current['coveringExpressions'] ?? null) ? $current['coveringExpressions'] : [],
                ];
            }

            return $rows;
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function validateSourceNext144(array $source): void
        {
            $indexes = $source['indexes'] ?? null;
            if (!is_array($indexes) || !array_is_list($indexes) || $indexes === []) {
                throw new \InvalidArgumentException('SQLite STAT4 expression covering next144 source needs index definitions');
            }
            foreach ($indexes as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite STAT4 expression covering next144 indexes must be arrays');
                }
            }
        }

        /**
         * @param list<string> $neededColumns
         */
        private static function validateNeededColumnsNext144(array $neededColumns): void
        {
            if ($neededColumns === []) {
                throw new \InvalidArgumentException('SQLite STAT4 expression covering next144 plan needs at least one output column');
            }
            foreach ($neededColumns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite STAT4 expression covering next144 output columns must be names');
                }
            }
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<int>
         */
        private static function rowidsNext144(array $rows): array
        {
            $rowids = [];
            foreach ($rows as $row) {
                if (is_int($row['rowid'] ?? null)) {
                    $rowids[] = $row['rowid'];
                }
            }
            sort($rowids);

            return $rowids;
        }

        /**
         * @param list<array<string,mixed>> $rows
         */
        private static function rowStreamSignatureNext144(array $rows): string
        {
            return hash('sha256', json_encode(array_map(static fn (array $row): array => [
                'rowid' => $row['rowid'] ?? null,
                'key' => $row['key'] ?? null,
            ], $rows), JSON_THROW_ON_ERROR));
        }

        /**
         * @param list<array<string,mixed>> $rows
         */
        private static function payloadSignatureNext144(array $rows): string
        {
            return hash('sha256', json_encode(array_map(static fn (array $row): array => [
                'rowid' => $row['rowid'] ?? null,
                'covering' => $row['covering'] ?? [],
                'coveringExpressions' => $row['coveringExpressions'] ?? [],
            ], $rows), JSON_THROW_ON_ERROR));
        }

        /**
         * @param list<array<string,string>> $expressions
         */
        private static function expressionSignatureNext144(array $expressions): string
        {
            if ($expressions === []) {
                return '';
            }

            return implode(',', array_map(static function (array $expression): string {
                $function = strtolower((string) ($expression['function'] ?? ''));
                $column = strtolower((string) ($expression['column'] ?? ''));
                $path = isset($expression['path']) ? ':' . (string) $expression['path'] : '';

                return $function . '(' . $column . ')' . $path;
            }, $expressions));
        }

        /**
         * @param array<string,mixed> $currentView
         * @param list<array<string,mixed>> $preparedRows
         * @param list<array<string,mixed>> $currentRows
         * @param list<int> $rejected
         * @param list<int> $admitted
         * @param list<int> $stable
         * @return array<string,mixed>
         */
        private static function cursorTapeNext144(
            array $currentView,
            array $preparedRows,
            array $currentRows,
            array $rejected,
            array $admitted,
            array $stable
        ): array {
            $tape = self::arrayValueNext144($currentView, 'cursorTape');
            $program = is_array($tape['program'] ?? null) ? $tape['program'] : [];
            array_unshift($program, [
                'opcode' => 'RecheckPointSource',
                'source' => 'current',
                'staleRejectedRowids' => $rejected,
                'currentAdmittedRowids' => $admitted,
            ]);

            return array_replace($tape, [
                'program' => $program,
                'preparedRows' => $preparedRows,
                'currentRows' => $currentRows,
                'preparedRowids' => self::rowidsNext144($preparedRows),
                'currentRowids' => self::rowidsNext144($currentRows),
                'staleCoveringRejectedRowids' => $rejected,
                'currentCoveringAdmittedRowids' => $admitted,
                'stableCoveringRowids' => $stable,
                'pointRecheckOpcode' => 'RecheckPointSource',
                'tableLookupElidedAfterCurrentSourceRecheck' => ($currentView['cursorTape']['tableLookupElided'] ?? false) === true,
            ]);
        }

        /**
         * @return array<string,mixed>
         */
        private static function arrayValueNext144(array $data, string $key): array
        {
            $value = $data[$key] ?? [];

            return is_array($value) ? $value : [];
        }

}
