<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerCoveringExpressionRangeCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerCoveringExpressionRangeCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $preparedPredicate
         * @param array<string,mixed> $currentPredicate
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,string>> $orderBy
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return array<string,mixed>
         */
        public static function materializeDescendingCurrentRange(
            array $preparedSource,
            array $currentSource,
            array $preparedPredicate,
            array $currentPredicate,
            array $currentRows,
            array $orderBy,
            array $neededColumns,
            array $neededExpressions = []
        ): array {
            $base = SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceNextPlan::materializeCurrentSourceRange(
                $preparedSource,
                $currentSource,
                $preparedPredicate,
                $currentPredicate,
                $currentRows,
                $orderBy,
                $neededColumns,
                $neededExpressions,
            );

            $selected = is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [];
            $descending = (bool) ($selected['descending'] ?? false);
            $currentNextRows = is_array($base['currentNextRows'] ?? null) ? $base['currentNextRows'] : [];
            $streamRows = self::streamCurrentRows($currentNextRows);
            if ($descending) {
                $streamRows = array_reverse($streamRows);
            }
            $streamPairs = self::pairCurrentRows($streamRows);
            $ready = ($base['status'] ?? '') === 'stat4-expression-covering-range-current-source-next128-ready'
                && $descending
                && ($base['tableLookupElided'] ?? false) === true
                && ($base['tempSorterElided'] ?? false) === true
                && $streamPairs !== [];

            $cursorTape = self::descendingCursorTape($base, $streamPairs, $neededColumns, $ready);

            return array_replace($base, [
                'status' => $ready ? 'covering-expression-range-current-source-descending-ready' : 'requires-next-stage',
                'currentNextRows' => $streamPairs,
                'descendingCurrentSourceRange' => $descending,
                'currentSourceRangeCursor' => $descending ? 'descending-covering-expression-range' : 'forward-covering-expression-range',
                'rangeDirection' => $descending ? 'DESC' : 'ASC',
                'rangeSeekOpcode' => $descending ? self::descendingSeekOpcode($base) : ($base['cursorTape']['seekOpcode'] ?? null),
                'rangeStopOpcode' => $descending ? self::descendingStopOpcode($base) : ($base['cursorTape']['stopOpcode'] ?? null),
                'currentSourceNextRowids' => array_map(static fn (array $pair): mixed => $pair['current']['rowid'] ?? null, $streamPairs),
                'currentSourceNextKeys' => array_map(static fn (array $pair): mixed => $pair['current']['key'] ?? null, $streamPairs),
                'cursorTape' => $cursorTape,
                'dependencies' => [
                    'SQLiteSelectExpressionIndexPlan bounded range STAT4 planner',
                    'SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceNextPlan',
                    'sqlite-planner-covering-expression-range-current-source-descending',
                ],
                'dependency_closure' => 'no new support component needed; descending reuses native expression-index STAT4 range planning and covering cursor current/next diagnostics',
                'non_overlap' => 'avoids accepted next128 forward range recheck, next132 expression skip-scan, expression ORDER BY, and range-cost ranking by proving descending covering expression range current/next cursor materialization',
            ]);
        }

        /**
         * @param list<array<string,mixed>> $currentNextRows
         * @return list<array<string,mixed>>
         */
        private static function streamCurrentRows(array $currentNextRows): array
        {
            $rows = [];
            foreach ($currentNextRows as $pair) {
                if (!is_array($pair) || !isset($pair['current']) || !is_array($pair['current'])) {
                    continue;
                }
                $rows[] = $pair['current'];
            }

            return $rows;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<array{current:array<string,mixed>,next:?array<string,mixed>}>
         */
        private static function pairCurrentRows(array $rows): array
        {
            $pairs = [];
            foreach ($rows as $offset => $row) {
                $pairs[] = ['current' => $row, 'next' => $rows[$offset + 1] ?? null];
            }

            return $pairs;
        }

        /**
         * @param array<string,mixed> $base
         * @param list<array{current:array<string,mixed>,next:?array<string,mixed>}> $streamPairs
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function descendingCursorTape(array $base, array $streamPairs, array $neededColumns, bool $ready): array
        {
            $tape = is_array($base['cursorTape'] ?? null) ? $base['cursorTape'] : [];
            $selected = is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [];
            $program = [
                [
                    'opcode' => 'RecheckRangeBounds',
                    'source' => 'current-source',
                    'direction' => (bool) ($selected['descending'] ?? false) ? 'DESC' : 'ASC',
                    'prepared' => $base['preparedRangeValues'] ?? null,
                    'current' => $base['currentRangeValues'] ?? null,
                ],
                ['opcode' => self::descendingSeekOpcode($base), 'source' => 'index', 'key' => ($base['currentRangeValues']['upper'] ?? null)],
                ['opcode' => self::descendingStopOpcode($base), 'source' => 'index', 'key' => ($base['currentRangeValues']['lower'] ?? null)],
            ];
            foreach ($neededColumns as $column) {
                $program[] = ['opcode' => 'Column', 'source' => $ready ? 'index' : 'table', 'column' => $column];
            }
            $program[] = ['opcode' => 'ResultRow', 'source' => $ready ? 'covering-index' : 'table'];
            $program[] = ['opcode' => 'Prev', 'source' => $ready ? 'index' : 'table'];

            return array_replace($tape, [
                'source' => 'current',
                'direction' => (bool) ($selected['descending'] ?? false) ? 'DESC' : 'ASC',
                'seekOpcode' => self::descendingSeekOpcode($base),
                'stopOpcode' => self::descendingStopOpcode($base),
                'rangeUpper' => $base['currentRangeValues']['upper'] ?? null,
                'rangeLower' => $base['currentRangeValues']['lower'] ?? null,
                'matchedKeys' => array_map(static fn (array $pair): mixed => $pair['current']['key'] ?? null, $streamPairs),
                'currentNextRows' => $streamPairs,
                'outputColumns' => array_map(static fn (string $column): array => ['opcode' => 'Column', 'source' => $ready ? 'index' : 'table', 'column' => $column], $neededColumns),
                'program' => $program,
                'nextOpcode' => 'Prev',
                'tableLookupElidedAfterRecheck' => $ready,
                'sorterOpen' => !$ready,
            ]);
        }

        /**
         * @param array<string,mixed> $base
         */
        private static function descendingSeekOpcode(array $base): string
        {
            return (bool) ($base['currentRangeValues']['upperInclusive'] ?? false) ? 'SeekLE' : 'SeekLT';
        }

        /**
         * @param array<string,mixed> $base
         */
        private static function descendingStopOpcode(array $base): string
        {
            return (bool) ($base['currentRangeValues']['lowerInclusive'] ?? false) ? 'IdxLT' : 'IdxLE';
        }

}
