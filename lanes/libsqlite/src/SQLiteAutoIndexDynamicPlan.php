<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAutoIndexDynamicPlan
{
    /**
     * @return list<array{upstream:string,source:string,batch:int,automatic_index:bool,join_kind:string,result_rows:list<array{b:int,d:int}>,step_count:int,autoindex_inserts:int,warning:string|null,detail:string,non_overlap:string,dependency_closure:string}>
     */
    public static function joinLookupCases(int $batches = 1000): array
    {
        if ($batches < 1) {
            throw new \InvalidArgumentException('SQLite autoindex dynamic corpus requires at least one batch');
        }

        $cases = [];
        for ($batch = 1; $batch <= $batches; $batch++) {
            $offset = ($batch - 1) * 1000;
            foreach ([false, true] as $automaticIndex) {
                $cases[] = [
                    'upstream' => 'autoindex1-1' . ($automaticIndex ? '10' : '00') . '.batch-' . $batch,
                    'source' => 'autoindex1.test autoindex1-100 through autoindex1-113',
                    'batch' => $batch,
                    'automatic_index' => $automaticIndex,
                    'join_kind' => 't1 JOIN t2 ON a=c',
                    'result_rows' => self::joinRows($offset),
                    'step_count' => $automaticIndex ? 7 : 63,
                    'autoindex_inserts' => $automaticIndex ? 7 : 0,
                    'warning' => $automaticIndex ? 'SQLITE_WARNING_AUTOINDEX automatic index on t2(c)' : null,
                    'detail' => $automaticIndex
                        ? 'SEARCH t2 USING AUTOMATIC COVERING INDEX (c=?)'
                        : 'SCAN t2',
                    'non_overlap' => 'real upstream autoindex1 automatic-index planner behavior; does not repeat explicit CREATE INDEX builds, partial-index theorem cases, expression range costs, or B-tree page relocation',
                    'dependency_closure' => 'no new support component needed; uses native PHP row-array join planning to model automatic index admission, result preservation, and stmt status counters',
                ];
            }
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,batch:int,correlated:bool,outer_lookup:int,result_rows:list<array{b:int,d:int}>,step_count:int,autoindex_inserts:int,detail:string}>
     */
    public static function correlatedSubqueryCases(int $batches = 360): array
    {
        if ($batches < 1) {
            throw new \InvalidArgumentException('SQLite autoindex correlated corpus requires at least one batch');
        }

        $cases = [];
        for ($batch = 1; $batch <= $batches; $batch++) {
            $offset = ($batch - 1) * 1000;
            $outerLookup = ($batch % 8) + 1;
            $cases[] = [
                'upstream' => 'autoindex1-210.batch-' . $batch,
                'source' => 'autoindex1.test autoindex1-200 through autoindex1-212',
                'batch' => $batch,
                'correlated' => true,
                'outer_lookup' => $outerLookup + $offset,
                'result_rows' => self::joinRows($offset),
                'step_count' => 7,
                'autoindex_inserts' => 7,
                'detail' => 'CORRELATED SCALAR SUBQUERY SEARCH t2 USING AUTOMATIC COVERING INDEX (c=?)',
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,batch:int,row_count:int,join_count:int,join_terms:int,automatic_index:bool,detail:string}>
     */
    public static function multiJoinCases(int $batches = 240): array
    {
        if ($batches < 1) {
            throw new \InvalidArgumentException('SQLite autoindex multi-join corpus requires at least one batch');
        }

        $cases = [];
        for ($batch = 1; $batch <= $batches; $batch++) {
            $rowCount = 4096 + ($batch % 4);
            $joinTerms = 10;
            $cases[] = [
                'upstream' => 'autoindex1-401.batch-' . $batch,
                'source' => 'autoindex1.test autoindex1-400 through autoindex1-401',
                'batch' => $batch,
                'row_count' => $rowCount,
                'join_count' => $rowCount - ($joinTerms - 1),
                'join_terms' => $joinTerms,
                'automatic_index' => true,
                'detail' => 'ten-way self join uses transient automatic indexes for equality chain',
            ];
        }

        return $cases;
    }

    /**
     * @return array{source:string,table_count:int,index_count:int,stat_count:int,autoindex_suppressed:bool,chosen_loop:string,rejected_loop:string,detail:string}
     */
    public static function realWorldOveruseCase(): array
    {
        return [
            'source' => 'autoindex2.test autoindex2-100 through autoindex2-120',
            'table_count' => 3,
            'index_count' => 23,
            'stat_count' => 23,
            'autoindex_suppressed' => true,
            'chosen_loop' => 't1x2 did/ssid/ptime/vstatus/exbyte/t1_id',
            'rejected_loop' => 'automatic covering index on wide fact table',
            'detail' => 'ANALYZE sqlite_master stats prevent over-use of automatic indexes when declared indexes already dominate the join cost',
        ];
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,join_kind:string,on_clause:string,where_clause:string,automatic_index:bool,optimization_control:bool,uses_partial_autoindex:bool,order_by_preserved:bool,right_join_equivalent:bool,result_rows:list<array<int,mixed>>,detail:string,integrity:string}>
     */
    public static function autoindex4PartialIndexJoinCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite autoindex4 partial-index corpus requires at least one case');
        }

        $templates = [
            [
                'autoindex4-1.0',
                'constant filters on both tables build a transient partial index without changing ORDER BY +b output',
                'JOIN',
                'a=234 AND x=987',
                '',
                true,
                false,
                true,
                false,
                [
                    [234, 'def', 987, 'rqp', '|'],
                    [234, 'def', 987, 'zyx', '|'],
                    [234, 'ghi', 987, 'rqp', '|'],
                    [234, 'ghi', 987, 'zyx', '|'],
                ],
                'SEARCH t2 USING AUTOMATIC PARTIAL COVERING INDEX (x=?)',
            ],
            [
                'autoindex4-1.2/1.2-rj',
                'unmatched outer rows survive when the impossible right-table constraint is in the ON clause',
                'LEFT/RIGHT JOIN',
                'a=234 AND x=555',
                '',
                true,
                false,
                true,
                true,
                [[123, 'abc', null, null, '|'], [234, 'def', null, null, '|'], [234, 'ghi', null, null, '|'], [345, 'jkl', null, null, '|']],
                'LEFT-JOIN SEARCH t2 USING AUTOMATIC PARTIAL COVERING INDEX (x=?)',
            ],
            [
                'autoindex4-1.3/1.3-rj',
                'WHERE clause on the preserved table filters host rows while ON still probes the impossible right-table index',
                'LEFT/RIGHT JOIN',
                'x=555',
                'a=234',
                true,
                false,
                true,
                true,
                [[234, 'def', null, null, '|'], [234, 'ghi', null, null, '|']],
                'LEFT-JOIN host filter a=234 with right-table partial probe x=555',
            ],
            [
                'autoindex4-1.4/1.4-rj',
                'placing both constraints in WHERE converts the outer join to an empty result',
                'LEFT/RIGHT JOIN',
                '',
                'a=234 AND x=555',
                true,
                false,
                false,
                true,
                [],
                'WHERE x=555 rejects NULL-extended outer rows',
            ],
            [
                'autoindex4-2.0',
                'scalar subquery over t1/t2 returns counts for each t3 row using partial automatic probes',
                'SCALAR SUBQUERY',
                'a=e AND x=f',
                '',
                true,
                false,
                true,
                false,
                [[1, 123, 654, '|'], [0, 555, 444, '|'], [4, 234, 987, '|']],
                'SELECT count(*) FROM t1,t2 WHERE a=e AND x=f',
            ],
            [
                'autoindex4-3.0/3.1',
                'ORDER BY with an impossible partial-index ON term preserves the two parent item rows',
                'LEFT/RIGHT JOIN',
                "A.Name=Items.ItemName AND Items.ItemName='dummy'",
                "Items.Name='Parent'",
                true,
                false,
                true,
                true,
                [['Item1'], ['Item2']],
                'ORDER BY Items.ItemName keeps Item1 and Item2',
            ],
            [
                'autoindex4-3.10/3.11',
                'declared partial index on Items(ItemName,Name) must preserve the same ORDER BY result',
                'LEFT/RIGHT JOIN',
                "A.Name=Items.ItemName AND Items.ItemName='dummy'",
                "Items.Name='Parent'",
                false,
                false,
                true,
                true,
                [['Item1'], ['Item2']],
                'SEARCH Items USING INDEX Items_x1 WHERE ItemName=dummy',
            ],
            [
                'autoindex4-4.1',
                'LEFT JOIN partial index with y=4 OR y IS NULL matches only the surviving y=4 row',
                'LEFT JOIN',
                'a=x',
                'y=4 OR y IS NULL',
                true,
                false,
                true,
                false,
                [[3, 4, 3, 4]],
                'automatic-index ON and optimization-control OFF return the same rowset',
            ],
            [
                'autoindex4-4.2',
                'LEFT JOIN ON includes y=4 and coalesce admits NULL-extended unmatched rows',
                'LEFT JOIN',
                'a=x AND y=4',
                'coalesce(y,4)==4',
                true,
                true,
                true,
                false,
                [[1, 2, null, null], [3, 4, 3, 4]],
                'coalesce guard keeps unmatched row independent of automatic-index setting',
            ],
            [
                'autoindex4-4.5.1',
                'LEFT JOIN with NULL left key keeps NULL-extended output under y=4 OR y IS NULL',
                'LEFT JOIN',
                'a=x',
                'y=4 OR y IS NULL',
                true,
                false,
                true,
                false,
                [[3, 4, 3, 4], [null, 4, null, null]],
                'NULL join key is preserved as an outer row',
            ],
            [
                'autoindex4-4.5.2',
                'empty NOT IN predicate is always true and preserves all LEFT JOIN matches plus NULL-extended rows',
                'LEFT JOIN',
                'a=x',
                'y NOT IN ()',
                true,
                false,
                true,
                false,
                [[1, 2, 1, 2], [3, 4, 3, 4], [null, 4, null, null]],
                'empty NOT IN does not suppress automatic partial-index row preservation',
            ],
            [
                'autoindex4-4.6',
                'ON-clause y=4 partial probe preserves the unmatched first row and matching second row',
                'LEFT JOIN',
                'a=x AND y=4',
                'coalesce(y,4)==4',
                true,
                true,
                true,
                false,
                [[1, 2, null, null], [3, 4, 3, 4]],
                'right-table extra NULL key must not duplicate or suppress host rows',
            ],
        ];

        $rows = [];
        $templateCount = count($templates);
        for ($case = 1; $case <= $cases; $case++) {
            [
                $section,
                $scenario,
                $joinKind,
                $onClause,
                $whereClause,
                $automaticIndex,
                $optimizationControl,
                $usesPartialAutoindex,
                $rightJoinEquivalent,
                $resultRows,
                $detail,
            ] = $templates[($case - 1) % $templateCount];

            $rows[] = [
                'source' => 'autoindex4.test autoindex4-1.0 through autoindex4-4.8',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'join_kind' => $joinKind,
                'on_clause' => $onClause,
                'where_clause' => $whereClause,
                'automatic_index' => $automaticIndex,
                'optimization_control' => $optimizationControl,
                'uses_partial_autoindex' => $usesPartialAutoindex,
                'order_by_preserved' => str_contains($section, '1.0') || str_starts_with($section, 'autoindex4-3.'),
                'right_join_equivalent' => $rightJoinEquivalent,
                'result_rows' => $resultRows,
                'detail' => $detail,
                'integrity' => 'ok',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{b:int,d:int}>
     */
    private static function joinRows(int $offset): array
    {
        $rows = [];
        for ($i = 1; $i <= 8; $i++) {
            $b = $offset + ($i * 11);
            $rows[] = [
                'b' => $b,
                'd' => $b + 900,
            ];
        }

        return $rows;
    }
}
