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
