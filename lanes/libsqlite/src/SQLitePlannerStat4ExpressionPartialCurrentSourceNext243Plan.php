<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext243Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @param list<string> $trailingColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $whereTerms,
        array $neededColumns,
        array $trailingColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext240Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $trailingColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $preparedIndex = self::indexByName($preparedSource, $selectedName);
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $fence = self::sampleTapeFence($preparedIndex, $currentIndex, self::rows($currentSource), self::matchedRowids($base));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next240-ready'
            && $fence['ready'] === true
            && $fence['preparedTapeReused'] === false;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next243-ready' : 'requires-current-source-stat4-expression-sample-tape-reprepare',
            'stat4ExpressionSampleTapeFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next243Ready' => $ready,
                'next243PreparedSampleRowids' => $fence['preparedSampleRowids'],
                'next243CurrentSampleRowids' => $fence['currentSampleRowids'],
                'next243ExpandedCurrentRowids' => $fence['expandedCurrentRowids'],
                'next243MissingSampleRowids' => $fence['missingCurrentSampleRowids'],
                'next243PreparedTapeReused' => $fence['preparedTapeReused'],
                'next243RejectedReason' => $fence['rejectedReason'],
                'next243ProofSignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next243SampleTapeReady' => $ready,
                'next243SampleTapeSignature' => $fence['proofSignature'],
                'next243CurrentSampleRowids' => $fence['currentSampleRowids'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT243 SAMPLE TAPE FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 SAMPLE TAPE PROVED' : ' REQUIRES CURRENT STAT4 SAMPLE TAPE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext240Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next243',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next243 reuses current-source STAT4 expression partial planning and adds current sqlite_stat4 sample-tape validation',
            'non_overlap' => 'adds current-source sqlite_stat4 expression sample-tape validation after accepted next240 partial predicate proof; avoids next240 predicate implication duplicates, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters',
        ]);
    }

    /**
     * @param array<string,mixed> $preparedIndex
     * @param array<string,mixed> $currentIndex
     * @param list<array<string,mixed>> $rows
     * @param list<int> $matchedRowids
     * @return array<string,mixed>
     */
    private static function sampleTapeFence(array $preparedIndex, array $currentIndex, array $rows, array $matchedRowids): array
    {
        $preparedSampleRowids = self::sampleRowids($preparedIndex);
        $currentSampleRowids = self::sampleRowids($currentIndex);
        $rowsByExpression = self::matchedRowsByExpression($rows, $matchedRowids);
        $expanded = [];
        $proofs = [];
        $missing = [];

        foreach ($currentSampleRowids as $sampleRowid) {
            $sampleRow = self::rowByRowid($rows, $sampleRowid);
            if ($sampleRow === null) {
                $missing[] = $sampleRowid;
                $proofs[] = [
                    'sampleRowid' => $sampleRowid,
                    'currentRowPresent' => false,
                    'expressionKey' => null,
                    'expandedRowids' => [],
                ];
                continue;
            }
            $key = self::rowExpressionKey($sampleRow);
            $bucket = $rowsByExpression[$key] ?? [];
            $expanded = array_merge($expanded, $bucket);
            $proofs[] = [
                'sampleRowid' => $sampleRowid,
                'currentRowPresent' => true,
                'expressionKey' => $key,
                'expandedRowids' => $bucket,
            ];
        }

        $expanded = array_values(array_unique($expanded));
        $matchedSorted = $matchedRowids;
        sort($matchedSorted);
        $expandedSorted = $expanded;
        sort($expandedSorted);
        $preparedTapeReused = $preparedSampleRowids === $currentSampleRowids;
        $ready = $missing === []
            && $expandedSorted === $matchedSorted
            && $currentSampleRowids !== []
            && !$preparedTapeReused;
        $proof = [
            'preparedSampleRowids' => $preparedSampleRowids,
            'currentSampleRowids' => $currentSampleRowids,
            'expandedCurrentRowids' => $expanded,
            'matchedRowids' => $matchedRowids,
            'missingCurrentSampleRowids' => $missing,
            'preparedTapeReused' => $preparedTapeReused,
            'sampleProofs' => $proofs,
        ];

        return $proof + [
            'ready' => $ready,
            'rejectedReason' => self::rejectedReason($missing, $expandedSorted, $matchedSorted, $preparedTapeReused, $currentSampleRowids),
            'proofSignature' => self::signature($proof),
        ];
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next243 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next243 source indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next243 selected index missing from source');
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private static function rows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next243 needs current source rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next243 current source rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $base @return list<int> */
    private static function matchedRowids(array $base): array
    {
        $rowids = $base['matchedRowids'] ?? null;
        if (!is_array($rowids) || !array_is_list($rowids)) {
            throw new \InvalidArgumentException('SQLite next243 needs matched rowids from next240');
        }

        return array_values(array_map(static fn (mixed $rowid): int => (int) $rowid, $rowids));
    }

    /** @param array<string,mixed> $index @return list<int> */
    private static function sampleRowids(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next243 needs sqlite_stat4 samples');
        }
        $rowids = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || !array_key_exists(1, $sample['sample'])) {
                throw new \InvalidArgumentException('SQLite next243 stat4 samples need rowid in sample slot 1');
            }
            $rowid = $sample['sample'][1];
            if (!is_int($rowid) && !(is_string($rowid) && ctype_digit($rowid))) {
                throw new \InvalidArgumentException('SQLite next243 stat4 sample rowids must be integers');
            }
            $rowids[] = (int) $rowid;
        }

        return array_values(array_unique($rowids));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int> $matchedRowids
     * @return array<string,list<int>>
     */
    private static function matchedRowsByExpression(array $rows, array $matchedRowids): array
    {
        $matched = array_fill_keys($matchedRowids, true);
        $out = [];
        foreach ($rows as $row) {
            $rowid = self::rowid($row);
            if (!isset($matched[$rowid])) {
                continue;
            }
            $out[self::rowExpressionKey($row)][] = $rowid;
        }
        ksort($out);
        foreach ($out as &$rowids) {
            sort($rowids);
        }
        unset($rowids);

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>|null
     */
    private static function rowByRowid(array $rows, int $rowid): ?array
    {
        foreach ($rows as $row) {
            if (self::rowid($row) === $rowid) {
                return $row;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        $rowid = $row['rowid'] ?? null;
        if (!is_int($rowid)) {
            throw new \InvalidArgumentException('SQLite next243 rows need integer rowid');
        }

        return $rowid;
    }

    /** @param array<string,mixed> $row */
    private static function rowExpressionKey(array $row): string
    {
        return strtolower((string) ($row['option_name'] ?? ''));
    }

    /** @param list<int> $missing @param list<int> $expandedSorted @param list<int> $matchedSorted @param list<int> $currentSampleRowids */
    private static function rejectedReason(array $missing, array $expandedSorted, array $matchedSorted, bool $preparedTapeReused, array $currentSampleRowids): ?string
    {
        if ($currentSampleRowids === []) {
            return 'missing-current-stat4-samples';
        }
        if ($missing !== []) {
            return 'current-stat4-sample-row-missing';
        }
        if ($preparedTapeReused) {
            return 'prepared-stat4-sample-tape-reused';
        }
        if ($expandedSorted !== $matchedSorted) {
            return 'current-stat4-sample-tape-does-not-cover-matched-rowids';
        }

        return null;
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
            'opcode' => 'ValidateCurrentStat4ExpressionSampleTape',
            'mode' => 'next243-current-source-stat4-expression-partial-sample-tape',
            'currentSampleRowids' => $fence['currentSampleRowids'],
            'expandedCurrentRowids' => $fence['expandedCurrentRowids'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
