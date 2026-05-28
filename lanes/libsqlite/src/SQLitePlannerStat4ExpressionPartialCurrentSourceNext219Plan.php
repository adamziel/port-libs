<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext219Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext217Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $lookahead = SQLitePlannerStat4ExpressionPartialCurrentSourceNext217Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit + 1,
            $offset,
        );
        $peerFence = self::peerRunFence(self::matchedRows($base), self::matchedRows($lookahead), $limit, $offset);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next217-ready'
            && ($lookahead['status'] ?? null) === 'stat4-expression-partial-current-source-next217-ready'
            && $peerFence['ready'] === true
            && $peerFence['rowidsRejectedByPeerFence'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next219-ready' : 'requires-current-source-stat4-peer-run-reprepare',
            'stat4PeerRunFence' => $peerFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next219Ready' => $ready,
                'next219BoundaryKey' => $peerFence['boundaryKey'],
                'next219BoundaryRowids' => $peerFence['boundaryPeerRowids'],
                'next219BoundaryPageRowids' => $peerFence['boundaryPeerRowidsOnPage'],
                'next219BoundaryRemainingRowids' => $peerFence['boundaryPeerRowidsAfterPage'],
                'next219PeerRunSignature' => $peerFence['peerRunSignature'],
                'next219ProofSignature' => $peerFence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next219PeerRunSignature' => $peerFence['peerRunSignature'],
                'next219ProofSignature' => $peerFence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $peerFence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT219 PEER-RUN YIELD FENCE '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' CURRENT PEER RUN PROVED' : ' REQUIRES PEER-RUN REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext217Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next219',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next219 composes existing current-source STAT4 expression partial cursor fences and adds duplicate expression-key peer-run boundary proof',
            'non_overlap' => 'avoids accepted next217 current/next page lookahead, next212 grouped LIKE proof, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only proves duplicate expression-key peer runs at a current-source STAT4 partial expression cursor page boundary',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $pageRows
     * @param list<array<string,mixed>> $lookaheadRows
     * @return array<string,mixed>
     */
    private static function peerRunFence(array $pageRows, array $lookaheadRows, int $limit, int $offset): array
    {
        if ($limit < 0 || $offset < 0) {
            throw new \InvalidArgumentException('SQLite next219 limit and offset must be non-negative');
        }

        $pageRowids = array_map(static fn (array $row): int => self::rowid($row), $pageRows);
        $lookaheadRowids = array_map(static fn (array $row): int => self::rowid($row), $lookaheadRows);
        $lastPageRow = $pageRows === [] ? null : $pageRows[array_key_last($pageRows)];
        $boundaryKey = is_array($lastPageRow) ? self::expressionKey($lastPageRow) : null;
        $peerRows = [];
        $peerRowids = [];
        $onPage = [];
        $afterPage = [];
        $rejected = [];
        $nextRow = $lookaheadRows[count($pageRows)] ?? null;
        $nextRowid = is_array($nextRow) ? self::rowid($nextRow) : null;
        $nextKey = is_array($nextRow) ? self::expressionKey($nextRow) : null;

        if ($boundaryKey !== null) {
            foreach ($lookaheadRows as $position => $row) {
                if (self::expressionKey($row) !== $boundaryKey) {
                    continue;
                }
                $rowid = self::rowid($row);
                $peerRows[] = [
                    'position' => $position,
                    'rowid' => $rowid,
                    'expressionKey' => $boundaryKey,
                    'onPage' => $position < count($pageRows),
                    'afterPage' => $position >= count($pageRows),
                ];
                $peerRowids[] = $rowid;
                if ($position < count($pageRows)) {
                    $onPage[] = $rowid;
                }
                if ($position >= count($pageRows)) {
                    $afterPage[] = $rowid;
                }
            }
            if ($afterPage !== [] && ($nextKey !== $boundaryKey || $nextRowid !== $afterPage[0])) {
                $rejected[] = $nextRowid;
            }
        }

        $complete = $boundaryKey === null || $afterPage === [] || ($nextKey === $boundaryKey && $nextRowid === $afterPage[0]);
        $proof = [
            'limit' => $limit,
            'offset' => $offset,
            'pageRowids' => $pageRowids,
            'lookaheadRowids' => $lookaheadRowids,
            'boundaryKey' => $boundaryKey,
            'boundaryPeerRows' => $peerRows,
            'boundaryPeerRowids' => $peerRowids,
            'boundaryPeerRowidsOnPage' => $onPage,
            'boundaryPeerRowidsAfterPage' => $afterPage,
            'nextRowid' => $nextRowid,
            'nextKey' => $nextKey,
        ];

        return $proof + [
            'ready' => $complete && $rejected === [],
            'boundaryContinuesAfterPage' => $afterPage !== [],
            'nextRowContinuesBoundaryPeerRun' => $afterPage === [] || ($nextKey === $boundaryKey && $nextRowid === $afterPage[0]),
            'rowidsRejectedByPeerFence' => array_values(array_filter($rejected, static fn (mixed $rowid): bool => $rowid !== null)),
            'peerRunSignature' => self::signature([$boundaryKey, $peerRowids, $onPage, $afterPage]),
            'proofSignature' => self::signature($proof),
        ];
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next219 needs matched row list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next219 matched rows must be arrays');
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

        throw new \InvalidArgumentException('SQLite next219 matched rows need expressionKey or option_name payload');
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next219 matched rowid must be an integer');
        }

        return (int) $row['rowid'];
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param array<string,mixed> $peerFence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $peerFence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'RecheckCurrentSourceStat4PeerRun',
            'mode' => 'next219-current-source-stat4-expression-partial-peer-run-yield',
            'boundaryKey' => $peerFence['boundaryKey'],
            'boundaryPeerRowids' => $peerFence['boundaryPeerRowids'],
            'boundaryPeerRowidsAfterPage' => $peerFence['boundaryPeerRowidsAfterPage'],
            'signature' => $peerFence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
