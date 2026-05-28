<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext246Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext243Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $trailingColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $fence = self::duplicateCardinalityFence(
            $currentIndex,
            self::rowsByRowid($currentSource),
            self::matchedRowids($base),
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next243-ready'
            && $fence['allCurrentStat4DuplicateCountsMatch'] === true
            && $fence['countMismatchExpressionKeys'] === []
            && $fence['missingSampleExpressionKeys'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next246-ready' : 'requires-current-source-stat4-duplicate-cardinality-reprepare',
            'stat4DuplicateCardinalityFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next246Ready' => $ready,
                'next246DuplicateExpressionKeys' => $fence['duplicateExpressionKeys'],
                'next246CountMismatchExpressionKeys' => $fence['countMismatchExpressionKeys'],
                'next246MissingSampleExpressionKeys' => $fence['missingSampleExpressionKeys'],
                'next246DuplicateCardinalitySignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next246DuplicateCardinalityReady' => $ready,
                'next246DuplicateCardinalitySignature' => $fence['proofSignature'],
                'next246DuplicateExpressionKeys' => $fence['duplicateExpressionKeys'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT246 DUPLICATE CARDINALITY FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 NEQ DUPLICATES PROVED' : ' REQUIRES CURRENT STAT4 DUPLICATE CARDINALITY REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext243Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next246',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next246 reuses current-source STAT4 sample-tape validation and adds sqlite_stat4 neq duplicate-cardinality fencing for partial expression indexes',
            'non_overlap' => 'adds current sqlite_stat4 duplicate-cardinality validation after accepted next243 sample-tape validation; avoids next243 sample-tape, accepted expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters',
        ]);
    }

    /**
     * @param array<string,mixed> $index
     * @param array<int,array<string,mixed>> $rowsByRowid
     * @param list<int> $matchedRowids
     * @return array<string,mixed>
     */
    private static function duplicateCardinalityFence(array $index, array $rowsByRowid, array $matchedRowids): array
    {
        $actualCounts = [];
        foreach ($matchedRowids as $rowid) {
            $row = $rowsByRowid[$rowid] ?? null;
            if ($row === null) {
                throw new \InvalidArgumentException('SQLite next246 matched rowid missing from current source');
            }
            $key = self::expressionKey($row);
            $actualCounts[$key] = ($actualCounts[$key] ?? 0) + 1;
        }
        ksort($actualCounts);

        $sampleCounts = self::sampleDuplicateCounts($index);
        $duplicateKeys = array_values(array_keys(array_filter($actualCounts, static fn (int $count): bool => $count > 1)));
        $proofs = [];
        $mismatches = [];
        $missing = [];
        foreach ($actualCounts as $key => $actualCount) {
            if ($actualCount < 2) {
                continue;
            }
            if (!array_key_exists($key, $sampleCounts)) {
                $missing[] = $key;
                $sampleCount = null;
            } else {
                $sampleCount = $sampleCounts[$key];
                if ($sampleCount !== $actualCount) {
                    $mismatches[] = $key;
                }
            }
            $proofs[] = [
                'expressionKey' => $key,
                'actualMatchedDuplicateCount' => $actualCount,
                'stat4NeqDuplicateCount' => $sampleCount,
                'matches' => $sampleCount === $actualCount,
            ];
        }

        $proof = [
            'actualExpressionCounts' => $actualCounts,
            'stat4ExpressionCounts' => $sampleCounts,
            'duplicateExpressionKeys' => $duplicateKeys,
            'countMismatchExpressionKeys' => $mismatches,
            'missingSampleExpressionKeys' => $missing,
            'duplicateCardinalityProofs' => $proofs,
        ];

        return $proof + [
            'allCurrentStat4DuplicateCountsMatch' => $duplicateKeys !== [] && $mismatches === [] && $missing === [],
            'proofSignature' => self::signature($proof),
        ];
    }

    /** @param array<string,mixed> $source @return array<int,array<string,mixed>> */
    private static function rowsByRowid(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next246 needs current source rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next246 current source rows must be arrays');
            }
            $rowid = self::intValue($row['rowid'] ?? null, 'current rowid');
            if (isset($out[$rowid])) {
                throw new \InvalidArgumentException('SQLite next246 duplicate current rowid');
            }
            $out[$rowid] = $row;
        }

        return $out;
    }

    /** @param array<string,mixed> $base @return list<int> */
    private static function matchedRowids(array $base): array
    {
        $rowids = $base['matchedRowids'] ?? null;
        if (!is_array($rowids) || !array_is_list($rowids)) {
            throw new \InvalidArgumentException('SQLite next246 needs matched rowids from next243');
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValue($rowid, 'matched rowid'), $rowids));
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next246 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next246 source indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next246 selected index missing from source');
    }

    /** @param array<string,mixed> $index @return array<string,int> */
    private static function sampleDuplicateCounts(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next246 needs sqlite_stat4 samples');
        }
        $counts = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || !array_key_exists(0, $sample['sample'])) {
                throw new \InvalidArgumentException('SQLite next246 stat4 samples need expression key in sample slot 0');
            }
            $key = strtolower((string) $sample['sample'][0]);
            $counts[$key] = self::firstStatInt($sample['neq'] ?? null, 'neq');
        }
        ksort($counts);

        return $counts;
    }

    /** @param array<string,mixed> $row */
    private static function expressionKey(array $row): string
    {
        if (array_key_exists('expressionKey', $row)) {
            return strtolower((string) $row['expressionKey']);
        }
        if (array_key_exists('option_name', $row)) {
            return strtolower((string) $row['option_name']);
        }

        throw new \InvalidArgumentException('SQLite next246 row needs expressionKey or option_name');
    }

    private static function firstStatInt(mixed $value, string $label): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new \InvalidArgumentException('SQLite next246 stat4 ' . $label . ' must be scalar');
        }
        $parts = preg_split('/\s+/', trim((string) $value));
        if ($parts === false || $parts === [] || !ctype_digit($parts[0])) {
            throw new \InvalidArgumentException('SQLite next246 stat4 ' . $label . ' first value must be an integer');
        }

        return (int) $parts[0];
    }

    private static function intValue(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next246 ' . $label . ' must be an integer');
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
            'opcode' => 'ValidateCurrentStat4DuplicateCardinality',
            'mode' => 'next246-current-source-stat4-expression-partial-duplicate-cardinality',
            'duplicateExpressionKeys' => $fence['duplicateExpressionKeys'],
            'proofSignature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
