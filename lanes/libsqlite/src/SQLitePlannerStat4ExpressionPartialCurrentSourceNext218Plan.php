<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext218Plan
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
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $fence = self::payloadFence(
            $currentIndex,
            self::matchedRows($base),
            $neededColumns,
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next212-ready'
            && $fence['allMatchedRowsHaveCurrentExpressionPayload'] === true
            && $fence['allNeededColumnsCoveredByCurrentIndex'] === true
            && $fence['allStat4SamplePayloadsResolveToCurrentRows'] === true
            && $fence['stalePayloadRowids'] === []
            && $fence['missingCoveredColumns'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next218-ready' : 'requires-current-source-expression-payload-reprepare',
            'expressionPayloadFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next218Ready' => $ready,
                'next218ExpressionPayloadSignature' => $fence['expressionPayloadSignature'],
                'next218CurrentCoveringSignature' => $fence['currentCoveringSignature'],
                'next218StalePayloadRowids' => $fence['stalePayloadRowids'],
                'next218MissingCoveredColumns' => $fence['missingCoveredColumns'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next218ExpressionPayloadSignature' => $fence['expressionPayloadSignature'],
                'next218PayloadProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT218 EXPRESSION PAYLOAD COVERING FENCE '
                . $selectedName
                . ($ready ? ' CURRENT EXPRESSION PAYLOAD COVERAGE PROVED' : ' REQUIRES CURRENT EXPRESSION PAYLOAD REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext212Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next218',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next218 reuses current-source STAT4 expression partial fences and adds expression-payload plus covering-column validation for selected current rows',
            'non_overlap' => 'adds expression payload and covering-column current-source validation after accepted grouped LIKE/stat4 partial fences; avoids STAT4 rowid alias, duplicate fanout, predicate-definition, grouped OR/LIKE, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and UTF clusters',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next218 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next218 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next218 selected index missing from source');
    }

    /**
     * @param array<string,mixed> $base
     * @return list<array<string,mixed>>
     */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next218 base matched rows must be a list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next218 matched rows must be arrays');
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $index
     * @param list<array<string,mixed>> $matchedRows
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function payloadFence(array $index, array $matchedRows, array $neededColumns): array
    {
        $expression = strtolower(preg_replace('/\s+/', '', (string) ($index['expression'] ?? '')) ?? '');
        if ($expression !== 'lower(option_name)') {
            throw new \InvalidArgumentException('SQLite next218 only supports lower(option_name) expression payloads');
        }
        $collation = strtoupper((string) ($index['collation'] ?? 'BINARY'));
        if (!in_array($collation, ['BINARY', 'NOCASE'], true)) {
            throw new \InvalidArgumentException('SQLite next218 expression payload collation is unsupported');
        }
        $covering = self::stringList($index['coveringColumns'] ?? null, 'coveringColumns');
        $payloads = self::payloads($index['stat4ExpressionPayloads'] ?? null);
        $samples = self::samples($index['stat4Samples'] ?? null);
        $missingColumns = array_values(array_diff($neededColumns, $covering));
        $payloadByRowid = [];
        foreach ($payloads as $payload) {
            $payloadByRowid[$payload['rowid']] = $payload;
        }

        $rowProofs = [];
        $stale = [];
        foreach ($matchedRows as $row) {
            $rowid = self::rowid($row, 'matched rowid');
            $payload = $payloadByRowid[$rowid] ?? null;
            $rowPayload = is_array($row['payload'] ?? null) ? $row['payload'] : $row;
            $expectedKey = self::expressionKey($row['expressionKey'] ?? ($rowPayload['option_name'] ?? null), $collation);
            $actualKey = is_array($payload) ? (string) ($payload['expressionKey'] ?? '') : null;
            $coveredValues = is_array($payload) && is_array($payload['coveredValues'] ?? null)
                ? $payload['coveredValues']
                : [];
            $missingPayloadColumns = array_values(array_diff($neededColumns, array_keys($coveredValues)));
            $matches = $payload !== null
                && $actualKey === $expectedKey
                && $missingPayloadColumns === [];
            if (!$matches) {
                $stale[] = $rowid;
            }
            $rowProofs[] = [
                'rowid' => $rowid,
                'expectedExpressionKey' => $expectedKey,
                'payloadExpressionKey' => $actualKey,
                'payloadFound' => $payload !== null,
                'missingPayloadColumns' => $missingPayloadColumns,
                'matchesCurrentExpressionPayload' => $matches,
            ];
        }

        $sampleProofs = [];
        $missingSamples = [];
        foreach ($samples as $sample) {
            $payload = $payloadByRowid[$sample['rowid']] ?? null;
            $sampleMatches = $payload !== null && (string) ($payload['expressionKey'] ?? '') === $sample['expressionKey'];
            if (!$sampleMatches) {
                $missingSamples[] = $sample['rowid'];
            }
            $sampleProofs[] = [
                'rowid' => $sample['rowid'],
                'sampleExpressionKey' => $sample['expressionKey'],
                'payloadFound' => $payload !== null,
                'payloadExpressionKey' => is_array($payload) ? (string) ($payload['expressionKey'] ?? '') : null,
                'samplePayloadMatchesCurrentRow' => $sampleMatches,
            ];
        }

        return [
            'expression' => $expression,
            'collation' => $collation,
            'currentCoveringColumns' => $covering,
            'neededColumns' => $neededColumns,
            'missingCoveredColumns' => $missingColumns,
            'allNeededColumnsCoveredByCurrentIndex' => $missingColumns === [],
            'matchedRowPayloadProofs' => $rowProofs,
            'stalePayloadRowids' => array_values(array_unique($stale)),
            'allMatchedRowsHaveCurrentExpressionPayload' => $stale === [],
            'stat4SamplePayloadProofs' => $sampleProofs,
            'missingSamplePayloadRowids' => array_values(array_unique($missingSamples)),
            'allStat4SamplePayloadsResolveToCurrentRows' => $missingSamples === [],
            'expressionPayloadSignature' => self::signature($payloads),
            'currentCoveringSignature' => self::signature([$covering, $neededColumns, $collation]),
            'proofSignature' => self::signature([$rowProofs, $sampleProofs, $missingColumns]),
        ];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $label): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next218 ' . $label . ' must be a list');
        }

        return array_map(static fn (mixed $item): string => (string) $item, $value);
    }

    /**
     * @return list<array{rowid:int,expressionKey:string,coveredValues:array<string,mixed>}>
     */
    private static function payloads(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            throw new \InvalidArgumentException('SQLite next218 needs stat4ExpressionPayloads');
        }
        $out = [];
        foreach ($value as $payload) {
            if (!is_array($payload) || !is_array($payload['coveredValues'] ?? null)) {
                throw new \InvalidArgumentException('SQLite next218 expression payload entries are malformed');
            }
            $out[] = [
                'rowid' => self::rowid(['rowid' => $payload['rowid'] ?? null], 'payload rowid'),
                'expressionKey' => strtolower((string) ($payload['expressionKey'] ?? '')),
                'coveredValues' => $payload['coveredValues'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{rowid:int,expressionKey:string}>
     */
    private static function samples(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            throw new \InvalidArgumentException('SQLite next218 needs stat4Samples');
        }
        $out = [];
        foreach ($value as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 2) {
                throw new \InvalidArgumentException('SQLite next218 stat4 samples are malformed');
            }
            $out[] = [
                'expressionKey' => strtolower((string) $sample['sample'][0]),
                'rowid' => self::rowid(['rowid' => $sample['sample'][1]], 'sample rowid'),
            ];
        }

        return $out;
    }

    private static function expressionKey(mixed $value, string $collation): ?string
    {
        if ($value === null) {
            return null;
        }
        $key = strtolower((string) $value);

        return $collation === 'RTRIM' ? rtrim($key) : $key;
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row, string $label): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next218 ' . $label . ' must be an integer');
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
            'opcode' => 'RecheckCurrentExpressionPayloadCoverage',
            'mode' => 'next218-current-source-stat4-expression-partial-payload-covering',
            'rowids' => array_column($fence['matchedRowPayloadProofs'], 'rowid'),
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
