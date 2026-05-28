<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext218Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $sampleFence = self::sampleOrderFence($currentIndex, self::matchedRows($base));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next218-ready'
            && $sampleFence['allMatchedExpressionKeysHaveCurrentSamples'] === true
            && $sampleFence['currentSamplesPreserveSelectedScanOrder'] === true
            && $sampleFence['duplicateSamplePeersRemainInRowidOrder'] === true
            && $sampleFence['rowidsRejectedBySampleOrderFence'] === []
            && $sampleFence['expressionKeysMissingCurrentSamples'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next224-ready' : 'requires-current-source-stat4-sample-order-reprepare',
            'stat4SampleOrderFence' => $sampleFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next224Ready' => $ready,
                'next224SampleOrderSignature' => $sampleFence['sampleOrderSignature'],
                'next224SampleProofSignature' => $sampleFence['proofSignature'],
                'next224MissingCurrentSampleKeys' => $sampleFence['expressionKeysMissingCurrentSamples'],
                'next224RowsRejectedBySampleOrderFence' => $sampleFence['rowidsRejectedBySampleOrderFence'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next224SampleOrderSignature' => $sampleFence['sampleOrderSignature'],
                'next224SampleProofSignature' => $sampleFence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $sampleFence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT224 SAMPLE ORDER FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 SAMPLES PRESERVE SCAN ORDER' : ' REQUIRES CURRENT STAT4 SAMPLE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext218Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next224',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next224 reuses current-source STAT4 expression partial payload fences and adds selected-page STAT4 sample-order validation',
            'non_overlap' => 'adds current sqlite_stat4 sample-order validation for matched expression keys after accepted next218 payload coverage; avoids grouped LIKE/OR, rowid alias, duplicate fanout payloads, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next224 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next224 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next224 selected index missing from source');
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next224 base matched rows must be a list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next224 matched rows must be arrays');
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $index
     * @param list<array<string,mixed>> $matchedRows
     * @return array<string,mixed>
     */
    private static function sampleOrderFence(array $index, array $matchedRows): array
    {
        $samples = self::samples($index['stat4Samples'] ?? null);
        $sampleByKey = [];
        foreach ($samples as $ordinal => $sample) {
            $sampleByKey[$sample['expressionKey']] = $sample + ['ordinal' => $ordinal];
        }

        $proofs = [];
        $missing = [];
        $rejected = [];
        $previous = null;
        foreach ($matchedRows as $position => $row) {
            $rowid = self::rowid($row, 'matched rowid');
            $key = self::expressionKey($row);
            $sample = $sampleByKey[$key] ?? null;
            if ($sample === null) {
                $missing[] = $key;
            }
            $relation = 'first';
            $ordered = $sample !== null;
            if (is_array($previous) && $sample !== null) {
                if ($sample['ordinal'] < $previous['sampleOrdinal']) {
                    $relation = 'descending-stat4-sample';
                    $ordered = true;
                } elseif ($sample['ordinal'] === $previous['sampleOrdinal'] && $rowid > $previous['rowid']) {
                    $relation = 'peer-rowid';
                    $ordered = true;
                } else {
                    $relation = 'out-of-order-current-stat4-sample';
                    $ordered = false;
                    $rejected[] = $rowid;
                }
            }
            $proof = [
                'position' => $position,
                'rowid' => $rowid,
                'expressionKey' => $key,
                'sampleFound' => $sample !== null,
                'sampleOrdinal' => is_array($sample) ? $sample['ordinal'] : null,
                'sampleNlt' => is_array($sample) ? $sample['nlt'] : null,
                'sampleNdlt' => is_array($sample) ? $sample['ndlt'] : null,
                'sampleNeq' => is_array($sample) ? $sample['neq'] : null,
                'relationToPrevious' => $relation,
                'preservesSelectedScanOrder' => $ordered,
            ];
            $proofs[] = $proof;
            if ($sample !== null) {
                $previous = $proof;
            }
        }

        return [
            'sampleCount' => count($samples),
            'matchedSampleProofs' => $proofs,
            'expressionKeysMissingCurrentSamples' => array_values(array_unique($missing)),
            'allMatchedExpressionKeysHaveCurrentSamples' => $missing === [],
            'rowidsRejectedBySampleOrderFence' => array_values(array_unique($rejected)),
            'currentSamplesPreserveSelectedScanOrder' => $rejected === [] && $missing === [],
            'duplicateSamplePeersRemainInRowidOrder' => self::duplicatePeersOrdered($proofs),
            'sampleOrderSignature' => self::signature(array_map(
                static fn (array $proof): array => [
                    'rowid' => $proof['rowid'],
                    'expressionKey' => $proof['expressionKey'],
                    'sampleOrdinal' => $proof['sampleOrdinal'],
                    'relationToPrevious' => $proof['relationToPrevious'],
                ],
                $proofs,
            )),
            'proofSignature' => self::signature([$samples, $proofs, $missing, $rejected]),
        ];
    }

    /**
     * @return list<array{expressionKey:string,rowid:int,nlt:int,ndlt:int,neq:int}>
     */
    private static function samples(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            throw new \InvalidArgumentException('SQLite next224 needs stat4Samples');
        }
        $out = [];
        foreach ($value as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 2) {
                throw new \InvalidArgumentException('SQLite next224 stat4 samples are malformed');
            }
            $out[] = [
                'expressionKey' => strtolower((string) $sample['sample'][0]),
                'rowid' => self::rowid(['rowid' => $sample['sample'][1]], 'sample rowid'),
                'nlt' => self::firstStatInt($sample['nlt'] ?? null, 'nlt'),
                'ndlt' => self::firstStatInt($sample['ndlt'] ?? null, 'ndlt'),
                'neq' => self::firstStatInt($sample['neq'] ?? null, 'neq'),
            ];
        }

        return $out;
    }

    private static function firstStatInt(mixed $value, string $label): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new \InvalidArgumentException('SQLite next224 stat4 ' . $label . ' must be a string or integer');
        }
        $parts = preg_split('/\s+/', trim((string) $value));
        if ($parts === false || $parts === [] || !ctype_digit($parts[0])) {
            throw new \InvalidArgumentException('SQLite next224 stat4 ' . $label . ' is malformed');
        }

        return (int) $parts[0];
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

        throw new \InvalidArgumentException('SQLite next224 matched rows need expressionKey or option_name payload');
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row, string $label): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next224 ' . $label . ' must be an integer');
        }

        return (int) $row['rowid'];
    }

    /** @param list<array<string,mixed>> $proofs */
    private static function duplicatePeersOrdered(array $proofs): bool
    {
        $lastByOrdinal = [];
        foreach ($proofs as $proof) {
            if (!is_int($proof['sampleOrdinal'])) {
                return false;
            }
            $ordinal = $proof['sampleOrdinal'];
            $rowid = (int) $proof['rowid'];
            if (isset($lastByOrdinal[$ordinal]) && $rowid <= $lastByOrdinal[$ordinal]) {
                return false;
            }
            $lastByOrdinal[$ordinal] = $rowid;
        }

        return true;
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
            'opcode' => 'RecheckCurrentStat4SampleOrder',
            'mode' => 'next224-current-source-stat4-expression-partial-sample-order',
            'rowids' => array_column($fence['matchedSampleProofs'], 'rowid'),
            'sampleOrdinals' => array_column($fence['matchedSampleProofs'], 'sampleOrdinal'),
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
