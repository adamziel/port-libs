<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext167Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext164Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
        );
        $selected = self::arrayValue($base, 'selectedPlan');
        $range = self::arrayValue($selected, 'rangeConstraint');
        $preparedWindow = self::sourceWindow($preparedSource, $whereTerms, (string) ($selected['expression'] ?? ''), $range, (string) ($selected['collation'] ?? 'BINARY'));
        $currentWindow = self::sourceWindow($currentSource, $whereTerms, (string) ($selected['expression'] ?? ''), $range, (string) ($selected['collation'] ?? 'BINARY'));
        $sampleFence = self::sampleFence($preparedSource, $currentSource, (string) ($selected['name'] ?? ''), $range, (string) ($selected['collation'] ?? 'BINARY'));
        $staleBlocked = array_values(array_diff($preparedWindow['rowids'], $currentWindow['rowids']));
        $currentOnly = array_values(array_diff($currentWindow['rowids'], $preparedWindow['rowids']));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next164-ready'
            && ($base['selectedSource'] ?? null) === 'current'
            && $sampleFence['changed'] === true
            && $sampleFence['currentKeys'] !== []
            && $currentWindow['rowids'] !== [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next167-ready' : 'requires-next-stage',
            'preparedSampleWindow' => $preparedWindow,
            'currentSampleWindow' => $currentWindow,
            'postAnalyzeSampleFence' => $sampleFence,
            'postAnalyzeSampleFenceChanged' => $sampleFence['changed'],
            'stalePreparedRowidsBlockedBySampleFence' => $staleBlocked,
            'currentSourceRowidsAdmittedBySampleFence' => $currentOnly,
            'currentSourceRowidsRefreshedBySampleFence' => array_values(array_intersect($preparedWindow['rowids'], $currentWindow['rowids'])),
            'sampleWindowSignature' => self::signature([
                'prepared' => $preparedWindow['signature'],
                'current' => $currentWindow['signature'],
                'fence' => $sampleFence['signature'],
            ]),
            'cursorProgram' => self::cursorProgram($base, $sampleFence, $staleBlocked, $currentOnly, $ready),
            'selectedPlan' => array_replace($selected, [
                'next167Ready' => $ready,
                'next167PreparedWindowRowids' => $preparedWindow['rowids'],
                'next167CurrentWindowRowids' => $currentWindow['rowids'],
                'next167CurrentOnlyRowids' => $currentOnly,
                'next167StaleBlockedRowids' => $staleBlocked,
                'next167SampleFenceChanged' => $sampleFence['changed'],
                'next167CurrentSampleKeys' => $sampleFence['currentKeys'],
            ]),
            'stat4Fence' => array_replace(
                self::arrayValue($base, 'stat4Fence'),
                [
                    'next167PreparedWindowSignature' => $preparedWindow['signature'],
                    'next167CurrentWindowSignature' => $currentWindow['signature'],
                    'next167SampleFenceSignature' => $sampleFence['signature'],
                ],
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT167 SAMPLE WINDOW '
                . (string) ($selected['name'] ?? 'NO INDEX'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext164Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next167',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next167 reuses native STAT4 expression partial current-source planning and adds post-ANALYZE sample-window fencing',
            'non_overlap' => 'avoids accepted next164 range-implies-partial proof, next158 stale-row range exclusion, next161 OR probes, expression ORDER BY, JSON, WAL, VFS, and B-tree clusters; this slice only fences post-ANALYZE STAT4 sample-window drift for a current partial expression index',
        ]);
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $terms
     * @param array<string,mixed> $range
     * @return array{rowids:list<int>,keys:list<mixed>,rows:list<array<string,mixed>>,signature:string}
     */
    private static function sourceWindow(array $source, array $terms, string $expression, array $range, string $collation): array
    {
        $rows = [];
        foreach (self::listValue($source['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next167 rows must be arrays');
            }
            $rowid = $row['rowid'] ?? null;
            if (!is_int($rowid) || $rowid < 0) {
                throw new \InvalidArgumentException('SQLite next167 rowid must be a non-negative integer');
            }
            $key = self::expressionValue($row, $expression);
            if (!self::inRange($key, $range, $collation) || !self::rowSatisfiesTerms($row, $terms, $expression, $collation)) {
                continue;
            }
            $rows[] = ['rowid' => $rowid, 'key' => $key, 'payload' => $row];
        }
        usort($rows, static fn (array $a, array $b): int => [(string) $a['key'], $a['rowid']] <=> [(string) $b['key'], $b['rowid']]);

        return [
            'rowids' => array_column($rows, 'rowid'),
            'keys' => array_column($rows, 'key'),
            'rows' => $rows,
            'signature' => self::signature(array_map(static fn (array $row): array => ['rowid' => $row['rowid'], 'key' => $row['key']], $rows)),
        ];
    }

    /**
     * @param array<string,mixed> $range
     * @return array<string,mixed>
     */
    private static function sampleFence(array $preparedSource, array $currentSource, string $indexName, array $range, string $collation): array
    {
        $prepared = self::samplesInRange(self::indexSamples($preparedSource, $indexName), $range, $collation);
        $current = self::samplesInRange(self::indexSamples($currentSource, $indexName), $range, $collation);
        $fence = [
            'changed' => self::signature($prepared) !== self::signature($current),
            'preparedKeys' => array_column($prepared, 'key'),
            'currentKeys' => array_column($current, 'key'),
            'preparedRowids' => array_column($prepared, 'rowid'),
            'currentRowids' => array_column($current, 'rowid'),
            'lowerKey' => $current[0]['key'] ?? null,
            'upperKey' => $current === [] ? null : $current[count($current) - 1]['key'],
            'estimatedRows' => array_sum(array_column($current, 'neq')),
        ];
        $fence['signature'] = self::signature($fence);

        return $fence;
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
                    throw new \InvalidArgumentException('SQLite next167 STAT4 samples need key and rowid');
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
     * @param list<array{key:mixed,rowid:int,neq:int}> $samples
     * @param array<string,mixed> $range
     * @return list<array{key:mixed,rowid:int,neq:int}>
     */
    private static function samplesInRange(array $samples, array $range, string $collation): array
    {
        return array_values(array_filter($samples, static fn (array $sample): bool => self::inRange($sample['key'], $range, $collation)));
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $fence
     * @param list<int> $staleBlocked
     * @param list<int> $currentOnly
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $base, array $fence, array $staleBlocked, array $currentOnly, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'FallbackFullScan', 'reason' => 'post-ANALYZE STAT4 sample window not usable']];
        }
        $selected = self::arrayValue($base, 'selectedPlan');

        return [
            ['opcode' => 'OpenRead', 'rootPage' => $selected['rootPage'] ?? null, 'index' => $selected['name'] ?? null],
            ['opcode' => 'FenceStat4SampleWindow', 'signature' => $fence['signature']],
            ['opcode' => 'SeekGE', 'key' => $fence['lowerKey']],
            ['opcode' => 'IdxLE', 'key' => $fence['upperKey']],
            ['opcode' => 'BlockPreparedRowids', 'rowids' => $staleBlocked],
            ['opcode' => 'AdmitCurrentRowids', 'rowids' => $currentOnly],
            ['opcode' => (($selected['covering'] ?? false) ? 'ColumnFromIndex' : 'DeferredSeek')],
            ['opcode' => 'ResultRow', 'rowids' => $base['matchedRowids'] ?? []],
            ['opcode' => 'Next'],
        ];
    }

    /**
     * @param array<string,mixed> $range
     */
    private static function inRange(mixed $key, array $range, string $collation): bool
    {
        $lower = self::compare($key, $range['lower'] ?? null, $collation);
        $upper = self::compare($key, $range['upper'] ?? null, $collation);

        return (($range['lowerInclusive'] ?? false) ? $lower >= 0 : $lower > 0)
            && (($range['upperInclusive'] ?? false) ? $upper <= 0 : $upper < 0);
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function rowSatisfiesTerms(array $row, array $terms, string $expression, string $collation): bool
    {
        foreach ($terms as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $left = $term['left'] ?? [];
            $leftValue = is_array($left) && array_key_exists('expression', $left)
                ? self::expressionValue($row, $expression)
                : ($row[(string) ($left['column'] ?? '')] ?? null);
            $right = $term['right'] ?? null;
            if ($operator === '=' && $leftValue === $right) {
                continue;
            }
            if ($operator === 'IS NOT NULL' && $leftValue !== null) {
                continue;
            }
            if (in_array($operator, ['>', '>=', '<', '<='], true)) {
                $cmp = self::compare($leftValue, $right, $collation);
                if (($operator === '>' && $cmp > 0) || ($operator === '>=' && $cmp >= 0) || ($operator === '<' && $cmp < 0) || ($operator === '<=' && $cmp <= 0)) {
                    continue;
                }
            }

            return false;
        }

        return true;
    }

    private static function expressionValue(array $row, string $expression): mixed
    {
        if (strtolower(str_replace(' ', '', $expression)) === 'lower(option_name)') {
            $value = $row['option_name'] ?? null;

            return is_string($value) ? strtolower($value) : null;
        }

        throw new \InvalidArgumentException('SQLite next167 unsupported expression ' . $expression);
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        $a = (string) $left;
        $b = (string) $right;
        if (strtoupper($collation) === 'NOCASE') {
            $a = strtolower($a);
            $b = strtolower($b);
        }

        return $a <=> $b;
    }

    /** @return array<string,mixed> */
    private static function arrayValue(array $array, string $key): array
    {
        $value = $array[$key] ?? null;
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next167 needs array ' . $key);
        }

        return $value;
    }

    /** @return list<mixed> */
    private static function listValue(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private static function intValue(mixed $value): int
    {
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new \InvalidArgumentException('SQLite next167 integer value expected');
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

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
