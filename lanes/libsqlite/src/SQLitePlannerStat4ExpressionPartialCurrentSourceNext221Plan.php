<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext221Plan
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
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $fence = self::sampleWindowFence(
            self::stat4SampleKeys($currentIndex),
            self::yieldRows($base),
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next217-ready'
            && $fence['allRowsCoveredByCurrentStat4Samples'] === true
            && $fence['samplePositionsPreserveDescendingScan'] === true
            && $fence['rowidsRejectedByCurrentStat4Samples'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next221-ready' : 'requires-current-source-stat4-sample-window-reprepare',
            'stat4SampleWindowFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next221Ready' => $ready,
                'next221CurrentStat4SampleSignature' => $fence['currentStat4SampleSignature'],
                'next221ProofSignature' => $fence['proofSignature'],
                'next221SamplePositions' => $fence['samplePositions'],
                'next221RowsRejectedByCurrentStat4Samples' => $fence['rowidsRejectedByCurrentStat4Samples'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next221CurrentStat4SampleSignature' => $fence['currentStat4SampleSignature'],
                'next221SampleWindowProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT221 SAMPLE WINDOW FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 SAMPLE WINDOWS PROVED' : ' REQUIRES CURRENT SOURCE STAT4 SAMPLE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext217Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next221',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next221 composes current-source STAT4 partial expression yield fences and validates the yielded cursor page against current STAT4 sample windows',
            'non_overlap' => 'avoids accepted next217 current/next yield continuity, next213 LIKE case checks, next212 grouped LIKE proof, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only proves yielded partial-expression rows remain bracketed by current-source STAT4 samples',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next221 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next221 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next221 selected index missing from current source');
    }

    /** @param array<string,mixed> $index @return list<string> */
    private static function stat4SampleKeys(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next221 selected index needs STAT4 samples');
        }
        $keys = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !isset($sample['sample']) || !is_array($sample['sample']) || !array_key_exists(0, $sample['sample'])) {
                throw new \InvalidArgumentException('SQLite next221 STAT4 samples need expression keys');
            }
            $keys[] = strtolower((string) $sample['sample'][0]);
        }
        $keys = array_values(array_unique($keys));
        sort($keys, SORT_STRING);

        return $keys;
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function yieldRows(array $base): array
    {
        $pairs = $base['stat4YieldFence']['currentNextPairs'] ?? null;
        if (!is_array($pairs) || !array_is_list($pairs)) {
            throw new \InvalidArgumentException('SQLite next221 needs next217 current/next pairs');
        }
        $out = [];
        foreach ($pairs as $pair) {
            if (!is_array($pair)) {
                throw new \InvalidArgumentException('SQLite next221 current/next pairs must be arrays');
            }
            $out[] = [
                'rowid' => $pair['rowid'] ?? null,
                'expressionKey' => $pair['expressionKey'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param list<string> $sampleKeys
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function sampleWindowFence(array $sampleKeys, array $rows): array
    {
        $proofs = [];
        $rejected = [];
        $positions = [];
        $previousPosition = null;
        $positionRegressions = [];
        foreach ($rows as $row) {
            $key = self::expressionKey($row);
            $rowid = self::rowid($row);
            $position = self::samplePosition($sampleKeys, $key);
            $covered = $position !== null;
            if (!$covered) {
                $rejected[] = $rowid;
            }
            if ($previousPosition !== null && $position !== null && $position > $previousPosition) {
                $positionRegressions[] = $rowid;
            }
            $positions[] = $position;
            $proofs[] = [
                'rowid' => $rowid,
                'expressionKey' => $key,
                'samplePosition' => $position,
                'lowerSampleKey' => $position === null ? null : $sampleKeys[$position],
                'upperSampleKey' => $position === null ? null : ($sampleKeys[$position + 1] ?? null),
                'coveredByCurrentStat4Samples' => $covered,
                'descendingScanPositionOk' => $previousPosition === null || $position === null || $position <= $previousPosition,
            ];
            $previousPosition = $position;
        }

        return [
            'currentStat4SampleKeys' => $sampleKeys,
            'currentStat4SampleSignature' => self::signature($sampleKeys),
            'rowProofs' => $proofs,
            'samplePositions' => $positions,
            'allRowsCoveredByCurrentStat4Samples' => $rejected === [],
            'samplePositionsPreserveDescendingScan' => $positionRegressions === [],
            'rowidsRejectedByCurrentStat4Samples' => $rejected,
            'rowidsRejectedByDescendingSampleOrder' => $positionRegressions,
            'proofSignature' => self::signature([$sampleKeys, $proofs]),
        ];
    }

    /** @param list<string> $sampleKeys */
    private static function samplePosition(array $sampleKeys, string $key): ?int
    {
        if ($key < $sampleKeys[0]) {
            return null;
        }
        $position = null;
        foreach ($sampleKeys as $offset => $sampleKey) {
            if ($key < $sampleKey) {
                break;
            }
            $position = $offset;
        }

        return $position;
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

        throw new \InvalidArgumentException('SQLite next221 rows need expression keys');
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next221 rowid must be an integer');
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
            'opcode' => 'RecheckStat4SampleWindowYield',
            'mode' => 'next221-current-source-stat4-expression-partial-sample-window',
            'samplePositions' => $fence['samplePositions'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
