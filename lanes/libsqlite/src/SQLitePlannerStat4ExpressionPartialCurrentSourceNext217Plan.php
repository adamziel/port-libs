<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext217Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $whereTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext212Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $lookahead = SQLitePlannerStat4ExpressionPartialCurrentSourceNext212Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit + 1,
            $offset,
        );
        $yieldFence = self::yieldFence(self::matchedRows($base), self::matchedRows($lookahead), $limit, $offset);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next212-ready'
            && ($lookahead['status'] ?? null) === 'stat4-expression-partial-current-source-next212-ready'
            && $yieldFence['currentPageMatchesLookaheadPrefix'] === true
            && $yieldFence['currentNextPairsPreserveExpressionOrder'] === true
            && $yieldFence['rowidsRejectedByYieldFence'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next217-ready' : 'requires-current-source-stat4-yield-reprepare',
            'stat4YieldFence' => $yieldFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next217Ready' => $ready,
                'next217CurrentNextYieldSignature' => $yieldFence['currentNextYieldSignature'],
                'next217ProofSignature' => $yieldFence['proofSignature'],
                'next217ResumeAfterRowid' => $yieldFence['resumeAfterRowid'],
                'next217NextRowid' => $yieldFence['nextRowid'],
                'next217RowsRejectedByYieldFence' => $yieldFence['rowidsRejectedByYieldFence'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next217CurrentNextYieldSignature' => $yieldFence['currentNextYieldSignature'],
                'next217ProofSignature' => $yieldFence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $yieldFence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT217 CURRENT/NEXT YIELD FENCE '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' CURRENT/NEXT STREAM PROVED' : ' REQUIRES CURRENT/NEXT YIELD REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext212Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next217',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next217 composes existing current-source STAT4 expression partial fences and adds a one-row lookahead yield proof for resumable cursors',
            'non_overlap' => 'avoids accepted next210 peer rowid fences, next211 seek windows, next212 grouped LIKE arm proof, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only proves the current page and next lookahead row for a current-source STAT4 partial expression cursor yield',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $pageRows
     * @param list<array<string,mixed>> $lookaheadRows
     * @return array<string,mixed>
     */
    private static function yieldFence(array $pageRows, array $lookaheadRows, int $limit, int $offset): array
    {
        if ($limit < 0 || $offset < 0) {
            throw new \InvalidArgumentException('SQLite next217 limit and offset must be non-negative');
        }

        $pageRowids = array_map(static fn (array $row): int => self::rowid($row), $pageRows);
        $lookaheadRowids = array_map(static fn (array $row): int => self::rowid($row), $lookaheadRows);
        $lookaheadPrefix = array_slice($lookaheadRowids, 0, count($pageRowids));
        $pairs = [];
        $rejected = [];
        $previousKey = null;
        $previousRowid = null;
        foreach ($lookaheadRows as $position => $row) {
            $key = self::expressionKey($row);
            $rowid = self::rowid($row);
            $relation = 'first';
            $ordered = true;
            if ($previousKey !== null && $previousRowid !== null) {
                $comparison = strcmp($previousKey, $key);
                $ordered = $comparison > 0 || ($comparison === 0 && $previousRowid <= $rowid);
                $relation = $comparison === 0 ? 'peer-rowid' : 'descending-expression';
                if (!$ordered) {
                    $rejected[] = $rowid;
                }
            }
            $pairs[] = [
                'position' => $position,
                'rowid' => $rowid,
                'expressionKey' => $key,
                'previousRowid' => $previousRowid,
                'previousExpressionKey' => $previousKey,
                'relation' => $relation,
                'orderedAfterPrevious' => $ordered,
            ];
            $previousKey = $key;
            $previousRowid = $rowid;
        }

        $resumeAfterRowid = $pageRowids === [] ? null : $pageRowids[array_key_last($pageRowids)];
        $nextRowid = $lookaheadRowids[count($pageRowids)] ?? null;

        return [
            'limit' => $limit,
            'offset' => $offset,
            'pageRowids' => $pageRowids,
            'lookaheadRowids' => $lookaheadRowids,
            'currentPageMatchesLookaheadPrefix' => $pageRowids === $lookaheadPrefix,
            'hasNextLookaheadRow' => $nextRowid !== null,
            'resumeAfterRowid' => $resumeAfterRowid,
            'nextRowid' => $nextRowid,
            'currentNextPairs' => $pairs,
            'currentNextPairsPreserveExpressionOrder' => $rejected === [],
            'rowidsRejectedByYieldFence' => array_values(array_unique($rejected)),
            'currentNextYieldSignature' => self::signature([$pageRowids, $nextRowid]),
            'proofSignature' => self::signature([$pairs, $resumeAfterRowid, $nextRowid]),
        ];
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next217 needs matched row list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next217 matched rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $row */
    private static function expressionKey(array $row): string
    {
        if (array_key_exists('expressionKey', $row)) {
            return strtolower((string) $row['expressionKey']);
        }
        $payload = $row['payload'] ?? null;
        if (is_array($payload) && array_key_exists('option_name', $payload)) {
            return strtolower((string) $payload['option_name']);
        }

        throw new \InvalidArgumentException('SQLite next217 matched rows need expressionKey or option_name payload');
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next217 matched rowid must be an integer');
        }

        return (int) $row['rowid'];
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param array<string,mixed> $yieldFence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $yieldFence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'RecheckCurrentNextStat4Yield',
            'mode' => 'next217-current-source-stat4-expression-partial-current-next-yield',
            'resumeAfterRowid' => $yieldFence['resumeAfterRowid'],
            'nextRowid' => $yieldFence['nextRowid'],
            'rowids' => $yieldFence['lookaheadRowids'],
            'signature' => $yieldFence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
