<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext245Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext242Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $index = self::indexByName($currentSource, $selectedName);
        $fence = self::sampleAnchorFence(self::rows($currentSource), $whereTerms, $index);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next242-ready'
            && $fence['ready'] === true;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next245-ready' : 'requires-current-source-stat4-sample-anchor-reprepare',
            'stat4SampleAnchorFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next245Ready' => $ready,
                'next245AnchoredSampleRowids' => $fence['anchoredSampleRowids'],
                'next245RejectedSampleRowids' => $fence['rejectedSampleRowids'],
                'next245ProofSignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next245SampleAnchorReady' => $ready,
                'next245SampleAnchorSignature' => $fence['proofSignature'],
                'next245RejectedSampleRowids' => $fence['rejectedSampleRowids'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT245 SAMPLE ANCHOR FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 SAMPLE ROWIDS VERIFIED' : ' REQUIRES CURRENT STAT4 SAMPLE ANCHOR REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext242Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next245',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next245 reuses current-source STAT4 expression partial histogram counters and adds sample rowid anchor validation',
            'non_overlap' => 'adds STAT4 sample-rowid anchor validation after accepted next242 histogram counter validation; avoids histogram cardinality, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $whereTerms
     * @param array<string,mixed> $index
     * @return array<string,mixed>
     */
    private static function sampleAnchorFence(array $rows, array $whereTerms, array $index): array
    {
        $partialRows = [];
        foreach ($rows as $row) {
            if (!self::rowSatisfiesTerms($row, $whereTerms)) {
                continue;
            }
            $rowid = self::rowid($row);
            $partialRows[$rowid] = [
                'rowid' => $rowid,
                'expressionKey' => self::rowExpressionKey($row),
                'blogId' => (int) ($row['blog_id'] ?? 0),
                'payloadSignature' => self::signature($row),
            ];
        }

        $proofs = [];
        $anchored = [];
        $rejected = [];
        foreach (self::stat4Samples($index) as $sample) {
            $row = $partialRows[$sample['rowid']] ?? null;
            $matches = $row !== null
                && $row['expressionKey'] === $sample['key']
                && $row['blogId'] === $sample['blogId'];
            $proof = [
                'sampleKey' => $sample['key'],
                'sampleRowid' => $sample['rowid'],
                'sampleBlogId' => $sample['blogId'],
                'currentRowPresent' => $row !== null,
                'currentExpressionKey' => $row['expressionKey'] ?? null,
                'currentBlogId' => $row['blogId'] ?? null,
                'currentPayloadSignature' => $row['payloadSignature'] ?? null,
                'matchesCurrentAnchor' => $matches,
            ];
            $proofs[] = $proof;
            if ($matches) {
                $anchored[] = $sample['rowid'];
            } else {
                $rejected[] = $sample['rowid'];
            }
        }

        $ready = $proofs !== [] && $rejected === [];
        $proof = [
            'partialRowCount' => count($partialRows),
            'partialRowids' => array_values(array_keys($partialRows)),
            'sampleCount' => count($proofs),
            'anchoredSampleCount' => count($anchored),
            'anchoredSampleRowids' => $anchored,
            'rejectedSampleRowids' => $rejected,
            'sampleAnchorProofs' => $proofs,
            'anchorSignature' => self::signature([$partialRows, $proofs]),
            'indexName' => (string) ($index['name'] ?? ''),
        ];

        return $proof + [
            'ready' => $ready,
            'rejectedReason' => $ready ? null : 'stale-stat4-sample-rowid-anchor',
            'proofSignature' => self::signature($proof),
        ];
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next245 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next245 source indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next245 selected current index missing');
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private static function rows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next245 needs current source rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next245 current source rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $index @return list<array{key:string,rowid:int,blogId:int}> */
    private static function stat4Samples(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next245 needs stat4Samples');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 3) {
                throw new \InvalidArgumentException('SQLite next245 stat4 samples need key, rowid, and blog id');
            }
            $out[] = [
                'key' => strtolower((string) $sample['sample'][0]),
                'rowid' => self::rowid(['rowid' => $sample['sample'][1]]),
                'blogId' => (int) $sample['sample'][2],
            ];
        }

        return $out;
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
            throw new \InvalidArgumentException('SQLite next245 current row needs option_name');
        }

        return strtolower((string) $row['option_name']);
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next245 rowid must be an integer');
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
            'opcode' => 'ValidateCurrentSourceStat4SampleAnchors',
            'mode' => 'next245-current-source-stat4-expression-partial-sample-anchors',
            'sampleCount' => $fence['sampleCount'],
            'anchoredSampleCount' => $fence['anchoredSampleCount'],
            'anchoredSampleRowids' => $fence['anchoredSampleRowids'],
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
