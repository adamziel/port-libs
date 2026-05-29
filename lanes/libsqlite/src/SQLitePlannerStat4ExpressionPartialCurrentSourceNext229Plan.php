<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext229Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::selectivityFence(
            self::matchedRows($base),
            is_array($base['stat4SampleOrderFence'] ?? null) ? $base['stat4SampleOrderFence'] : [],
            $limit,
            $offset,
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next224-ready'
            && $fence['currentStat4CardinalityBracketsMatchedRows'] === true
            && $fence['pageWindowWithinCurrentStat4Estimate'] === true
            && $fence['samplePeerCountsCoverMatchedPeers'] === true
            && $fence['rowidsRejectedBySelectivityFence'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next229-ready' : 'requires-current-source-stat4-selectivity-reprepare',
            'stat4SelectivityFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next229Ready' => $ready,
                'next229EstimatedRows' => $fence['estimatedRows'],
                'next229MatchedRows' => $fence['matchedRowCount'],
                'next229PageWindow' => $fence['pageWindow'],
                'next229SelectivitySignature' => $fence['selectivitySignature'],
                'next229RowsRejectedBySelectivityFence' => $fence['rowidsRejectedBySelectivityFence'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next229SelectivitySignature' => $fence['selectivitySignature'],
                'next229ProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT229 SELECTIVITY FENCE '
                . (string) ($base['selectedPlan']['name'] ?? '')
                . ($ready ? ' CURRENT STAT4 CARDINALITY PROVED' : ' REQUIRES CURRENT STAT4 SELECTIVITY REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next229',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next229 reuses current-source STAT4 expression partial sample fences and adds selectivity/cardinality proof before cursor reuse',
            'non_overlap' => 'adds current sqlite_stat4 selectivity and peer-count cardinality validation after accepted next224 sample-order validation; avoids expression ORDER BY, range-cost ranking, grouped SELECT, JSON table, WAL, VFS, B-tree, trigger, UTF, and prior STAT4 sample-order clusters',
        ]);
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next229 base matched rows must be a list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next229 matched rows must be arrays');
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $matchedRows
     * @param array<string,mixed> $sampleFence
     * @return array<string,mixed>
     */
    private static function selectivityFence(array $matchedRows, array $sampleFence, int $limit, int $offset): array
    {
        if ($limit < 0 || $offset < 0) {
            throw new \InvalidArgumentException('SQLite next229 limit and offset must be non-negative');
        }
        $proofs = $sampleFence['matchedSampleProofs'] ?? null;
        if (!is_array($proofs) || !array_is_list($proofs) || $proofs === []) {
            throw new \InvalidArgumentException('SQLite next229 needs next224 matched sample proofs');
        }

        $matchedCount = count($matchedRows);
        $maxNlt = self::maxIntColumn($proofs, 'sampleNlt');
        $maxNeq = self::maxIntColumn($proofs, 'sampleNeq');
        $estimatedRows = $maxNlt + $maxNeq;
        $pageEnd = $offset + $matchedCount;
        $rowids = array_values(array_map(static fn (array $row): int => self::rowid($row), $matchedRows));
        $rejected = [];
        if ($estimatedRows < $matchedCount || $estimatedRows < $pageEnd) {
            $rejected = $rowids;
        }

        $peerRowsByKey = [];
        foreach ($proofs as $proof) {
            if (!is_array($proof)) {
                throw new \InvalidArgumentException('SQLite next229 sample proofs must be arrays');
            }
            $key = (string) ($proof['expressionKey'] ?? '');
            $peerRowsByKey[$key][] = self::rowid($proof);
        }

        $peerProofs = [];
        $peerRejected = [];
        foreach ($peerRowsByKey as $key => $peerRowids) {
            $sampleNeq = self::sampleNeqForKey($proofs, $key);
            $covered = count($peerRowids) <= $sampleNeq;
            $peerProofs[] = [
                'expressionKey' => $key,
                'matchedPeerRowids' => $peerRowids,
                'matchedPeerCount' => count($peerRowids),
                'sampleNeq' => $sampleNeq,
                'sampleCoversPeers' => $covered,
            ];
            if (!$covered) {
                $peerRejected = array_merge($peerRejected, $peerRowids);
            }
        }

        $rejected = array_values(array_unique(array_merge($rejected, $peerRejected)));

        return [
            'matchedRowCount' => $matchedCount,
            'limit' => $limit,
            'offset' => $offset,
            'pageWindow' => ['start' => $offset, 'end' => $pageEnd],
            'estimatedRows' => $estimatedRows,
            'maxSampleNlt' => $maxNlt,
            'maxSampleNeq' => $maxNeq,
            'currentStat4CardinalityBracketsMatchedRows' => $estimatedRows >= $matchedCount,
            'pageWindowWithinCurrentStat4Estimate' => $estimatedRows >= $pageEnd,
            'peerSelectivityProofs' => $peerProofs,
            'samplePeerCountsCoverMatchedPeers' => $peerRejected === [],
            'rowidsRejectedBySelectivityFence' => $rejected,
            'selectivitySignature' => self::signature([$estimatedRows, $matchedCount, $offset, $limit, $peerProofs]),
            'proofSignature' => self::signature([$proofs, $rowids, $rejected]),
        ];
    }

    /** @param list<array<string,mixed>> $proofs */
    private static function maxIntColumn(array $proofs, string $column): int
    {
        $values = [];
        foreach ($proofs as $proof) {
            if (!is_array($proof) || !is_int($proof[$column] ?? null)) {
                throw new \InvalidArgumentException('SQLite next229 sample proof ' . $column . ' must be an integer');
            }
            $values[] = $proof[$column];
        }

        return max($values);
    }

    /** @param list<array<string,mixed>> $proofs */
    private static function sampleNeqForKey(array $proofs, string $key): int
    {
        foreach ($proofs as $proof) {
            if ((string) ($proof['expressionKey'] ?? '') === $key) {
                if (!is_int($proof['sampleNeq'] ?? null)) {
                    throw new \InvalidArgumentException('SQLite next229 sample proof neq must be an integer');
                }

                return $proof['sampleNeq'];
            }
        }

        throw new \InvalidArgumentException('SQLite next229 sample proof key missing');
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next229 rowid must be an integer');
        }

        return (int) $row['rowid'];
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param array<string,mixed> $fence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'RecheckCurrentStat4Selectivity',
            'mode' => 'next229-current-source-stat4-expression-partial-selectivity',
            'estimatedRows' => $fence['estimatedRows'],
            'matchedRows' => $fence['matchedRowCount'],
            'pageWindow' => $fence['pageWindow'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
