<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext210Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext209Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $peerFence = self::peerFence(self::matchedRows($base));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next209-ready'
            && $peerFence['hasDuplicateExpressionPeers'] === true
            && $peerFence['allDuplicateExpressionPeersInRowidOrder'] === true
            && $peerFence['rowidsRejectedByPeerOrderFence'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next210-ready' : 'requires-current-source-expression-peer-reprepare',
            'expressionPeerOrderFence' => $peerFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next210Ready' => $ready,
                'next210DuplicateExpressionPeerKeys' => $peerFence['duplicateExpressionPeerKeys'],
                'next210ExpressionPeerOrderSignature' => $peerFence['peerOrderSignature'],
                'next210RowsRejectedByPeerOrderFence' => $peerFence['rowidsRejectedByPeerOrderFence'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next210ExpressionPeerOrderSignature' => $peerFence['peerOrderSignature'],
                'next210ExpressionPeerProofSignature' => $peerFence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $peerFence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT210 DUPLICATE EXPRESSION PEER ROWID FENCE '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' CURRENT PEERS ORDERED BY ROWID' : ' REQUIRES CURRENT SOURCE PEER REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext209Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next210',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next210 reuses current-source STAT4 expression partial grouped-OR admission and adds duplicate expression-key rowid peer-order fencing',
            'non_overlap' => 'avoids accepted next209 grouped partial OR admission, next208 planner OR behavior, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only proves rowid tie-break ordering for duplicate expression keys inside an admitted current-source STAT4 partial index stream',
        ]);
    }

    /** @param list<array<string,mixed>> $rows @return array<string,mixed> */
    private static function peerFence(array $rows): array
    {
        $groups = [];
        foreach ($rows as $position => $row) {
            $key = self::expressionKey($row);
            $groups[$key][] = [
                'rowid' => self::rowid($row),
                'position' => $position,
                'payload' => self::payload($row),
            ];
        }

        $duplicateGroups = [];
        $rejected = [];
        foreach ($groups as $key => $peers) {
            if (count($peers) < 2) {
                continue;
            }
            $rowids = array_column($peers, 'rowid');
            $sorted = $rowids;
            sort($sorted, SORT_NUMERIC);
            if ($rowids !== $sorted) {
                $rejected = array_values(array_merge($rejected, $rowids));
            }
            $duplicateGroups[] = [
                'expressionKey' => $key,
                'rowids' => $rowids,
                'expectedRowidOrder' => $sorted,
                'positions' => array_column($peers, 'position'),
                'orderedByRowid' => $rowids === $sorted,
            ];
        }

        return [
            'duplicateExpressionPeerGroups' => $duplicateGroups,
            'duplicateExpressionPeerKeys' => array_column($duplicateGroups, 'expressionKey'),
            'hasDuplicateExpressionPeers' => $duplicateGroups !== [],
            'allDuplicateExpressionPeersInRowidOrder' => $duplicateGroups !== [] && $rejected === [],
            'rowidsRejectedByPeerOrderFence' => array_values(array_unique($rejected)),
            'peerOrderSignature' => self::signature(array_map(
                static fn (array $group): array => [
                    'expressionKey' => $group['expressionKey'],
                    'rowids' => $group['rowids'],
                    'expectedRowidOrder' => $group['expectedRowidOrder'],
                ],
                $duplicateGroups,
            )),
            'proofSignature' => self::signature($duplicateGroups),
        ];
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next210 needs matched row list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next210 matched rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $row */
    private static function expressionKey(array $row): string
    {
        if (array_key_exists('expressionKey', $row)) {
            return (string) $row['expressionKey'];
        }
        $payload = self::payload($row);
        if (array_key_exists('option_name', $payload)) {
            return strtolower((string) $payload['option_name']);
        }

        throw new \InvalidArgumentException('SQLite next210 matched rows need expressionKey or option_name payload');
    }

    /** @param array<string,mixed> $row */
    private static function payload(array $row): array
    {
        $payload = $row['payload'] ?? null;
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('SQLite next210 matched rows need payload arrays');
        }

        return $payload;
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next210 matched rowid must be an integer');
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
            'opcode' => 'RecheckDuplicateExpressionPeerRowidOrder',
            'mode' => 'next210-current-source-stat4-expression-partial-peer-rowid-order',
            'duplicateExpressionPeerKeys' => $peerFence['duplicateExpressionPeerKeys'],
            'rowids' => array_merge(...array_map(
                static fn (array $group): array => $group['rowids'],
                $peerFence['duplicateExpressionPeerGroups'],
            )),
            'signature' => $peerFence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
