<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext205Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext203Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );

        $index = self::indexByName($currentSource, (string) ($base['selectedPlan']['name'] ?? ''));
        $samples = self::samplesByKey($index);
        $peerFence = self::peerFence(self::matchedRows($base), self::currentRows($currentSource), $samples);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next203-ready'
            && $peerFence['ready'] === true
            && $peerFence['missingSampleKeys'] === []
            && $peerFence['staleNeqKeys'] === []
            && $peerFence['nonContiguousPeerKeys'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next205-ready' : 'requires-current-source-stat4-peer-cardinality-reprepare',
            'stat4PeerCardinalityFence' => $peerFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next205Ready' => $ready,
                'next205PeerKeys' => $peerFence['peerKeys'],
                'next205StaleNeqKeys' => $peerFence['staleNeqKeys'],
                'next205PeerSignature' => $peerFence['signature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next205PeerCardinalitySignature' => $peerFence['signature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $peerFence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT205 PEER CARDINALITY FENCE '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' CURRENT PEERS VERIFIED' : ' REQUIRES CURRENT SOURCE STAT4 PEER REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext203Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next205',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next205 reuses current-source STAT4 expression partial boundary fences and adds duplicate expression-key peer cardinality admission from STAT4 neq samples',
            'non_overlap' => 'avoids accepted next203 boundary samples, next200 NOT BETWEEN residuals, next196 peer ordering, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and encoding clusters; this slice only proves duplicate expression-key peer cardinality from current STAT4 neq samples before admitting a partial expression-index LIMIT window',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next205 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next205 source index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next205 selected index missing from current source');
    }

    /** @param array<string,mixed> $index @return array<string,array<string,mixed>> */
    private static function samplesByKey(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite next205 needs STAT4 sample list');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next205 STAT4 samples must be arrays');
            }
            $values = $sample['sample'] ?? null;
            if (!is_array($values) || !array_key_exists(0, $values) || !array_key_exists(1, $values)) {
                throw new \InvalidArgumentException('SQLite next205 STAT4 sample needs expression key and rowid');
            }
            $key = self::normalizeKey($values[0]);
            if ($key === null) {
                throw new \InvalidArgumentException('SQLite next205 STAT4 sample expression key cannot be null');
            }
            $out[$key] = [
                'expressionKey' => $key,
                'rowid' => self::intValue($values[1], 'STAT4 sample rowid'),
                'neq' => self::firstCounter($sample['neq'] ?? null, 'neq'),
                'nlt' => self::firstCounter($sample['nlt'] ?? null, 'nlt'),
                'raw' => $sample,
            ];
        }

        return $out;
    }

    /** @param mixed $value @return list<array<string,mixed>> */
    private static function matchedRows(mixed $value): array
    {
        $rows = is_array($value) ? ($value['matchedRows'] ?? null) : null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next205 needs matched rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next205 matched rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private static function currentRows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next205 needs current source rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists('rowid', $row)) {
                throw new \InvalidArgumentException('SQLite next205 current source rows need rowid');
            }
            self::intValue($row['rowid'], 'current source rowid');
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $matchedRows
     * @param list<array<string,mixed>> $currentRows
     * @param array<string,array<string,mixed>> $samples
     * @return array<string,mixed>
     */
    private static function peerFence(array $matchedRows, array $currentRows, array $samples): array
    {
        $selected = [];
        foreach ($matchedRows as $position => $row) {
            $payload = $row['payload'] ?? null;
            if (!is_array($payload) || !array_key_exists('option_name', $payload)) {
                throw new \InvalidArgumentException('SQLite next205 matched rows need option_name payload');
            }
            $selected[] = [
                'position' => $position,
                'rowid' => self::intValue($row['rowid'] ?? null, 'matched rowid'),
                'expressionKey' => self::normalizeKey($payload['option_name']),
            ];
        }

        $selectedByKey = [];
        foreach ($selected as $row) {
            $key = (string) $row['expressionKey'];
            $selectedByKey[$key][] = $row;
        }

        $currentCounts = [];
        foreach ($currentRows as $row) {
            if (($row['autoload'] ?? null) !== 'yes') {
                continue;
            }
            $name = self::normalizeKey($row['option_name'] ?? null);
            if ($name === null || !str_starts_with($name, 'plugin_')) {
                continue;
            }
            $currentCounts[$name] = ($currentCounts[$name] ?? 0) + 1;
        }

        $peerKeys = [];
        $checks = [];
        $missing = [];
        $stale = [];
        $nonContiguous = [];
        foreach ($selectedByKey as $key => $rows) {
            if (count($rows) < 2) {
                continue;
            }
            $peerKeys[] = $key;
            $positions = array_column($rows, 'position');
            $contiguous = $positions === range((int) min($positions), (int) max($positions));
            if (!$contiguous) {
                $nonContiguous[] = $key;
            }
            $sample = $samples[$key] ?? null;
            if ($sample === null) {
                $missing[] = $key;
            }
            $expectedPeers = (int) ($currentCounts[$key] ?? count($rows));
            $sampleNeq = is_array($sample) ? (int) $sample['neq'] : 0;
            $sampleReady = is_array($sample) && $sampleNeq >= $expectedPeers;
            if (!$sampleReady && $sample !== null) {
                $stale[] = $key;
            }
            $checks[] = [
                'expressionKey' => $key,
                'selectedRowids' => array_column($rows, 'rowid'),
                'selectedPositions' => $positions,
                'contiguousInWindow' => $contiguous,
                'currentPeerCount' => $expectedPeers,
                'sampleNeq' => $sampleNeq,
                'sampleRowid' => is_array($sample) ? (int) $sample['rowid'] : null,
                'ready' => $contiguous && $sampleReady,
            ];
        }

        return [
            'ready' => $peerKeys !== [] && $missing === [] && $stale === [] && $nonContiguous === [],
            'peerKeys' => $peerKeys,
            'selectedPeerRowids' => $checks === []
                ? []
                : array_values(array_merge(...array_map(static fn (array $check): array => $check['selectedRowids'], $checks))),
            'missingSampleKeys' => $missing,
            'staleNeqKeys' => $stale,
            'nonContiguousPeerKeys' => $nonContiguous,
            'checks' => $checks,
            'signature' => self::signature([$peerKeys, $checks, $missing, $stale, $nonContiguous]),
        ];
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
            'opcode' => 'Stat4PeerCardinalityFence',
            'mode' => 'next205-current-source-stat4-expression-partial-peer-cardinality',
            'peerKeys' => $peerFence['peerKeys'],
            'rowids' => $peerFence['selectedPeerRowids'],
        ];

        return $program;
    }

    private static function normalizeKey(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtolower((string) $value);
    }

    private static function firstCounter(mixed $value, string $name): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("SQLite next205 STAT4 {$name} counter must be a non-empty string");
        }
        $first = preg_split('/\s+/', trim($value))[0] ?? '';
        if (!ctype_digit($first)) {
            throw new \InvalidArgumentException("SQLite next205 STAT4 {$name} counter must start with a non-negative integer");
        }

        return (int) $first;
    }

    private static function intValue(mixed $value, string $name): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next205 {$name} must be an integer");
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
