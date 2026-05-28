<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext237Plan
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
        if ($trailingColumns === []) {
            throw new \InvalidArgumentException('SQLite next237 needs trailing columns');
        }

        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext228Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $rowsByRowid = self::rowsByRowid($currentSource);
        $fence = self::trailingPayloadFence(
            $rowsByRowid,
            self::stat4TrailingPayloads($currentIndex, $trailingColumns),
            self::matchedSampleRowids($base),
            $trailingColumns,
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next228-ready'
            && $fence['allTrailingPayloadRowsResolveToCurrentSource'] === true
            && $fence['allTrailingPayloadsMatchCurrentRows'] === true
            && $fence['matchedSamplesRemainTrailingCompatible'] === true
            && $fence['missingCurrentTrailingPayloadRowids'] === []
            && $fence['sampleRowidsRejectedByTrailingPayload'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next237-ready' : 'requires-current-source-stat4-trailing-payload-reprepare',
            'stat4TrailingPayloadFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next237Ready' => $ready,
                'next237TrailingColumns' => $trailingColumns,
                'next237TrailingPayloadSignature' => $fence['trailingPayloadSignature'],
                'next237ProofSignature' => $fence['proofSignature'],
                'next237MissingCurrentTrailingPayloadRowids' => $fence['missingCurrentTrailingPayloadRowids'],
                'next237RowsRejectedByTrailingPayload' => $fence['sampleRowidsRejectedByTrailingPayload'],
                'next237MatchedTrailingRowids' => $fence['matchedTrailingRowids'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next237TrailingPayloadSignature' => $fence['trailingPayloadSignature'],
                'next237ProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT237 TRAILING PAYLOAD FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 TRAILING PAYLOADS PROVED' : ' REQUIRES CURRENT STAT4 TRAILING PAYLOAD REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext228Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next237',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next237 reuses current-source STAT4 expression partial fences and adds trailing payload validation for yielded covering scans',
            'non_overlap' => 'adds current sqlite_stat4 trailing-payload validation after accepted next228 sample partial-predicate validation; avoids STAT4 sample window/order, rowid peer fences, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next237 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next237 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next237 selected index missing from source');
    }

    /** @param array<string,mixed> $source @return array<int,array<string,mixed>> */
    private static function rowsByRowid(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next237 needs current source rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next237 current source rows must be arrays');
            }
            $out[self::rowid($row, 'current rowid')] = $row;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $index
     * @param list<string> $trailingColumns
     * @return list<array{expressionKey:string,rowid:int,trailing:array<string,mixed>,neqVector:list<int>,nltVector:list<int>}>
     */
    private static function stat4TrailingPayloads(array $index, array $trailingColumns): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next237 needs stat4Samples');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 2) {
                throw new \InvalidArgumentException('SQLite next237 stat4 samples are malformed');
            }
            $trailing = [];
            foreach ($trailingColumns as $position => $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite next237 trailing column names must be non-empty strings');
                }
                $trailing[$column] = $sample['sample'][$position + 2] ?? null;
            }
            $out[] = [
                'expressionKey' => strtolower((string) $sample['sample'][0]),
                'rowid' => self::rowid(['rowid' => $sample['sample'][1]], 'stat4 sample rowid'),
                'trailing' => $trailing,
                'neqVector' => self::statVector($sample['neq'] ?? null),
                'nltVector' => self::statVector($sample['nlt'] ?? null),
            ];
        }

        return $out;
    }

    /** @return list<int> */
    private static function statVector(mixed $value): array
    {
        if (is_int($value)) {
            return [$value];
        }
        if (is_string($value)) {
            $parts = preg_split('/\s+/', trim($value));
            if ($parts === false || $parts === ['']) {
                return [];
            }

            return array_map(static fn (string $part): int => (int) $part, $parts);
        }
        if (is_array($value)) {
            return array_map(static fn (mixed $part): int => (int) $part, $value);
        }

        return [];
    }

    /** @param array<string,mixed> $base @return list<int> */
    private static function matchedSampleRowids(array $base): array
    {
        $rowids = $base['stat4SamplePartialPredicateFence']['matchedSampleRowids'] ?? null;
        if (!is_array($rowids) || !array_is_list($rowids)) {
            throw new \InvalidArgumentException('SQLite next237 needs next228 matched sample rowids');
        }

        return array_values(array_map(static fn (mixed $rowid): int => (int) $rowid, $rowids));
    }

    /**
     * @param array<int,array<string,mixed>> $rowsByRowid
     * @param list<array{expressionKey:string,rowid:int,trailing:array<string,mixed>,neqVector:list<int>,nltVector:list<int>}> $payloads
     * @param list<int> $matchedSampleRowids
     * @param list<string> $trailingColumns
     * @return array<string,mixed>
     */
    private static function trailingPayloadFence(array $rowsByRowid, array $payloads, array $matchedSampleRowids, array $trailingColumns): array
    {
        $matchedLookup = array_fill_keys($matchedSampleRowids, true);
        $proofs = [];
        $missing = [];
        $rejected = [];
        $matchedRejected = [];

        foreach ($payloads as $payload) {
            $rowid = $payload['rowid'];
            $row = $rowsByRowid[$rowid] ?? null;
            if ($row === null) {
                $missing[] = $rowid;
                $proofs[] = [
                    'sampleRowid' => $rowid,
                    'sampleExpressionKey' => $payload['expressionKey'],
                    'matchedBySelectedPage' => isset($matchedLookup[$rowid]),
                    'currentRowPresent' => false,
                    'trailingColumnProofs' => [],
                    'trailingPayloadMatchesCurrentRow' => false,
                    'neqVector' => $payload['neqVector'],
                    'nltVector' => $payload['nltVector'],
                ];
                continue;
            }

            $columnProofs = [];
            foreach ($trailingColumns as $column) {
                $sampleValue = $payload['trailing'][$column] ?? null;
                $currentValue = $row[$column] ?? null;
                $matches = self::valuesMatch($sampleValue, $currentValue);
                $columnProofs[] = [
                    'column' => $column,
                    'sampleValue' => $sampleValue,
                    'currentValue' => $currentValue,
                    'matches' => $matches,
                ];
            }
            $payloadMatches = !in_array(false, array_column($columnProofs, 'matches'), true);
            if (!$payloadMatches) {
                $rejected[] = $rowid;
                if (isset($matchedLookup[$rowid])) {
                    $matchedRejected[] = $rowid;
                }
            }

            $proofs[] = [
                'sampleRowid' => $rowid,
                'sampleExpressionKey' => $payload['expressionKey'],
                'matchedBySelectedPage' => isset($matchedLookup[$rowid]),
                'currentRowPresent' => true,
                'trailingColumnProofs' => $columnProofs,
                'trailingPayloadMatchesCurrentRow' => $payloadMatches,
                'neqVector' => $payload['neqVector'],
                'nltVector' => $payload['nltVector'],
            ];
        }

        return [
            'trailingColumns' => $trailingColumns,
            'sampleRowCount' => count($proofs),
            'matchedTrailingRowids' => $matchedSampleRowids,
            'sampleRowProofs' => $proofs,
            'allTrailingPayloadRowsResolveToCurrentSource' => $missing === [],
            'allTrailingPayloadsMatchCurrentRows' => $rejected === [],
            'matchedSamplesRemainTrailingCompatible' => $matchedRejected === [],
            'missingCurrentTrailingPayloadRowids' => array_values(array_unique($missing)),
            'sampleRowidsRejectedByTrailingPayload' => array_values(array_unique($rejected)),
            'matchedSampleRowidsRejectedByTrailingPayload' => array_values(array_unique($matchedRejected)),
            'trailingPayloadSignature' => self::signature([$trailingColumns, $payloads, $matchedSampleRowids]),
            'proofSignature' => self::signature([$trailingColumns, $payloads, $matchedSampleRowids, $proofs, $missing, $rejected, $matchedRejected]),
        ];
    }

    private static function valuesMatch(mixed $left, mixed $right): bool
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return (float) $left === (float) $right;
        }

        return strtolower((string) $left) === strtolower((string) $right);
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row, string $label): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next237 ' . $label . ' must be an integer');
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
            'opcode' => 'RecheckCurrentStat4TrailingPayloads',
            'mode' => 'next237-current-source-stat4-expression-partial-trailing-payload',
            'trailingColumns' => $fence['trailingColumns'],
            'sampleRowids' => array_column($fence['sampleRowProofs'], 'sampleRowid'),
            'matchedTrailingRowids' => $fence['matchedTrailingRowids'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
