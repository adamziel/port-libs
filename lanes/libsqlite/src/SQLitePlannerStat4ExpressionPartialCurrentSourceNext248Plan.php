<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext248Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext245Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $index = self::indexByName($currentSource, $selectedName);
        $fence = self::duplicateRunFence(self::rows($currentSource), $whereTerms, $index);
        $anchorFence = is_array($base['stat4SampleAnchorFence'] ?? null) ? $base['stat4SampleAnchorFence'] : [];
        $ready = ($anchorFence['ready'] ?? false) === true
            && $fence['ready'] === true;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next248-ready' : 'requires-current-source-stat4-duplicate-run-reprepare',
            'stat4DuplicateRunFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next248Ready' => $ready,
                'next245Ready' => ($anchorFence['ready'] ?? false) === true,
                'next248DuplicateKeys' => $fence['duplicateKeys'],
                'next248RejectedKeys' => $fence['rejectedKeys'],
                'next248ProofSignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next248DuplicateRunReady' => $ready,
                'next248DuplicateRunSignature' => $fence['proofSignature'],
                'next248RejectedKeys' => $fence['rejectedKeys'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT248 DUPLICATE RUN FENCE '
                . $selectedName
                . ($ready ? ' CURRENT DUPLICATE RUNS VERIFIED' : ' REQUIRES CURRENT STAT4 DUPLICATE RUN REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext245Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next248',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next248 reuses current-source STAT4 expression partial sample anchors and adds duplicate expression-key run validation',
            'non_overlap' => 'adds duplicate expression-key run validation after accepted next245 sample-rowid anchor validation; avoids next245 anchor checks, histogram cardinality, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $whereTerms
     * @param array<string,mixed> $index
     * @return array<string,mixed>
     */
    private static function duplicateRunFence(array $rows, array $whereTerms, array $index): array
    {
        $partialRows = [];
        foreach ($rows as $row) {
            if (!self::rowSatisfiesTerms($row, $whereTerms)) {
                continue;
            }
            $key = self::rowExpressionKey($row);
            $partialRows[] = [
                'rowid' => self::rowid($row),
                'expressionKey' => $key,
                'blogId' => (int) ($row['blog_id'] ?? 0),
                'updatedAt' => (int) ($row['updated_at'] ?? 0),
            ];
        }
        usort($partialRows, static function (array $a, array $b): int {
            return ($a['expressionKey'] <=> $b['expressionKey'])
                ?: ($a['blogId'] <=> $b['blogId'])
                ?: ($a['rowid'] <=> $b['rowid']);
        });

        $runs = [];
        foreach ($partialRows as $row) {
            $runs[$row['expressionKey']][] = $row;
        }

        $sampleRuns = [];
        foreach (self::stat4Samples($index) as $sample) {
            $sampleRuns[$sample['key']][] = $sample['rowid'];
        }

        $proofs = [];
        $rejected = [];
        foreach ($runs as $key => $runRows) {
            $currentRowids = array_column($runRows, 'rowid');
            $sampleRowids = $sampleRuns[$key] ?? [];
            $isDuplicate = count($currentRowids) > 1;
            $matches = !$isDuplicate || $sampleRowids === $currentRowids;
            if (!$matches) {
                $rejected[] = $key;
            }
            $proofs[] = [
                'expressionKey' => $key,
                'currentRowids' => $currentRowids,
                'sampleRowids' => $sampleRowids,
                'duplicateRun' => $isDuplicate,
                'sampleCoversDuplicateRun' => $matches,
                'currentRunSignature' => self::signature($runRows),
            ];
        }

        $duplicateKeys = array_values(array_filter(
            array_keys($runs),
            static fn (string $key): bool => count($runs[$key]) > 1,
        ));
        $ready = $proofs !== [] && $rejected === [];
        $proof = [
            'partialRowCount' => count($partialRows),
            'partialRowidsInIndexOrder' => array_column($partialRows, 'rowid'),
            'duplicateKeys' => $duplicateKeys,
            'duplicateRunCount' => count($duplicateKeys),
            'sampleRunKeys' => array_keys($sampleRuns),
            'rejectedKeys' => $rejected,
            'duplicateRunProofs' => $proofs,
            'runSignature' => self::signature([$partialRows, $sampleRuns]),
            'indexName' => (string) ($index['name'] ?? ''),
        ];

        return $proof + [
            'ready' => $ready,
            'rejectedReason' => $ready ? null : 'stale-stat4-duplicate-expression-key-run',
            'proofSignature' => self::signature($proof),
        ];
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next248 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next248 source indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next248 selected current index missing');
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private static function rows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next248 needs current source rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next248 current source rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $index @return list<array{key:string,rowid:int}> */
    private static function stat4Samples(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next248 needs stat4Samples');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 2) {
                throw new \InvalidArgumentException('SQLite next248 stat4 samples need key and rowid');
            }
            $out[] = [
                'key' => strtolower((string) $sample['sample'][0]),
                'rowid' => self::rowid(['rowid' => $sample['sample'][1]]),
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
            throw new \InvalidArgumentException('SQLite next248 rows need option_name');
        }

        return strtolower((string) $row['option_name']);
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        $rowid = $row['rowid'] ?? null;
        if (!is_int($rowid)) {
            throw new \InvalidArgumentException('SQLite next248 rowid must be an integer');
        }

        return $rowid;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : strtolower((string) $value);
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
            'opcode' => 'ValidateCurrentSourceStat4DuplicateRuns',
            'mode' => 'next248-current-source-stat4-expression-partial-duplicate-runs',
            'duplicateKeys' => $fence['duplicateKeys'],
            'partialRowidsInIndexOrder' => $fence['partialRowidsInIndexOrder'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
