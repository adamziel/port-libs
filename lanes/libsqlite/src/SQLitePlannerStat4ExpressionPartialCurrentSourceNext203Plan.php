<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext203Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext196Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) (($base['selectedPlan']['name'] ?? ''));
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $samples = self::stat4Samples($currentIndex);
        $rowsById = self::rowsById($currentSource);
        $boundaryFence = self::boundaryFence(self::matchedRows($base), $samples, $rowsById, $limit, $offset);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next196-ready'
            && $boundaryFence['ready'] === true
            && $boundaryFence['missingBoundaryKeys'] === []
            && $boundaryFence['sampleKeyDriftRowids'] === []
            && $boundaryFence['nonMonotonicSampleRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next203-ready' : 'requires-current-source-stat4-boundary-reprepare',
            'stat4BoundaryFence' => $boundaryFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next203Ready' => $ready,
                'next203BoundaryKeys' => $boundaryFence['boundaryKeys'],
                'next203MissingBoundaryKeys' => $boundaryFence['missingBoundaryKeys'],
                'next203BoundarySignature' => $boundaryFence['signature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next203BoundarySignature' => $boundaryFence['signature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $boundaryFence
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT203 BOUNDARY SAMPLE FENCE '
                . $selectedName
                . ($ready ? ' CURRENT BOUNDARIES VERIFIED' : ' REQUIRES CURRENT SOURCE STAT4 BOUNDARY REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext196Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next203',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next203 reuses current-source STAT4 expression partial peer-order fences and adds a LIMIT/OFFSET boundary sample admission fence',
            'non_overlap' => 'avoids accepted next196 peer-order, next195 partial-WHERE, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and encoding clusters; this slice only proves selected LIMIT/OFFSET window boundaries are bracketed by current STAT4 expression samples before admitting the partial expression-index scan',
        ]);
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next203 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next203 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next203 selected index missing from source');
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array<string,mixed>>
     */
    private static function stat4Samples(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite next203 needs STAT4 sample list');
        }
        $out = [];
        $lastNlt = -1;
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next203 STAT4 samples must be arrays');
            }
            $values = $sample['sample'] ?? null;
            if (!is_array($values) || !array_key_exists(0, $values) || !array_key_exists(1, $values)) {
                throw new \InvalidArgumentException('SQLite next203 STAT4 samples need expression key and rowid');
            }
            if (!is_int($values[1]) && !ctype_digit((string) $values[1])) {
                throw new \InvalidArgumentException('SQLite next203 STAT4 sample rowid must be an integer');
            }
            $nlt = self::firstCounter($sample['nlt'] ?? null);
            $out[] = [
                'expressionKey' => self::normalizeKey($values[0]),
                'rowid' => (int) $values[1],
                'nlt' => $nlt,
                'monotonic' => $nlt >= $lastNlt,
                'raw' => $sample,
            ];
            $lastNlt = $nlt;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $source
     * @return array<int,array<string,mixed>>
     */
    private static function rowsById(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next203 needs current rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists('rowid', $row)) {
                throw new \InvalidArgumentException('SQLite next203 source rows need rowid');
            }
            if (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid'])) {
                throw new \InvalidArgumentException('SQLite next203 source rowid must be an integer');
            }
            $out[(int) $row['rowid']] = $row;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $base
     * @return list<array<string,mixed>>
     */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next203 needs matched rows');
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $samples
     * @param array<int,array<string,mixed>> $rowsById
     * @return array<string,mixed>
     */
    private static function boundaryFence(array $rows, array $samples, array $rowsById, int $limit, int $offset): array
    {
        if ($limit < 0 || $offset < 0) {
            throw new \InvalidArgumentException('SQLite next203 LIMIT/OFFSET must be non-negative');
        }
        $selected = [];
        foreach ($rows as $position => $row) {
            if (!is_array($row) || !array_key_exists('rowid', $row)) {
                throw new \InvalidArgumentException('SQLite next203 matched rows need rowid');
            }
            $payload = $row['payload'] ?? null;
            if (!is_array($payload) || !array_key_exists('option_name', $payload)) {
                throw new \InvalidArgumentException('SQLite next203 matched rows need option_name payload');
            }
            $selected[] = [
                'position' => $position,
                'rowid' => (int) $row['rowid'],
                'expressionKey' => self::normalizeKey($payload['option_name']),
            ];
        }
        $boundaryKeys = $selected === [] ? [] : array_values(array_unique([
            (string) $selected[0]['expressionKey'],
            (string) $selected[array_key_last($selected)]['expressionKey'],
        ]));
        $sampleByKey = [];
        $nonMonotonic = [];
        foreach ($samples as $sample) {
            if (($sample['monotonic'] ?? false) !== true) {
                $nonMonotonic[] = (int) $sample['rowid'];
            }
            $sampleByKey[(string) $sample['expressionKey']][] = $sample;
        }

        $checks = [];
        $missing = [];
        $drift = [];
        foreach ($boundaryKeys as $key) {
            $keySamples = $sampleByKey[$key] ?? [];
            if ($keySamples === []) {
                $missing[] = $key;
                $checks[] = [
                    'expressionKey' => $key,
                    'ready' => false,
                    'reasons' => ['missing-current-stat4-boundary-sample'],
                ];
                continue;
            }
            $sampleChecks = [];
            foreach ($keySamples as $sample) {
                $row = $rowsById[(int) $sample['rowid']] ?? null;
                $actual = is_array($row) ? self::normalizeKey($row['option_name'] ?? null) : null;
                $ready = $actual === $key && ($sample['monotonic'] ?? false) === true;
                if (!$ready) {
                    $drift[] = (int) $sample['rowid'];
                }
                $sampleChecks[] = [
                    'rowid' => (int) $sample['rowid'],
                    'nlt' => (int) $sample['nlt'],
                    'expectedExpressionKey' => $key,
                    'actualExpressionKey' => $actual,
                    'ready' => $ready,
                ];
            }
            $checks[] = [
                'expressionKey' => $key,
                'ready' => !in_array(false, array_column($sampleChecks, 'ready'), true),
                'sampleChecks' => $sampleChecks,
            ];
        }

        return [
            'ready' => $selected !== [] && $missing === [] && $drift === [] && $nonMonotonic === [],
            'limit' => $limit,
            'offset' => $offset,
            'selectedRowids' => array_column($selected, 'rowid'),
            'selectedExpressionKeys' => array_column($selected, 'expressionKey'),
            'boundaryKeys' => $boundaryKeys,
            'missingBoundaryKeys' => $missing,
            'sampleKeyDriftRowids' => array_values(array_unique($drift)),
            'nonMonotonicSampleRowids' => array_values(array_unique($nonMonotonic)),
            'checks' => $checks,
            'signature' => self::signature([$limit, $offset, $selected, $checks, $nonMonotonic]),
        ];
    }

    private static function normalizeKey(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtolower((string) $value);
    }

    private static function firstCounter(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException('SQLite next203 STAT4 nlt counter must be a non-empty string');
        }
        $first = preg_split('/\s+/', trim($value))[0] ?? '';
        if (!ctype_digit($first)) {
            throw new \InvalidArgumentException('SQLite next203 STAT4 nlt counter must start with a non-negative integer');
        }

        return (int) $first;
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param array<string,mixed> $boundaryFence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $boundaryFence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'Stat4BoundarySampleFence',
            'mode' => 'next203-current-source-stat4-expression-partial-boundary',
            'rowids' => $boundaryFence['selectedRowids'],
            'boundaryKeys' => $boundaryFence['boundaryKeys'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
