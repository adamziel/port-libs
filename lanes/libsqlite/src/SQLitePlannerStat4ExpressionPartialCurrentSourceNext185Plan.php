<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext185Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext182Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) (($base['selectedPlan']['name'] ?? ''));
        $preparedIndex = self::indexByName($preparedSource, $selectedName);
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $preparedSamples = self::sampleRows($preparedIndex);
        $currentSamples = self::sampleRows($currentIndex);
        $currentRowsById = self::rowsById($currentSource);
        $windowRowids = self::intList($base['limitWindow']['rowids'] ?? null, 'limitWindow.rowids');
        $missing = array_values(array_filter(
            $windowRowids,
            static fn (int $rowid): bool => !isset($currentRowsById[$rowid]),
        ));
        $currentSampleRowids = array_values(array_unique(array_column($currentSamples, 'rowid')));
        sort($currentSampleRowids);
        $preparedSampleRowids = array_values(array_unique(array_column($preparedSamples, 'rowid')));
        sort($preparedSampleRowids);
        $missingSampleRowids = array_values(array_filter(
            $currentSampleRowids,
            static fn (int $rowid): bool => !isset($currentRowsById[$rowid]),
        ));
        $sampleDelta = [
            'preparedCount' => count($preparedSamples),
            'currentCount' => count($currentSamples),
            'preparedRowids' => $preparedSampleRowids,
            'currentRowids' => $currentSampleRowids,
            'missingCurrentSampleRowids' => $missingSampleRowids,
            'preparedSignature' => self::signature($preparedSamples),
            'currentSignature' => self::signature($currentSamples),
        ];
        $sampleDelta['changed'] = $sampleDelta['preparedSignature'] !== $sampleDelta['currentSignature'];
        $provenance = self::windowProvenance($windowRowids, $currentRowsById, $currentSamples);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next182-ready'
            && $sampleDelta['changed'] === true
            && $missing === []
            && $missingSampleRowids === []
            && self::allFromCurrent($provenance);

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next185-ready' : 'requires-current-source-reprepare',
            'sampleDeltaFence' => $sampleDelta,
            'currentSourceRowProvenance' => $provenance,
            'missingCurrentRowids' => $missing,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next185Ready' => $ready,
                'next185CurrentSampleChanged' => $sampleDelta['changed'],
                'next185CurrentSampleRowids' => $currentSampleRowids,
                'next185WindowRowids' => $windowRowids,
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next185SampleDeltaSignature' => self::signature($sampleDelta),
                'next185ProvenanceSignature' => self::signature($provenance),
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $windowRowids,
                $sampleDelta
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT185 SAMPLE PROVENANCE '
                . $selectedName
                . ($ready ? ' CURRENT ROWIDS VERIFIED' : ' REQUIRES CURRENT SOURCE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext182Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next185',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next185 reuses current-source STAT4 expression partial LIMIT windows and adds current sample rowid provenance fencing',
            'non_overlap' => 'avoids accepted next182 LIMIT/OFFSET covering windows, next180 descending scans, next169 cost fences, expression ORDER BY, JSON, WAL, VFS, B-tree, and trigger clusters; this slice only rejects stale prepared STAT4 samples when current-source window rowids must be proven against current rows',
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
            throw new \InvalidArgumentException('SQLite next185 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next185 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next185 selected index missing from source');
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array{key:mixed,rowid:int,neq:mixed,nlt:mixed,ndlt:mixed}>
     */
    private static function sampleRows(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite next185 needs STAT4 sample list');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next185 STAT4 samples must be arrays');
            }
            $values = $sample['sample'] ?? null;
            if (!is_array($values) || !array_key_exists(0, $values) || !array_key_exists(1, $values)) {
                throw new \InvalidArgumentException('SQLite next185 STAT4 samples need expression key and rowid');
            }
            if (!is_int($values[1]) && !ctype_digit((string) $values[1])) {
                throw new \InvalidArgumentException('SQLite next185 STAT4 sample rowid must be an integer');
            }
            $out[] = [
                'key' => $values[0],
                'rowid' => (int) $values[1],
                'neq' => $sample['neq'] ?? null,
                'nlt' => $sample['nlt'] ?? null,
                'ndlt' => $sample['ndlt'] ?? null,
            ];
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
            throw new \InvalidArgumentException('SQLite next185 needs current rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists('rowid', $row)) {
                throw new \InvalidArgumentException('SQLite next185 current rows need rowid');
            }
            if (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid'])) {
                throw new \InvalidArgumentException('SQLite next185 current rowid must be an integer');
            }
            $out[(int) $row['rowid']] = $row;
        }

        return $out;
    }

    /**
     * @param mixed $value
     * @return list<int>
     */
    private static function intList(mixed $value, string $name): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next185 needs integer list ' . $name);
        }
        $out = [];
        foreach ($value as $item) {
            if (!is_int($item) && !ctype_digit((string) $item)) {
                throw new \InvalidArgumentException('SQLite next185 list contains non-integer ' . $name);
            }
            $out[] = (int) $item;
        }

        return $out;
    }

    /**
     * @param list<int> $rowids
     * @param array<int,array<string,mixed>> $rowsById
     * @param list<array{key:mixed,rowid:int,neq:mixed,nlt:mixed,ndlt:mixed}> $samples
     * @return list<array<string,mixed>>
     */
    private static function windowProvenance(array $rowids, array $rowsById, array $samples): array
    {
        $sampleMap = [];
        foreach ($samples as $sample) {
            $sampleMap[$sample['rowid']] = $sample;
        }
        $out = [];
        foreach ($rowids as $rowid) {
            $row = $rowsById[$rowid] ?? null;
            $out[] = [
                'rowid' => $rowid,
                'source' => $row === null ? 'missing-current-row' : 'current',
                'option_name' => is_array($row) ? ($row['option_name'] ?? null) : null,
                'sampleKey' => $sampleMap[$rowid]['key'] ?? null,
                'stat4Anchor' => isset($sampleMap[$rowid]),
            ];
        }

        return $out;
    }

    /** @param list<array<string,mixed>> $provenance */
    private static function allFromCurrent(array $provenance): bool
    {
        foreach ($provenance as $row) {
            if (($row['source'] ?? null) !== 'current') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param list<int> $rowids
     * @param array<string,mixed> $sampleDelta
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $rowids, array $sampleDelta): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'Stat4CurrentSampleFence',
            'mode' => 'next185-current-source-stat4-expression-partial-provenance',
            'rowids' => $rowids,
            'currentSampleCount' => $sampleDelta['currentCount'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
