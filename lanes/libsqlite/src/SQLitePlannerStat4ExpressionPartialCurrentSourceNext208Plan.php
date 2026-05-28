<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext208Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext206Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) (($base['selectedPlan']['name'] ?? ''));
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $samples = self::sampleRows($currentIndex);
        $matchedRows = self::matchedRows($base);
        $fence = self::selectivityFence(
            $samples,
            self::matchedExpressionKeys($base),
            $matchedRows,
            self::matchedOrArm($base)
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next206-ready'
            && $fence['ready'] === true
            && $fence['unanchoredSelectedKeys'] === []
            && $fence['rowidsMissingSampleWindow'] === []
            && $fence['monotonicNlt'] === true
            && $fence['matchedOrArm'] >= 0;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next208-ready' : 'requires-current-source-stat4-selectivity-reprepare',
            'stat4SelectivityFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next208Ready' => $ready,
                'next208MatchedOrArm' => $fence['matchedOrArm'],
                'next208SelectedSampleKeys' => $fence['selectedSampleKeys'],
                'next208EstimatedRowsFromStat4' => $fence['estimatedRowsFromStat4'],
                'next208ActualWindowRows' => $fence['actualWindowRows'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next208SelectivitySignature' => $fence['signature'],
                'next208SampleWindowSignature' => $fence['sampleWindowSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT208 OR-ARM SELECTIVITY FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 WINDOW VERIFIED' : ' REQUIRES CURRENT SOURCE STAT4 SELECTIVITY REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext206Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next208',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next208 composes current-source STAT4 partial-expression OR proof with sample-window selectivity fencing',
            'non_overlap' => 'avoids accepted next206 partial OR implication, next203 boundary-only fencing, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only admits the matched partial-OR arm when the current STAT4 sample window anchors the selected expression keys and rowids',
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
            throw new \InvalidArgumentException('SQLite next208 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next208 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next208 selected index missing from source');
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array{key:string,rowid:int,neq:int,nlt:int,ndlt:int}>
     */
    private static function sampleRows(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next208 needs STAT4 sample list');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next208 STAT4 samples must be arrays');
            }
            $values = $sample['sample'] ?? null;
            if (!is_array($values) || !array_key_exists(0, $values) || !array_key_exists(1, $values)) {
                throw new \InvalidArgumentException('SQLite next208 STAT4 samples need expression key and rowid');
            }
            if (!is_int($values[1]) && !ctype_digit((string) $values[1])) {
                throw new \InvalidArgumentException('SQLite next208 STAT4 sample rowid must be an integer');
            }
            $out[] = [
                'key' => strtolower((string) $values[0]),
                'rowid' => (int) $values[1],
                'neq' => self::firstStatInt($sample['neq'] ?? null, 'neq'),
                'nlt' => self::firstStatInt($sample['nlt'] ?? null, 'nlt'),
                'ndlt' => self::firstStatInt($sample['ndlt'] ?? null, 'ndlt'),
            ];
        }

        return $out;
    }

    private static function firstStatInt(mixed $value, string $name): int
    {
        $parts = preg_split('/\s+/', trim((string) $value));
        if ($parts === false || $parts === [] || $parts[0] === '' || !ctype_digit($parts[0])) {
            throw new \InvalidArgumentException('SQLite next208 STAT4 ' . $name . ' must start with an integer');
        }

        return (int) $parts[0];
    }

    /**
     * @param list<array{key:string,rowid:int,neq:int,nlt:int,ndlt:int}> $samples
     * @param list<string> $selectedKeys
     * @param list<array<string,mixed>> $matchedRows
     * @return array<string,mixed>
     */
    private static function selectivityFence(array $samples, array $selectedKeys, array $matchedRows, int $matchedOrArm): array
    {
        $keys = array_values(array_unique(array_map(static fn (string $key): string => strtolower($key), $selectedKeys)));
        $sampleByKey = [];
        $sampleByRowid = [];
        $monotonic = true;
        $previousNlt = -1;
        foreach ($samples as $sample) {
            $sampleByKey[$sample['key']][] = $sample;
            $sampleByRowid[$sample['rowid']] = $sample;
            if ($sample['nlt'] < $previousNlt) {
                $monotonic = false;
            }
            $previousNlt = $sample['nlt'];
        }

        $selectedSamples = [];
        $unanchored = [];
        foreach ($keys as $key) {
            if (!isset($sampleByKey[$key])) {
                $unanchored[] = $key;
                continue;
            }
            foreach ($sampleByKey[$key] as $sample) {
                $selectedSamples[] = $sample;
            }
        }

        $rowids = [];
        $missingRowids = [];
        foreach ($matchedRows as $offset => $row) {
            $rowid = self::rowid($row);
            $rowids[] = $rowid;
            $key = $selectedKeys[$offset] ?? null;
            if (!is_string($key) || !isset($sampleByKey[strtolower($key)])) {
                $missingRowids[] = $rowid;
            }
        }

        $estimated = 0;
        foreach ($selectedSamples as $sample) {
            $estimated += max(1, $sample['neq']);
        }
        $window = [
            'matchedOrArm' => $matchedOrArm,
            'selectedKeys' => $keys,
            'selectedSamples' => $selectedSamples,
            'rowids' => $rowids,
        ];

        return [
            'ready' => $matchedOrArm >= 0 && $keys !== [] && $selectedSamples !== [] && $unanchored === [] && $missingRowids === [] && $monotonic,
            'matchedOrArm' => $matchedOrArm,
            'selectedExpressionKeys' => $keys,
            'selectedSampleKeys' => array_values(array_map(static fn (array $sample): string => $sample['key'], $selectedSamples)),
            'selectedSampleRowids' => array_values(array_map(static fn (array $sample): int => $sample['rowid'], $selectedSamples)),
            'unanchoredSelectedKeys' => $unanchored,
            'rowidsMissingSampleWindow' => $missingRowids,
            'actualWindowRows' => count($matchedRows),
            'estimatedRowsFromStat4' => $estimated,
            'monotonicNlt' => $monotonic,
            'sampleWindowSignature' => self::signature($window),
            'signature' => self::signature([$window, $estimated, $unanchored, $missingRowids, $monotonic]),
        ];
    }

    /** @param array<string,mixed> $base @return list<string> */
    private static function matchedExpressionKeys(array $base): array
    {
        $keys = $base['matchedExpressionKeys'] ?? null;
        if (!is_array($keys) || !array_is_list($keys)) {
            throw new \InvalidArgumentException('SQLite next208 needs matched expression keys');
        }

        return array_map(static fn (mixed $key): string => strtolower((string) $key), $keys);
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next208 needs matched rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next208 matched rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $base */
    private static function matchedOrArm(array $base): int
    {
        $arm = $base['partialOrPredicateFence']['matchedOrArm'] ?? null;
        if ($arm === null) {
            return -1;
        }
        if (!is_int($arm) && !ctype_digit((string) $arm)) {
            throw new \InvalidArgumentException('SQLite next208 needs matched OR arm');
        }

        return (int) $arm;
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next208 matched rowid must be an integer');
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
            'opcode' => 'VerifyStat4SelectivityWindow',
            'mode' => 'next208-current-source-stat4-expression-partial-or-selectivity',
            'matchedOrArm' => $fence['matchedOrArm'],
            'selectedSampleRowids' => $fence['selectedSampleRowids'],
            'estimatedRowsFromStat4' => $fence['estimatedRowsFromStat4'],
            'actualWindowRows' => $fence['actualWindowRows'],
            'signature' => $fence['signature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
