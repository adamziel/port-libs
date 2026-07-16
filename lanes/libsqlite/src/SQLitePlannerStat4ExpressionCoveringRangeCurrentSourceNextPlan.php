<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceNextPlan. */

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
        public static function materializeCurrentSourceRange(
            array $preparedSource,
            array $currentSource,
            array $preparedPredicate,
            array $currentPredicate,
            array $currentRows,
            array $orderBy,
            array $neededColumns,
            array $neededExpressions = []
        ): array {
            $preparedView = SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan::materialize(
                $preparedSource,
                $preparedSource,
                $preparedPredicate,
                $currentRows,
                $orderBy,
                $neededColumns,
                $neededExpressions,
            );
            $currentView = SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan::materialize(
                $preparedSource,
                $currentSource,
                $currentPredicate,
                $currentRows,
                $orderBy,
                $neededColumns,
                $neededExpressions,
            );

            $preparedRowids = self::rowids($preparedView);
            $currentRowids = self::rowids($currentView);
            $rejected = array_values(array_diff($preparedRowids, $currentRowids));
            $admitted = array_values(array_diff($currentRowids, $preparedRowids));
            $selected = $currentView['selectedPlan'] ?? [];
            $ready = ($currentView['status'] ?? '') === 'covering-expression-stat4-current-source-ready'
                && ($currentView['stalePreparedStatement'] ?? false) === true
                && ($currentView['tableLookupElided'] ?? false) === true
                && ($selected['operator'] ?? null) === 'range-bounded';

            return array_replace($currentView, [
                'status' => $ready ? 'stat4-expression-covering-range-current-source-next128-ready' : 'requires-next-stage',
                'preparedPredicateSignature' => hash('sha256', json_encode($preparedPredicate, JSON_THROW_ON_ERROR)),
                'currentPredicateSignature' => hash('sha256', json_encode($currentPredicate, JSON_THROW_ON_ERROR)),
                'rangePredicateChanged' => self::rangeValues($preparedView) !== self::rangeValues($currentView),
                'preparedRangeValues' => self::rangeValues($preparedView),
                'currentRangeValues' => self::rangeValues($currentView),
                'preparedMatchedRowids' => $preparedRowids,
                'currentMatchedRowids' => $currentRowids,
                'staleRangeRejectedRowids' => $rejected,
                'currentRangeAdmittedRowids' => $admitted,
                'residualRangeRecheckRequired' => $rejected !== [] || $admitted !== [],
                'rangeRecheckOpcode' => 'IdxGE/IdxLT current-source fence',
                'cursorTape' => self::annotatedTape($currentView, $preparedView, $rejected, $admitted),
                'dependencies' => [
                    'SQLiteSelectExpressionIndexPlan bounded range STAT4 planner',
                    'SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan',
                    'sqlite-planner-stat4-expression-covering-range-current-source-next128',
                ],
                'dependency_closure' => 'no new support component needed; next128 reuses native expression-index STAT4 range planning and covering cursor diagnostics',
                'non_overlap' => 'avoids accepted canonical static bounded range and next126 IN probes by proving stale prepared range rows are rejected and current-source covering range rows are admitted after a range-bound/source change',
            ]);
        }

        /**
         * @param array<string,mixed> $view
         * @return list<int>
         */
        private static function rowids(array $view): array
        {
            $rowids = [];
            foreach (($view['currentNextRows'] ?? []) as $pair) {
                if (is_array($pair) && isset($pair['current']) && is_array($pair['current'])) {
                    $rowid = $pair['current']['rowid'] ?? null;
                    if (is_int($rowid)) {
                        $rowids[] = $rowid;
                    }
                }
            }
            sort($rowids);

            return $rowids;
        }

        /**
         * @param array<string,mixed> $view
         * @return array<string,mixed>
         */
        private static function rangeValues(array $view): array
        {
            $plan = $view['selectedPlan'] ?? [];
            $values = is_array($plan) && is_array($plan['values'] ?? null) ? $plan['values'] : [];

            return [
                'lower' => $values['lower'] ?? null,
                'upper' => $values['upper'] ?? null,
                'lowerInclusive' => (bool) ($values['lowerInclusive'] ?? false),
                'upperInclusive' => (bool) ($values['upperInclusive'] ?? false),
            ];
        }

        /**
         * @param array<string,mixed> $currentView
         * @param array<string,mixed> $preparedView
         * @param list<int> $rejected
         * @param list<int> $admitted
         * @return array<string,mixed>
         */
        private static function annotatedTape(array $currentView, array $preparedView, array $rejected, array $admitted): array
        {
            $tape = is_array($currentView['cursorTape'] ?? null) ? $currentView['cursorTape'] : [];
            $program = is_array($tape['program'] ?? null) ? $tape['program'] : [];
            array_unshift($program, [
                'opcode' => 'RecheckRangeBounds',
                'source' => 'current-source',
                'prepared' => self::rangeValues($preparedView),
                'current' => self::rangeValues($currentView),
            ]);

            return array_replace($tape, [
                'program' => $program,
                'preparedRange' => self::rangeValues($preparedView),
                'currentRange' => self::rangeValues($currentView),
                'staleRangeRejectedRowids' => $rejected,
                'currentRangeAdmittedRowids' => $admitted,
                'residualRangeRecheckOpcode' => 'RecheckRangeBounds',
                'tableLookupElidedAfterRecheck' => ($currentView['tableLookupElided'] ?? false) === true,
            ]);
        }

}
