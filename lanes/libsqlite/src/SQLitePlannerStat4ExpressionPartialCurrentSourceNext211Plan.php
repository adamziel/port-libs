<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext211Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext209Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $fence = self::seekWindowFence(
            self::seekWindows($currentIndex),
            self::currentRowsByRowid($currentSource),
            self::matchedRows($base),
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next209-ready'
            && $fence['allStat4SeekSamplesResolveToCurrentSource'] === true
            && $fence['allWindowRowidsResolveToCurrentSource'] === true
            && $fence['allSelectedRowsRemainInsideCurrentStat4Windows'] === true;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next211-ready' : 'requires-current-source-stat4-seek-window-reprepare',
            'stat4SeekWindowFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next211Ready' => $ready,
                'next211CurrentStat4SeekWindowSignature' => $fence['currentStat4SeekWindowSignature'],
                'next211CurrentStat4SeekProofSignature' => $fence['proofSignature'],
                'next211StaleStat4SampleRowids' => $fence['staleStat4SampleRowids'],
                'next211MissingCurrentWindowRowids' => $fence['missingCurrentWindowRowids'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next211CurrentStat4SeekWindowSignature' => $fence['currentStat4SeekWindowSignature'],
                'next211CurrentStat4SeekProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT211 SEEK WINDOW FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 SEEK WINDOW PROVED' : ' REQUIRES CURRENT STAT4 SEEK WINDOW REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext209Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next211',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next211 reuses lane-local STAT4 expression partial current-source fences and adds seek-window rowid provenance checks over existing row payloads',
            'non_overlap' => 'avoids accepted next209 grouped partial OR-arm admission, next206 single-term partial OR fencing, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only admits reuse when STAT4 seek-window probe samples and selected rowids are proven against the current source',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next211 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next211 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next211 selected index missing from source');
    }

    /** @param array<string,mixed> $index @return list<array<string,mixed>> */
    private static function seekWindows(array $index): array
    {
        $windows = $index['stat4SeekWindows'] ?? null;
        if (!is_array($windows) || !array_is_list($windows) || $windows === []) {
            throw new \InvalidArgumentException('SQLite next211 selected index needs stat4SeekWindows');
        }

        $out = [];
        foreach ($windows as $window) {
            if (!is_array($window)) {
                throw new \InvalidArgumentException('SQLite next211 seek windows must be arrays');
            }
            $rowids = $window['rowids'] ?? null;
            if (!is_array($rowids) || !array_is_list($rowids)) {
                throw new \InvalidArgumentException('SQLite next211 seek window rowids must be a list');
            }
            $out[] = [
                'name' => (string) ($window['name'] ?? ('window-' . count($out))),
                'lowerSample' => self::sample($window['lowerSample'] ?? null),
                'upperSample' => self::sample($window['upperSample'] ?? null),
                'rowids' => array_map(static fn (mixed $rowid): int => self::rowidValue($rowid), $rowids),
            ];
        }

        return $out;
    }

    /** @return array{rowid:int,key:string} */
    private static function sample(mixed $sample): array
    {
        if (!is_array($sample)) {
            throw new \InvalidArgumentException('SQLite next211 seek window samples must be arrays');
        }

        return [
            'rowid' => self::rowidValue($sample['rowid'] ?? null),
            'key' => strtolower((string) ($sample['key'] ?? '')),
        ];
    }

    private static function rowidValue(mixed $rowid): int
    {
        if (!is_int($rowid) && !ctype_digit((string) $rowid)) {
            throw new \InvalidArgumentException('SQLite next211 rowids must be integers');
        }

        return (int) $rowid;
    }

    /**
     * @param array<string,mixed> $source
     * @return array<int,array<string,mixed>>
     */
    private static function currentRowsByRowid(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next211 needs current source rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next211 current rows must be arrays');
            }
            $out[self::rowid($row)] = $row;
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $windows
     * @param array<int,array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $matchedRows
     * @return array<string,mixed>
     */
    private static function seekWindowFence(array $windows, array $currentRows, array $matchedRows): array
    {
        $matchedRowids = array_map(static fn (array $row): int => self::rowid($row), $matchedRows);
        $matchedSet = array_fill_keys($matchedRowids, true);
        $allWindowRowids = [];
        $staleSamples = [];
        $missingRowids = [];
        $outsideWindow = [];
        $proofs = [];

        foreach ($windows as $window) {
            $lower = self::sampleProof($window['lowerSample'], $currentRows);
            $upper = self::sampleProof($window['upperSample'], $currentRows);
            foreach ([$lower, $upper] as $sampleProof) {
                if (!$sampleProof['currentKeyMatchesSample']) {
                    $staleSamples[] = $sampleProof['rowid'];
                }
            }

            $windowMissing = [];
            foreach ($window['rowids'] as $rowid) {
                $allWindowRowids[$rowid] = true;
                if (!isset($currentRows[$rowid])) {
                    $missingRowids[] = $rowid;
                    $windowMissing[] = $rowid;
                }
            }

            $proofs[] = [
                'name' => $window['name'],
                'lowerSampleProof' => $lower,
                'upperSampleProof' => $upper,
                'rowids' => $window['rowids'],
                'missingCurrentRowids' => $windowMissing,
                'coversMatchedRowids' => array_values(array_intersect($matchedRowids, $window['rowids'])),
            ];
        }

        foreach ($matchedRowids as $rowid) {
            if (!isset($allWindowRowids[$rowid])) {
                $outsideWindow[] = $rowid;
            }
        }

        $staleSamples = array_values(array_unique($staleSamples));
        $missingRowids = array_values(array_unique($missingRowids));
        $outsideWindow = array_values(array_unique($outsideWindow));

        return [
            'currentStat4SeekWindows' => $windows,
            'currentStat4SeekWindowSignature' => self::signature($windows),
            'matchedRowids' => $matchedRowids,
            'windowProofs' => $proofs,
            'staleStat4SampleRowids' => $staleSamples,
            'missingCurrentWindowRowids' => $missingRowids,
            'matchedRowidsOutsideCurrentStat4Windows' => $outsideWindow,
            'allStat4SeekSamplesResolveToCurrentSource' => $staleSamples === [],
            'allWindowRowidsResolveToCurrentSource' => $missingRowids === [],
            'allSelectedRowsRemainInsideCurrentStat4Windows' => $outsideWindow === [] && $matchedSet !== [],
            'proofSignature' => self::signature([$proofs, $matchedRowids, $staleSamples, $missingRowids, $outsideWindow]),
        ];
    }

    /**
     * @param array{rowid:int,key:string} $sample
     * @param array<int,array<string,mixed>> $currentRows
     * @return array<string,mixed>
     */
    private static function sampleProof(array $sample, array $currentRows): array
    {
        $row = $currentRows[$sample['rowid']] ?? null;
        $currentKey = is_array($row) ? self::expressionKey($row) : null;

        return [
            'rowid' => $sample['rowid'],
            'sampleKey' => $sample['key'],
            'currentKey' => $currentKey,
            'currentRowPresent' => is_array($row),
            'currentKeyMatchesSample' => is_array($row) && $currentKey === $sample['key'],
        ];
    }

    /** @param array<string,mixed> $row */
    private static function expressionKey(array $row): string
    {
        $value = $row['option_name'] ?? null;
        return strtolower((string) $value);
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next211 rowid must be an integer');
        }

        return (int) $row['rowid'];
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next211 needs matched row list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next211 matched rows must be arrays');
            }
        }

        return $rows;
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
            'opcode' => 'RecheckCurrentStat4SeekWindow',
            'mode' => 'next211-current-source-stat4-expression-partial-seek-window',
            'rowids' => $fence['matchedRowids'],
            'windowSignature' => $fence['currentStat4SeekWindowSignature'],
            'proofSignature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
