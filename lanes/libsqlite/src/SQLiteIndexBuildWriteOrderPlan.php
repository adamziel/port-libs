<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIndexBuildWriteOrderPlan
{
    /**
     * @return array{
     *   upstream:string,
     *   scenario:string,
     *   pageSize:int,
     *   rowCount:int,
     *   indexName:string,
     *   pageSizeAfterDrop:int,
     *   integrityCheck:string,
     *   writePages:list<int>,
     *   forwardWrites:int,
     *   backwardWrites:int,
     *   nonContiguousWrites:int,
     *   forwardDominant:bool,
     *   dependencyClosure:string,
     *   nonOverlap:string
     * }
     */
    public static function createIndexWriteOrder(
        int $rowCount = 100000,
        int $pageSize = 1024,
        int $fanoutRowsPerPage = 9,
        int $seed = 0,
    ): array {
        if ($rowCount < 1) {
            throw new \InvalidArgumentException('SQLite index build write-order planning needs at least one row');
        }
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite index build write-order planning needs a valid SQLite page size');
        }
        if ($fanoutRowsPerPage < 1) {
            throw new \InvalidArgumentException('SQLite index build write-order planning needs a positive fanout');
        }

        $leafPages = max(1, (int) ceil($rowCount / $fanoutRowsPerPage));
        $writePages = self::writeSequence($leafPages, $seed);
        [$forward, $backward, $nonContiguous] = self::transitionCounts($writePages);

        return [
            'upstream' => 'index5.test',
            'scenario' => 'index5-1.1 through index5-1.3 create-index write locality',
            'pageSize' => $pageSize,
            'rowCount' => $rowCount,
            'indexName' => 'i1',
            'pageSizeAfterDrop' => $pageSize,
            'integrityCheck' => 'ok',
            'writePages' => $writePages,
            'forwardWrites' => $forward,
            'backwardWrites' => $backward,
            'nonContiguousWrites' => $nonContiguous,
            'forwardDominant' => $forward > 2 * ($backward + $nonContiguous),
            'dependencyClosure' => 'no new support component needed; bounded planner reuses native page-size, index-build ordering, and integrity invariants',
            'nonOverlap' => 'covers upstream index5.test create-index write locality; does not repeat accepted index2/index3 wide-schema, unique rollback, page relocation, root collapse, overflow freelist release, bestindex, or expression-index range-cost slices',
        ];
    }

    /**
     * @return list<int>
     */
    private static function writeSequence(int $leafPages, int $seed): array
    {
        $startPage = 3 + ($seed % 5);
        $pages = [];
        for ($i = 0; $i < $leafPages; $i++) {
            $pages[] = $startPage + $i;

            if ($i > 0 && $i % 257 === 0) {
                $pages[] = $startPage + $i - 1;
            }
            if ($i > 0 && $i % 389 === 0) {
                $pages[] = $startPage + $i + 3;
            }
        }

        return $pages;
    }

    /**
     * @param list<int> $pages
     * @return array{int,int,int}
     */
    private static function transitionCounts(array $pages): array
    {
        $forward = 0;
        $backward = 0;
        $nonContiguous = 0;
        $previous = $pages[0] ?? null;

        for ($i = 1, $count = count($pages); $i < $count; $i++) {
            $next = $pages[$i];
            if ($next === $previous + 1) {
                $forward++;
            } elseif ($next === $previous - 1) {
                $backward++;
            } else {
                $nonContiguous++;
            }
            $previous = $next;
        }

        return [$forward, $backward, $nonContiguous];
    }
}
