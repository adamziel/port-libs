<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext173Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $whereTerms, array $neededColumns): array
    {
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext167Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
        );
        $selected = self::arrayValue($base, 'selectedPlan');
        $indexName = (string) ($selected['name'] ?? '');
        $expression = (string) ($selected['expression'] ?? 'lower(option_name)');
        $collation = (string) ($selected['collation'] ?? 'BINARY');
        $currentWindow = self::arrayValue($base, 'currentSampleWindow');
        $sampleFence = self::arrayValue($base, 'postAnalyzeSampleFence');
        $sampleFanout = self::sampleFanout($currentSource, $indexName, $currentWindow, $expression, $collation);
        $duplicateBuckets = array_values(array_filter(
            $sampleFanout,
            static fn (array $bucket): bool => count($bucket['rowids']) > 1,
        ));
        $fanoutRowids = self::uniqueRowids($sampleFanout);
        $sampleOnlyRowids = array_values(array_diff(self::intList($sampleFence['currentRowids'] ?? []), $fanoutRowids));
        $windowRowids = self::intList($currentWindow['rowids'] ?? []);
        sort($windowRowids, SORT_NUMERIC);
        $completeFanout = $duplicateBuckets !== []
            && array_reduce($duplicateBuckets, static fn (bool $carry, array $bucket): bool => $carry && ($bucket['complete'] ?? false) === true, true);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next167-ready'
            && $duplicateBuckets !== []
            && $sampleOnlyRowids === []
            && $fanoutRowids === $windowRowids
            && $completeFanout;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next173-ready' : 'requires-next-stage',
            'duplicateStat4KeyBuckets' => $duplicateBuckets,
            'stat4SampleFanout' => $sampleFanout,
            'stat4FanoutRowids' => $fanoutRowids,
            'sampleOnlyRowidsMissingCurrentFanout' => $sampleOnlyRowids,
            'stat4DuplicateKeyCount' => count($duplicateBuckets),
            'stat4FanoutSignature' => self::signature($sampleFanout),
            'cursorProgram' => self::cursorProgram(
                self::listValue($base['cursorProgram'] ?? []),
                $duplicateBuckets,
                $fanoutRowids,
                $ready,
            ),
            'selectedPlan' => array_replace($selected, [
                'next173Ready' => $ready,
                'next173DuplicateKeyCount' => count($duplicateBuckets),
                'next173FanoutRowids' => $fanoutRowids,
                'next173FanoutSignature' => self::signature($sampleFanout),
            ]),
            'stat4Fence' => array_replace(
                self::arrayValue($base, 'stat4Fence'),
                [
                    'next173FanoutSignature' => self::signature($sampleFanout),
                    'next173DuplicateKeyCount' => count($duplicateBuckets),
                ],
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT173 DUPLICATE SAMPLE FANOUT '
                . ($indexName === '' ? 'NO INDEX' : $indexName),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext167Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next173',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next173 reuses native STAT4 expression partial current-source planning and adds duplicate-key sample fanout admission',
            'non_overlap' => 'avoids accepted next167 post-ANALYZE sample-window drift, next164 range-implies-partial proof, next161 OR probes, expression ORDER BY, JSON, WAL, VFS, and B-tree clusters; this slice only admits duplicate current rowids behind one STAT4 sample key for a partial expression index',
        ]);
    }

    /**
     * @param array<string,mixed> $window
     * @return list<array{key:mixed,sampleRowid:int,neq:int,rowids:list<int>,rowCount:int,complete:bool}>
     */
    private static function sampleFanout(array $source, string $indexName, array $window, string $expression, string $collation): array
    {
        $rowsByKey = [];
        foreach (self::listValue($window['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next173 window rows must be arrays');
            }
            $key = $row['key'] ?? null;
            $rowid = $row['rowid'] ?? null;
            if (!is_int($rowid) || $rowid < 0) {
                throw new \InvalidArgumentException('SQLite next173 window rowid must be a non-negative integer');
            }
            $rowsByKey[self::keySignature($key, $collation)][] = $rowid;
        }

        $buckets = [];
        foreach (self::indexSamples($source, $indexName) as $sample) {
            $signature = self::keySignature($sample['key'], $collation);
            if (!isset($rowsByKey[$signature])) {
                continue;
            }
            $rowids = array_values(array_unique($rowsByKey[$signature]));
            sort($rowids, SORT_NUMERIC);
            $buckets[] = [
                'key' => $sample['key'],
                'sampleRowid' => $sample['rowid'],
                'neq' => $sample['neq'],
                'rowids' => $rowids,
                'rowCount' => count($rowids),
                'complete' => count($rowids) === $sample['neq'],
            ];
        }

        usort($buckets, static fn (array $a, array $b): int => self::compare($a['key'], $b['key'], $collation));

        return $buckets;
    }

    /**
     * @return list<array{key:mixed,rowid:int,neq:int}>
     */
    private static function indexSamples(array $source, string $indexName): array
    {
        foreach (self::listValue($source['indexes'] ?? []) as $index) {
            if (!is_array($index) || ($index['name'] ?? null) !== $indexName) {
                continue;
            }
            $samples = [];
            foreach (self::listValue($index['stat4Samples'] ?? []) as $sample) {
                if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 2) {
                    throw new \InvalidArgumentException('SQLite next173 STAT4 samples need key and rowid');
                }
                $samples[] = [
                    'key' => $sample['sample'][0],
                    'rowid' => self::intValue($sample['sample'][1]),
                    'neq' => self::firstStatInt($sample['neq'] ?? 1),
                ];
            }

            return $samples;
        }

        return [];
    }

    /**
     * @param list<array<string,mixed>> $cursor
     * @param list<array<string,mixed>> $duplicateBuckets
     * @param list<int> $fanoutRowids
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $cursor, array $duplicateBuckets, array $fanoutRowids, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'FallbackFullScan', 'reason' => 'STAT4 duplicate-key fanout not complete']];
        }

        $program = [];
        foreach ($cursor as $operation) {
            if (!is_array($operation)) {
                continue;
            }
            if (($operation['opcode'] ?? null) === 'ResultRow') {
                $program[] = [
                    'opcode' => 'ExpandStat4DuplicateKeyFanout',
                    'buckets' => $duplicateBuckets,
                    'rowids' => $fanoutRowids,
                ];
            }
            $program[] = $operation;
        }

        return $program;
    }

    /** @param list<array<string,mixed>> $buckets */
    private static function uniqueRowids(array $buckets): array
    {
        $rowids = [];
        foreach ($buckets as $bucket) {
            foreach (self::intList($bucket['rowids'] ?? []) as $rowid) {
                $rowids[$rowid] = $rowid;
            }
        }
        ksort($rowids, SORT_NUMERIC);

        return array_values($rowids);
    }

    /** @return array<string,mixed> */
    private static function arrayValue(array $array, string $key): array
    {
        $value = $array[$key] ?? null;
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next173 needs array ' . $key);
        }

        return $value;
    }

    /** @return list<mixed> */
    private static function listValue(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /** @return list<int> */
    private static function intList(mixed $value): array
    {
        $out = [];
        foreach (self::listValue($value) as $item) {
            $out[] = self::intValue($item);
        }

        return $out;
    }

    private static function intValue(mixed $value): int
    {
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new \InvalidArgumentException('SQLite next173 integer value expected');
        }

        return (int) $value;
    }

    private static function firstStatInt(mixed $value): int
    {
        if (is_string($value)) {
            $value = preg_split('/\s+/', trim($value))[0] ?? '0';
        }

        return self::intValue($value);
    }

    private static function keySignature(mixed $key, string $collation): string
    {
        $value = (string) $key;
        if (strtoupper($collation) === 'NOCASE') {
            $value = strtolower($value);
        }

        return $value;
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        return self::keySignature($left, $collation) <=> self::keySignature($right, $collation);
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
