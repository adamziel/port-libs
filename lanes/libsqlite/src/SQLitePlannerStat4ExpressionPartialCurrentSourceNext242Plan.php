<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext242Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext239Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $index = self::indexByName($currentSource, $selectedName);
        $fence = self::histogramFence(self::rows($currentSource), $whereTerms, $index);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next239-ready'
            && $fence['ready'] === true;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next242-ready' : 'requires-current-source-stat4-histogram-reprepare',
            'stat4HistogramFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next242Ready' => $ready,
                'next242MatchedSamples' => $fence['matchedSampleCount'],
                'next242RejectedSamples' => $fence['rejectedSamples'],
                'next242ProofSignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next242HistogramReady' => $ready,
                'next242HistogramSignature' => $fence['proofSignature'],
                'next242RejectedSamples' => $fence['rejectedSamples'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT242 HISTOGRAM FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 HISTOGRAM VERIFIED' : ' REQUIRES CURRENT STAT4 HISTOGRAM REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext239Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next242',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next242 reuses current-source STAT4 expression partial rows and validates current histogram counters',
            'non_overlap' => 'adds STAT4 neq/nlt/ndlt histogram validation after accepted next239 partial cardinality estimates; avoids payload, page membership, sample predicate, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $whereTerms
     * @param array<string,mixed> $index
     * @return array<string,mixed>
     */
    private static function histogramFence(array $rows, array $whereTerms, array $index): array
    {
        $partialRows = [];
        foreach ($rows as $row) {
            if (self::rowSatisfiesTerms($row, $whereTerms)) {
                $partialRows[] = [
                    'rowid' => self::rowid($row),
                    'expressionKey' => self::rowExpressionKey($row),
                    'blogId' => (int) ($row['blog_id'] ?? 0),
                ];
            }
        }
        usort($partialRows, static function (array $left, array $right): int {
            $key = strcmp($left['expressionKey'], $right['expressionKey']);
            if ($key !== 0) {
                return $key;
            }
            $blog = $left['blogId'] <=> $right['blogId'];
            if ($blog !== 0) {
                return $blog;
            }

            return $left['rowid'] <=> $right['rowid'];
        });

        $samples = self::stat4Samples($index);
        $proofs = [];
        $rejected = [];
        foreach ($samples as $sample) {
            $proof = self::sampleHistogramProof($sample, $partialRows);
            $proofs[] = $proof;
            if (!$proof['matchesCurrentHistogram']) {
                $rejected[] = $proof['sampleKey'] . ':' . $proof['sampleBlogId'];
            }
        }

        $ready = $rejected === [] && $proofs !== [];
        $proof = [
            'partialRowCount' => count($partialRows),
            'partialRowids' => array_map(static fn (array $row): int => $row['rowid'], $partialRows),
            'sampleCount' => count($samples),
            'matchedSampleCount' => count($samples) - count($rejected),
            'sampleProofs' => $proofs,
            'rejectedSamples' => $rejected,
            'histogramSignature' => self::signature([$partialRows, $samples]),
            'indexName' => (string) ($index['name'] ?? ''),
        ];

        return $proof + [
            'ready' => $ready,
            'rejectedReason' => $ready ? null : 'stale-stat4-histogram-counters',
            'proofSignature' => self::signature($proof),
        ];
    }

    /**
     * @param array{key:string,blogId:int,neq:list<int>,nlt:list<int>,ndlt:list<int>} $sample
     * @param list<array{rowid:int,expressionKey:string,blogId:int}> $rows
     * @return array<string,mixed>
     */
    private static function sampleHistogramProof(array $sample, array $rows): array
    {
        $key = $sample['key'];
        $blogId = $sample['blogId'];
        $sameKey = 0;
        $sameComposite = 0;
        $lessKey = 0;
        $lessComposite = 0;
        $distinctKeys = [];
        $distinctComposite = [];

        foreach ($rows as $row) {
            $rowKey = $row['expressionKey'];
            $rowBlog = $row['blogId'];
            if ($rowKey === $key) {
                ++$sameKey;
                if ($rowBlog === $blogId) {
                    ++$sameComposite;
                }
            }
            if ($rowKey < $key) {
                ++$lessKey;
                $distinctKeys[$rowKey] = true;
            }
            if ($rowKey < $key || ($rowKey === $key && $rowBlog < $blogId)) {
                ++$lessComposite;
                $distinctComposite[$rowKey . "\0" . $rowBlog] = true;
            }
        }

        $expected = [
            'neq' => [$sameKey, $sameComposite],
            'nlt' => [$lessKey, $lessComposite],
            'ndlt' => [count($distinctKeys), count($distinctComposite)],
        ];
        $actual = [
            'neq' => $sample['neq'],
            'nlt' => $sample['nlt'],
            'ndlt' => $sample['ndlt'],
        ];

        return [
            'sampleKey' => $key,
            'sampleBlogId' => $blogId,
            'expected' => $expected,
            'actual' => $actual,
            'matchesCurrentHistogram' => $expected === $actual,
        ];
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next242 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next242 source indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next242 selected current index missing');
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private static function rows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next242 needs current source rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next242 current source rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $index @return list<array{key:string,blogId:int,neq:list<int>,nlt:list<int>,ndlt:list<int>}> */
    private static function stat4Samples(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next242 needs stat4Samples');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 2) {
                throw new \InvalidArgumentException('SQLite next242 stat4 samples are malformed');
            }
            $out[] = [
                'key' => strtolower((string) $sample['sample'][0]),
                'blogId' => (int) ($sample['sample'][2] ?? 0),
                'neq' => self::counterPair($sample['neq'] ?? null, 'neq'),
                'nlt' => self::counterPair($sample['nlt'] ?? null, 'nlt'),
                'ndlt' => self::counterPair($sample['ndlt'] ?? null, 'ndlt'),
            ];
        }

        return $out;
    }

    /** @return list<int> */
    private static function counterPair(mixed $value, string $name): array
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('SQLite next242 stat4 ' . $name . ' counter must be a string');
        }
        $parts = preg_split('/\s+/', trim($value));
        if (!is_array($parts) || count($parts) < 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
            throw new \InvalidArgumentException('SQLite next242 stat4 ' . $name . ' counter must have two integers');
        }

        return [(int) $parts[0], (int) $parts[1]];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $terms
     */
    private static function rowSatisfiesTerms(array $row, array $terms): bool
    {
        foreach ($terms as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left)) {
                return false;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $value = array_key_exists('expression', $left)
                ? self::rowExpressionKey($row)
                : ($row[(string) ($left['column'] ?? '')] ?? null);
            if ($operator === '=' && $value != ($term['right'] ?? null)) {
                return false;
            }
            if ($operator === 'IS NOT NULL' && $value === null) {
                return false;
            }
            if ($operator === 'LIKE' && !self::likePrefix((string) $value, (string) ($term['right'] ?? ''))) {
                return false;
            }
            if ($operator === 'BETWEEN') {
                $stringValue = strtolower((string) $value);
                $lower = self::stringOrNull($term['lower'] ?? null);
                $upper = self::stringOrNull($term['upper'] ?? null);
                if (($lower !== null && $stringValue < $lower) || ($upper !== null && $stringValue > $upper)) {
                    return false;
                }
            }
            if (in_array($operator, ['>', '>=', '<', '<='], true)) {
                $comparison = strcmp(strtolower((string) $value), strtolower((string) ($term['right'] ?? '')));
                if (($operator === '>' && $comparison <= 0)
                    || ($operator === '>=' && $comparison < 0)
                    || ($operator === '<' && $comparison >= 0)
                    || ($operator === '<=' && $comparison > 0)
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function likePrefix(string $value, string $pattern): bool
    {
        if ($pattern === 'plugin_%') {
            return str_starts_with(strtolower($value), 'plugin_');
        }
        if (str_ends_with($pattern, '%') && !str_contains(substr($pattern, 0, -1), '_')) {
            return str_starts_with(strtolower($value), strtolower(substr($pattern, 0, -1)));
        }

        return strtolower($value) === strtolower($pattern);
    }

    /** @param array<string,mixed> $row */
    private static function rowExpressionKey(array $row): string
    {
        if (!array_key_exists('option_name', $row)) {
            throw new \InvalidArgumentException('SQLite next242 current row needs option_name');
        }

        return strtolower((string) $row['option_name']);
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next242 rowid must be an integer');
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
            'opcode' => 'ValidateCurrentSourceStat4Histogram',
            'mode' => 'next242-current-source-stat4-expression-partial-histogram',
            'partialRowCount' => $fence['partialRowCount'],
            'sampleCount' => $fence['sampleCount'],
            'matchedSampleCount' => $fence['matchedSampleCount'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : strtolower((string) $value);
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
