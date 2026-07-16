<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWhereLimitMutationPlan
{
    private const SOURCE = 'wherelimit.test sections wherelimit-0.1 through wherelimit-4.12';

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite wherelimit dynamic corpus requires at least one case');
        }

        $templates = self::templates();
        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $out[] = self::caseFromTemplate($case, $batch, $template);
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function templates(): array
    {
        return [
            ['section' => 'wherelimit-0.1', 'kind' => 'syntax-error', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 ORDER BY x', 'error' => 'ORDER BY without LIMIT on DELETE', 'scenario' => 'DELETE ORDER BY without LIMIT is rejected'],
            ['section' => 'wherelimit-0.2', 'kind' => 'syntax-error', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=1 ORDER BY x', 'error' => 'ORDER BY without LIMIT on DELETE', 'scenario' => 'DELETE WHERE ORDER BY without LIMIT is rejected'],
            ['section' => 'wherelimit-0.3', 'kind' => 'syntax-error', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=1 WHERE x=1 ORDER BY x', 'error' => 'ORDER BY without LIMIT on UPDATE', 'scenario' => 'UPDATE ORDER BY without LIMIT is rejected'],
            ['section' => 'wherelimit-0.4', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 AS a WHERE a.x=%d', 'where_x' => 1, 'order' => '', 'limit' => null, 'offset' => 0, 'limit_expression' => '', 'scenario' => 'DELETE target aliases are accepted'],
            ['section' => 'wherelimit-0.5.1', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 AS a SET y=1 WHERE x=%d', 'where_x' => 1, 'set_y' => 1, 'order' => '', 'limit' => null, 'offset' => 0, 'limit_expression' => '', 'scenario' => 'UPDATE target aliases are accepted when predicates use the alias scope'],
            ['section' => 'wherelimit-0.5.2', 'kind' => 'syntax-error', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 AS a SET y=1 WHERE t1.x=1', 'error' => 'no such column: t1.x', 'scenario' => 'UPDATE alias hides the original table name in predicates'],
            ['section' => 'wherelimit-0.6', 'kind' => 'syntax-error', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=1 OFFSET 2', 'error' => 'near "OFFSET": syntax error', 'scenario' => 'DELETE OFFSET without LIMIT is rejected'],
            ['section' => 'wherelimit-0.7', 'kind' => 'syntax-error', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=1 WHERE x=1 OFFSET 2', 'error' => 'near "OFFSET": syntax error', 'scenario' => 'UPDATE OFFSET without LIMIT is rejected'],
            ['section' => 'wherelimit-1.1', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1', 'where_x' => null, 'order' => '', 'limit' => null, 'offset' => 0, 'limit_expression' => '', 'scenario' => 'DELETE without WHERE removes all rowid table rows'],
            ['section' => 'wherelimit-1.2', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 LIMIT 5', 'where_x' => null, 'order' => '', 'limit' => 5, 'offset' => 0, 'limit_expression' => 'LIMIT 5', 'scenario' => 'DELETE LIMIT chooses the first rowid-table rows'],
            ['section' => 'wherelimit-1.3', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 ORDER BY x LIMIT 5', 'where_x' => null, 'order' => 'x', 'limit' => 5, 'offset' => 0, 'limit_expression' => 'LIMIT 5', 'scenario' => 'DELETE ORDER BY x applies before LIMIT'],
            ['section' => 'wherelimit-1.3b', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => "DELETE FROM t1 RETURNING x, y, '|' ORDER BY x, y LIMIT 5", 'where_x' => null, 'order' => 'x,y', 'limit' => 5, 'offset' => 0, 'limit_expression' => 'LIMIT 5', 'returning' => true, 'scenario' => 'DELETE RETURNING reports rows selected by ORDER BY x,y LIMIT'],
            ['section' => 'wherelimit-1.4', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => "DELETE FROM t1 RETURNING x, y, '|' ORDER BY x LIMIT 5 OFFSET 2", 'where_x' => null, 'order' => 'x', 'limit' => 5, 'offset' => 2, 'limit_expression' => 'LIMIT 5 OFFSET 2', 'returning' => true, 'scenario' => 'DELETE RETURNING skips rows using OFFSET after ORDER BY'],
            ['section' => 'wherelimit-1.5', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 ORDER BY x LIMIT 5 OFFSET -2', 'where_x' => null, 'order' => 'x', 'limit' => 5, 'offset' => -2, 'limit_expression' => 'LIMIT 5 OFFSET -2', 'scenario' => 'negative OFFSET is normalized to zero for DELETE'],
            ['section' => 'wherelimit-1.6', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 ORDER BY x LIMIT 2, -5', 'where_x' => null, 'order' => 'x', 'limit' => -5, 'offset' => 2, 'limit_expression' => 'LIMIT 2, -5', 'scenario' => 'comma LIMIT with negative count deletes all rows after the offset'],
            ['section' => 'wherelimit-1.7', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 ORDER BY x LIMIT -2, 5', 'where_x' => null, 'order' => 'x', 'limit' => 5, 'offset' => -2, 'limit_expression' => 'LIMIT -2, 5', 'scenario' => 'comma LIMIT with negative offset starts at zero'],
            ['section' => 'wherelimit-1.8', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 ORDER BY x LIMIT -2, -5', 'where_x' => null, 'order' => 'x', 'limit' => -5, 'offset' => -2, 'limit_expression' => 'LIMIT -2, -5', 'scenario' => 'negative comma offset and count delete every ordered row'],
            ['section' => 'wherelimit-1.9', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 ORDER BY x LIMIT 2, 5', 'where_x' => null, 'order' => 'x', 'limit' => 5, 'offset' => 2, 'limit_expression' => 'LIMIT 2, 5', 'scenario' => 'comma LIMIT applies offset,count ordering for DELETE'],
            ['section' => 'wherelimit-1.10', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 ORDER BY x LIMIT 5 OFFSET 5', 'where_x' => null, 'order' => 'x', 'limit' => 5, 'offset' => 5, 'limit_expression' => 'LIMIT 5 OFFSET 5', 'scenario' => 'DELETE LIMIT OFFSET skips a full page of ordered rows'],
            ['section' => 'wherelimit-1.11', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 ORDER BY x LIMIT 50 OFFSET 30', 'where_x' => null, 'order' => 'x', 'limit' => 50, 'offset' => 30, 'limit_expression' => 'LIMIT 50 OFFSET 30', 'scenario' => 'DELETE offset beyond the rowset mutates no rows'],
            ['section' => 'wherelimit-1.12', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 ORDER BY x LIMIT 30, 50', 'where_x' => null, 'order' => 'x', 'limit' => 50, 'offset' => 30, 'limit_expression' => 'LIMIT 30, 50', 'scenario' => 'comma LIMIT offset beyond the rowset mutates no rows'],
            ['section' => 'wherelimit-1.13', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 ORDER BY x LIMIT 50 OFFSET 50', 'where_x' => null, 'order' => 'x', 'limit' => 50, 'offset' => 50, 'limit_expression' => 'LIMIT 50 OFFSET 50', 'scenario' => 'large DELETE OFFSET leaves the table unchanged'],
            ['section' => 'wherelimit-2.1', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=%d', 'where_x' => 1, 'order' => '', 'limit' => null, 'offset' => 0, 'limit_expression' => '', 'scenario' => 'DELETE WHERE removes all rows with the target x value'],
            ['section' => 'wherelimit-2.2', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=%d LIMIT 5', 'where_x' => 1, 'order' => '', 'limit' => 5, 'offset' => 0, 'limit_expression' => 'LIMIT 5', 'scenario' => 'DELETE WHERE LIMIT selects a bounded set of matching rows'],
            ['section' => 'wherelimit-2.3', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=%d ORDER BY x LIMIT 5', 'where_x' => 1, 'order' => 'x', 'limit' => 5, 'offset' => 0, 'limit_expression' => 'LIMIT 5', 'scenario' => 'DELETE WHERE ORDER BY LIMIT keeps the x-filter before selection'],
            ['section' => 'wherelimit-2.4', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=%d ORDER BY x LIMIT 5 OFFSET 2', 'where_x' => 2, 'order' => 'x', 'limit' => 5, 'offset' => 2, 'limit_expression' => 'LIMIT 5 OFFSET 2', 'scenario' => 'DELETE WHERE ORDER BY LIMIT OFFSET skips matching rows only'],
            ['section' => 'wherelimit-2.5', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=%d ORDER BY x LIMIT 5 OFFSET -2', 'where_x' => 2, 'order' => 'x', 'limit' => 5, 'offset' => -2, 'limit_expression' => 'LIMIT 5 OFFSET -2', 'scenario' => 'DELETE WHERE negative OFFSET starts at the first matching row'],
            ['section' => 'wherelimit-2.6', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=%d ORDER BY x LIMIT 2, -5', 'where_x' => 3, 'order' => 'x', 'limit' => -5, 'offset' => 2, 'limit_expression' => 'LIMIT 2, -5', 'scenario' => 'DELETE WHERE comma LIMIT removes all matches after the offset'],
            ['section' => 'wherelimit-2.7', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=%d ORDER BY x LIMIT -2, 5', 'where_x' => 3, 'order' => 'x', 'limit' => 5, 'offset' => -2, 'limit_expression' => 'LIMIT -2, 5', 'scenario' => 'DELETE WHERE comma LIMIT negative offset starts at zero'],
            ['section' => 'wherelimit-2.8', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=%d ORDER BY x LIMIT -2, -5', 'where_x' => 4, 'order' => 'x', 'limit' => -5, 'offset' => -2, 'limit_expression' => 'LIMIT -2, -5', 'scenario' => 'DELETE WHERE negative count removes all matching rows'],
            ['section' => 'wherelimit-2.9', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=%d ORDER BY x LIMIT 2, 5', 'where_x' => 5, 'order' => 'x', 'limit' => 5, 'offset' => 2, 'limit_expression' => 'LIMIT 2, 5', 'scenario' => 'DELETE WHERE comma LIMIT offset,count applies after filtering'],
            ['section' => 'wherelimit-2.10', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=%d ORDER BY x LIMIT 5 OFFSET 5', 'where_x' => 6, 'order' => 'x', 'limit' => 5, 'offset' => 5, 'limit_expression' => 'LIMIT 5 OFFSET 5', 'scenario' => 'DELETE WHERE large OFFSET can select the last matching row'],
            ['section' => 'wherelimit-2.11', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=%d ORDER BY x LIMIT 50 OFFSET 30', 'where_x' => 1, 'order' => 'x', 'limit' => 50, 'offset' => 30, 'limit_expression' => 'LIMIT 50 OFFSET 30', 'scenario' => 'DELETE WHERE OFFSET beyond matches mutates no rows'],
            ['section' => 'wherelimit-2.12', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=%d ORDER BY x LIMIT 30, 50', 'where_x' => 2, 'order' => 'x', 'limit' => 50, 'offset' => 30, 'limit_expression' => 'LIMIT 30, 50', 'scenario' => 'DELETE WHERE comma offset beyond matches mutates no rows'],
            ['section' => 'wherelimit-2.13', 'kind' => 'rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t1 WHERE x=%d ORDER BY x LIMIT 50 OFFSET 50', 'where_x' => 3, 'order' => 'x', 'limit' => 50, 'offset' => 50, 'limit_expression' => 'LIMIT 50 OFFSET 50', 'scenario' => 'DELETE WHERE large OFFSET leaves matching rows unchanged'],
            ['section' => 'wherelimit-3.1', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=1 WHERE x=%d', 'where_x' => 1, 'set_y' => 1, 'order' => '', 'limit' => null, 'offset' => 0, 'limit_expression' => '', 'scenario' => 'UPDATE WHERE mutates all rows with the target x value'],
            ['section' => 'wherelimit-3.2', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => "UPDATE t1 SET y=1 WHERE x=%d RETURNING x, y, '|' LIMIT 5", 'where_x' => 1, 'set_y' => 1, 'order' => '', 'limit' => 5, 'offset' => 0, 'limit_expression' => 'LIMIT 5', 'returning' => true, 'scenario' => 'UPDATE RETURNING reports rows selected by WHERE LIMIT'],
            ['section' => 'wherelimit-3.3', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=2 WHERE x=%d ORDER BY x LIMIT 5', 'where_x' => 2, 'set_y' => 2, 'order' => 'x', 'limit' => 5, 'offset' => 0, 'limit_expression' => 'LIMIT 5', 'scenario' => 'UPDATE WHERE ORDER BY LIMIT mutates only selected matches'],
            ['section' => 'wherelimit-3.4', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=2 WHERE x=%d ORDER BY x LIMIT 5 OFFSET 2', 'where_x' => 2, 'set_y' => 2, 'order' => 'x', 'limit' => 5, 'offset' => 2, 'limit_expression' => 'LIMIT 5 OFFSET 2', 'scenario' => 'UPDATE WHERE OFFSET skips selected matches before mutation'],
            ['section' => 'wherelimit-3.5', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=2 WHERE x=%d ORDER BY x LIMIT 5 OFFSET -2', 'where_x' => 2, 'set_y' => 2, 'order' => 'x', 'limit' => 5, 'offset' => -2, 'limit_expression' => 'LIMIT 5 OFFSET -2', 'scenario' => 'UPDATE WHERE negative OFFSET starts at the first match'],
            ['section' => 'wherelimit-3.6', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=3 WHERE x=%d ORDER BY x LIMIT 2, -5', 'where_x' => 3, 'set_y' => 3, 'order' => 'x', 'limit' => -5, 'offset' => 2, 'limit_expression' => 'LIMIT 2, -5', 'scenario' => 'UPDATE WHERE negative comma count mutates all matches after offset'],
            ['section' => 'wherelimit-3.7', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=3 WHERE x=%d ORDER BY x LIMIT -2, 5', 'where_x' => 3, 'set_y' => 3, 'order' => 'x', 'limit' => 5, 'offset' => -2, 'limit_expression' => 'LIMIT -2, 5', 'scenario' => 'UPDATE WHERE comma LIMIT negative offset starts at zero'],
            ['section' => 'wherelimit-3.8', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=4 WHERE x=%d ORDER BY x LIMIT -2, -5', 'where_x' => 4, 'set_y' => 4, 'order' => 'x', 'limit' => -5, 'offset' => -2, 'limit_expression' => 'LIMIT -2, -5', 'scenario' => 'UPDATE WHERE negative offset and count mutate all matches'],
            ['section' => 'wherelimit-3.9', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=4 WHERE x=%d ORDER BY x LIMIT 2, 5', 'where_x' => 5, 'set_y' => 4, 'order' => 'x', 'limit' => 5, 'offset' => 2, 'limit_expression' => 'LIMIT 2, 5', 'scenario' => 'UPDATE WHERE comma offset,count mutates selected matches'],
            ['section' => 'wherelimit-3.10', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=4 WHERE x=%d ORDER BY x LIMIT 5 OFFSET 5', 'where_x' => 6, 'set_y' => 4, 'order' => 'x', 'limit' => 5, 'offset' => 5, 'limit_expression' => 'LIMIT 5 OFFSET 5', 'scenario' => 'UPDATE WHERE large OFFSET can select a trailing match'],
            ['section' => 'wherelimit-3.11', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=1 WHERE x=%d ORDER BY x LIMIT 50 OFFSET 30', 'where_x' => 1, 'set_y' => 1, 'order' => 'x', 'limit' => 50, 'offset' => 30, 'limit_expression' => 'LIMIT 50 OFFSET 30', 'scenario' => 'UPDATE WHERE offset beyond matches mutates no rows'],
            ['section' => 'wherelimit-3.12', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=1 WHERE x=%d ORDER BY x LIMIT 30, 50', 'where_x' => 2, 'set_y' => 1, 'order' => 'x', 'limit' => 50, 'offset' => 30, 'limit_expression' => 'LIMIT 30, 50', 'scenario' => 'UPDATE WHERE comma offset beyond matches mutates no rows'],
            ['section' => 'wherelimit-3.13', 'kind' => 'rowid-update', 'operation' => 'UPDATE', 'statement' => 'UPDATE t1 SET y=1 WHERE x=%d ORDER BY x LIMIT 50 OFFSET 50', 'where_x' => 3, 'set_y' => 1, 'order' => 'x', 'limit' => 50, 'offset' => 50, 'limit_expression' => 'LIMIT 50 OFFSET 50', 'scenario' => 'UPDATE WHERE large OFFSET leaves all matching rows unchanged'],
            ['section' => 'wherelimit-4.2', 'kind' => 'view-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM tv WHERE 1 LIMIT 2', 'order' => '', 'limit' => 2, 'offset' => 0, 'limit_expression' => 'LIMIT 2', 'scenario' => 'DELETE LIMIT against an INSTEAD OF trigger view is allowed'],
            ['section' => 'wherelimit-4.3', 'kind' => 'view-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM tv WHERE 1 ORDER BY a LIMIT 2', 'order' => 'a', 'limit' => 2, 'offset' => 0, 'limit_expression' => 'LIMIT 2', 'scenario' => 'DELETE ORDER BY LIMIT against an INSTEAD OF trigger view is allowed'],
            ['section' => 'wherelimit-4.11/4.12', 'kind' => 'without-rowid-delete', 'operation' => 'DELETE', 'statement' => 'DELETE FROM t3 WHERE a=5 LIMIT 2', 'where_a' => 5, 'limit' => 2, 'offset' => 0, 'limit_expression' => 'LIMIT 2', 'scenario' => 'DELETE LIMIT against a WITHOUT ROWID primary-key b-tree is allowed'],
        ];
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function caseFromTemplate(int $case, int $batch, array $template): array
    {
        return match ($template['kind']) {
            'syntax-error' => self::syntaxErrorCase($case, $batch, $template),
            'view-delete' => self::viewDeleteCase($case, $batch, $template),
            'without-rowid-delete' => self::withoutRowidDeleteCase($case, $batch, $template),
            'rowid-delete', 'rowid-update' => self::rowidMutationCase($case, $batch, $template),
            default => throw new \InvalidArgumentException('Unsupported SQLite wherelimit template kind'),
        };
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function syntaxErrorCase(int $case, int $batch, array $template): array
    {
        $rows = self::rowidGrid(4, 4);

        return self::baseCase($case, $batch, $template) + [
            'statement' => $template['statement'],
            'result_code' => 1,
            'error' => $template['error'],
            'grid_x_count' => 4,
            'grid_y_count' => 4,
            'row_count_before' => count($rows),
            'row_count_after' => count($rows),
            'qualified_rows' => 0,
            'affected_rows' => 0,
            'selected_rowids' => [],
            'mutation_rowids' => [],
            'selected_pairs' => [],
            'returning_rows' => [],
            'uses_returning' => false,
            'uses_limit_clause' => str_contains((string) $template['statement'], 'LIMIT'),
            'uses_order_by' => str_contains((string) $template['statement'], 'ORDER BY'),
            'uses_temp_order_btree' => false,
            'uses_rowid_btree' => true,
            'uses_view_trigger' => false,
            'uses_without_rowid_btree' => false,
            'normalized_limit' => null,
            'normalized_offset' => 0,
            'integrity' => 'ok',
            'detail' => 'upstream catchsql error is preserved without mutating the rowid table',
        ];
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function rowidMutationCase(int $case, int $batch, array $template): array
    {
        $xCount = 6 + (($batch - 1) % 3);
        $yCount = 6 + ($batch % 2);
        $rows = self::rowidGrid($xCount, $yCount);
        $whereX = self::whereX($template['where_x'], $xCount, $batch);
        $where = static fn (array $row): bool => $whereX === null || $row['x'] === $whereX;
        $orderBy = self::orderBy((string) $template['order']);
        $limit = is_int($template['limit']) ? $template['limit'] : null;
        $offset = (int) $template['offset'];
        $statement = self::formatStatement((string) $template['statement'], $whereX);
        if ($template['kind'] === 'rowid-delete') {
            $plan = SQLiteUpdateDeleteLimitPlan::delete($rows, $where, $orderBy, $limit, $offset);
        } else {
            $plan = SQLiteUpdateDeleteLimitPlan::update($rows, $where, ['y' => (int) $template['set_y']], $orderBy, $limit, $offset);
        }

        $summary = $plan->toArray();
        $selectedPairs = self::pairs($plan->selectedRows);
        $returningRows = ($template['returning'] ?? false)
            ? $plan->returningRows(['x', 'y', 'separator' => static fn (): string => '|'])
            : [];
        $normalizedLimit = $limit !== null && $limit < 0 ? null : $limit;

        return self::baseCase($case, $batch, $template) + [
            'statement' => $statement,
            'result_code' => 0,
            'error' => null,
            'grid_x_count' => $xCount,
            'grid_y_count' => $yCount,
            'row_count_before' => count($rows),
            'row_count_after' => count($plan->resultRows),
            'qualified_rows' => $summary['qualified_rows'],
            'affected_rows' => $summary['selected_rows'],
            'selected_rowids' => $summary['selected_ids'],
            'mutation_rowids' => $summary['mutation_ids'],
            'selected_pairs' => $selectedPairs,
            'first_selected_pair' => $selectedPairs[0] ?? null,
            'last_selected_pair' => $selectedPairs === [] ? null : $selectedPairs[count($selectedPairs) - 1],
            'returning_rows' => $returningRows,
            'uses_returning' => (bool) ($template['returning'] ?? false),
            'uses_limit_clause' => $limit !== null,
            'uses_order_by' => $orderBy !== [],
            'uses_temp_order_btree' => $orderBy !== [] && $limit !== null,
            'uses_rowid_btree' => true,
            'uses_view_trigger' => false,
            'uses_without_rowid_btree' => false,
            'where_x' => $whereX,
            'order_by' => $summary['order_by'],
            'limit_value' => $limit,
            'offset_value' => $offset,
            'normalized_limit' => $normalizedLimit,
            'normalized_offset' => max(0, $offset),
            'limit_expression' => (string) $template['limit_expression'],
            'integrity' => 'ok',
            'detail' => 'rowid table mutation selection is delegated to SQLiteUpdateDeleteLimitPlan',
        ];
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function viewDeleteCase(int $case, int $batch, array $template): array
    {
        $t1 = [['rowid' => 1, 'a' => 1], ['rowid' => 2, 'a' => 2], ['rowid' => 3, 'a' => 3]];
        $t2 = [['rowid' => 1, 'a' => 101], ['rowid' => 2, 'a' => 102], ['rowid' => 3, 'a' => 103]];
        $viewRows = [];
        foreach ($t2 as $row) {
            $viewRows[] = ['rowid' => 't2:' . $row['rowid'], 'trigger_rowid' => $row['rowid'], 'source' => 't2', 'a' => $row['a']];
        }
        foreach ($t1 as $row) {
            $viewRows[] = ['rowid' => 't1:' . $row['rowid'], 'trigger_rowid' => $row['rowid'], 'source' => 't1', 'a' => $row['a']];
        }

        $orderBy = self::orderBy((string) $template['order']);
        $plan = SQLiteUpdateDeleteLimitPlan::delete(
            $viewRows,
            static fn (array $row): bool => true,
            $orderBy,
            (int) $template['limit'],
            (int) $template['offset'],
        );
        $deletedRowids = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['trigger_rowid'],
            $plan->selectedRows,
        )));
        $deleted = array_fill_keys($deletedRowids, true);
        $remainingT1 = array_values(array_filter($t1, static fn (array $row): bool => !isset($deleted[$row['rowid']])));
        $remainingT2 = array_values(array_filter($t2, static fn (array $row): bool => !isset($deleted[$row['rowid']])));

        return self::baseCase($case, $batch, $template) + [
            'statement' => $template['statement'],
            'result_code' => 0,
            'error' => null,
            'grid_x_count' => 0,
            'grid_y_count' => 0,
            'row_count_before' => count($viewRows),
            'row_count_after' => count($remainingT1) + count($remainingT2),
            'qualified_rows' => count($viewRows),
            'affected_rows' => count($plan->selectedRows),
            'selected_rowids' => $plan->selectedIds,
            'mutation_rowids' => $plan->mutationIds,
            'selected_pairs' => array_map(
                static fn (array $row): array => [$row['source'], $row['trigger_rowid'], $row['a']],
                $plan->selectedRows,
            ),
            'returning_rows' => [],
            'uses_returning' => false,
            'uses_limit_clause' => true,
            'uses_order_by' => $orderBy !== [],
            'uses_temp_order_btree' => $orderBy !== [],
            'uses_rowid_btree' => false,
            'uses_view_trigger' => true,
            'uses_without_rowid_btree' => false,
            'limit_value' => (int) $template['limit'],
            'offset_value' => (int) $template['offset'],
            'normalized_limit' => (int) $template['limit'],
            'normalized_offset' => (int) $template['offset'],
            'limit_expression' => (string) $template['limit_expression'],
            'trigger_deleted_rowids' => $deletedRowids,
            'remaining_t1_rows' => array_column($remainingT1, 'a'),
            'remaining_t2_rows' => array_column($remainingT2, 'a'),
            'integrity' => 'ok',
            'detail' => 'INSTEAD OF DELETE trigger applies selected view rowids to both base tables',
        ];
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function withoutRowidDeleteCase(int $case, int $batch, array $template): array
    {
        $rows = [
            ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4],
            ['a' => 5, 'b' => 6, 'c' => 7, 'd' => 8],
            ['a' => 9, 'b' => 10, 'c' => 11, 'd' => 12],
        ];
        $plan = SQLiteUpdateDeleteLimitPlan::delete(
            $rows,
            static fn (array $row): bool => $row['a'] === 5,
            [],
            (int) $template['limit'],
            (int) $template['offset'],
            'a',
        );

        return self::baseCase($case, $batch, $template) + [
            'statement' => $template['statement'],
            'result_code' => 0,
            'error' => null,
            'grid_x_count' => 0,
            'grid_y_count' => 0,
            'row_count_before' => count($rows),
            'row_count_after' => count($plan->resultRows),
            'qualified_rows' => count($plan->qualifiedRows),
            'affected_rows' => count($plan->selectedRows),
            'selected_rowids' => $plan->selectedIds,
            'mutation_rowids' => $plan->mutationIds,
            'selected_pairs' => array_map(static fn (array $row): array => [$row['a'], $row['b'], $row['c'], $row['d']], $plan->selectedRows),
            'remaining_rows' => array_map(static fn (array $row): array => [$row['a'], $row['b'], $row['c'], $row['d']], $plan->resultRows),
            'returning_rows' => [],
            'uses_returning' => false,
            'uses_limit_clause' => true,
            'uses_order_by' => false,
            'uses_temp_order_btree' => false,
            'uses_rowid_btree' => false,
            'uses_view_trigger' => false,
            'uses_without_rowid_btree' => true,
            'primary_key' => ['a', 'b'],
            'limit_value' => (int) $template['limit'],
            'offset_value' => (int) $template['offset'],
            'normalized_limit' => (int) $template['limit'],
            'normalized_offset' => (int) $template['offset'],
            'limit_expression' => (string) $template['limit_expression'],
            'integrity' => 'ok',
            'detail' => 'WITHOUT ROWID primary-key b-tree delete preserves upstream wherelimit-4.12 ordering',
        ];
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function baseCase(int $case, int $batch, array $template): array
    {
        return [
            'source' => self::SOURCE,
            'case' => $case,
            'upstream_file' => 'test/wherelimit.test',
            'upstream_section' => $template['section'],
            'batch' => $batch,
            'scenario' => $template['scenario'] . ' dynamic batch ' . $batch,
            'operation' => $template['operation'],
            'target_kind' => $template['kind'],
        ];
    }

    /**
     * @return list<array{rowid:int,x:int,y:int}>
     */
    private static function rowidGrid(int $xCount, int $yCount): array
    {
        $rows = [];
        $rowid = 1;
        for ($x = 1; $x <= $xCount; $x++) {
            for ($y = 1; $y <= $yCount; $y++) {
                $rows[] = ['rowid' => $rowid++, 'x' => $x, 'y' => $y];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{column:string,direction?:string}>
     */
    private static function orderBy(string $order): array
    {
        return match ($order) {
            '' => [],
            'a' => [['column' => 'a'], ['column' => 'rowid']],
            'x' => [['column' => 'x'], ['column' => 'rowid']],
            'x,y' => [['column' => 'x'], ['column' => 'y'], ['column' => 'rowid']],
            default => throw new \InvalidArgumentException("Unsupported SQLite wherelimit ORDER BY template {$order}"),
        };
    }

    private static function whereX(mixed $whereX, int $xCount, int $batch): ?int
    {
        if ($whereX === null) {
            return null;
        }
        if (!is_int($whereX)) {
            throw new \InvalidArgumentException('SQLite wherelimit WHERE x template must be an integer or NULL');
        }

        return (($whereX - 1 + $batch - 1) % $xCount) + 1;
    }

    private static function formatStatement(string $statement, ?int $whereX): string
    {
        return str_contains($statement, '%d') ? sprintf($statement, $whereX) : $statement;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{x:int,y:int,rowid:int}>
     */
    private static function pairs(array $rows): array
    {
        return array_map(
            static fn (array $row): array => ['x' => (int) $row['x'], 'y' => (int) $row['y'], 'rowid' => (int) $row['rowid']],
            $rows,
        );
    }
}
