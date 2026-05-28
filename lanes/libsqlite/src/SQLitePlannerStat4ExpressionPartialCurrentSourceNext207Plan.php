<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext207Plan
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
        $sampleFence = self::sampleFence(
            self::partialTerms($currentIndex),
            self::stat4Samples($currentIndex),
            self::rowsByRowid($currentSource),
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next206-ready'
            && $sampleFence['allSamplesSatisfyCurrentPartialPredicate'] === true
            && $sampleFence['missingSampleRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next207-ready' : 'requires-current-source-stat4-sample-reprepare',
            'stat4SamplePredicateFence' => $sampleFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next207Ready' => $ready,
                'next207Stat4SampleSignature' => $sampleFence['sampleSignature'],
                'next207RejectedSampleRowids' => $sampleFence['sampleRowidsRejectedByCurrentPartialPredicate'],
                'next207MissingSampleRowids' => $sampleFence['missingSampleRowids'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next207Stat4SampleSignature' => $sampleFence['sampleSignature'],
                'next207Stat4SampleProofSignature' => $sampleFence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $sampleFence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT207 STAT4 SAMPLE PARTIAL PREDICATE FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 SAMPLES MATCH PARTIAL WHERE' : ' REQUIRES CURRENT SOURCE STAT4 SAMPLE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext206Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next207',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next207 reuses current-source STAT4 expression partial predicate fences and adds per-sample partial WHERE validation against the current row image',
            'non_overlap' => 'avoids accepted next206 OR-arm implication, next202 predicate definition fingerprints, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only blocks reuse when current sqlite_stat4 samples no longer satisfy the partial expression-index WHERE predicate',
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
            throw new \InvalidArgumentException('SQLite next207 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next207 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next207 selected index missing from current source');
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array<string,mixed>>
     */
    private static function partialTerms(array $index): array
    {
        $terms = $index['partialPredicateTerms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite next207 selected index needs partialPredicateTerms');
        }
        $out = [];
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next207 partial predicate terms must be arrays');
            }
            $out[] = self::normalizeTerm($term);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array<string,mixed>>
     */
    private static function stat4Samples(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next207 selected index needs stat4Samples');
        }
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next207 stat4 samples must be arrays');
            }
            if (!isset($sample['sample']) || !is_array($sample['sample']) || count($sample['sample']) < 2) {
                throw new \InvalidArgumentException('SQLite next207 stat4 samples need expression key and rowid');
            }
        }

        return $samples;
    }

    /**
     * @param array<string,mixed> $source
     * @return array<int,array<string,mixed>>
     */
    private static function rowsByRowid(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next207 needs current source rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next207 current source rows must be arrays');
            }
            $out[self::rowid($row, 'current source rowid')] = $row;
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @param list<array<string,mixed>> $samples
     * @param array<int,array<string,mixed>> $rowsByRowid
     * @return array<string,mixed>
     */
    private static function sampleFence(array $terms, array $samples, array $rowsByRowid): array
    {
        $proofs = [];
        $rejected = [];
        $missing = [];
        foreach ($samples as $ordinal => $sample) {
            $raw = $sample['sample'];
            $rowid = self::rowid(['rowid' => $raw[1]], 'stat4 sample rowid');
            $row = $rowsByRowid[$rowid] ?? null;
            if ($row === null) {
                $missing[] = $rowid;
            }
            $payload = $row ?? [
                'rowid' => $rowid,
                'option_name' => $raw[0],
                '__expr_lower_option_name' => $raw[0],
            ];
            $termResults = [];
            foreach ($terms as $term) {
                $termResults[] = [
                    'term' => $term,
                    'satisfied' => $row !== null && self::termMatchesRow($term, $payload),
                ];
            }
            $satisfied = !in_array(false, array_column($termResults, 'satisfied'), true);
            if (!$satisfied && $row !== null) {
                $rejected[] = $rowid;
            }
            $proofs[] = [
                'ordinal' => $ordinal,
                'rowid' => $rowid,
                'expressionKey' => (string) $raw[0],
                'rowFound' => $row !== null,
                'termResults' => $termResults,
                'satisfiesCurrentPartialPredicate' => $satisfied,
            ];
        }

        return [
            'currentPartialPredicateTerms' => $terms,
            'sampleCount' => count($samples),
            'sampleRowids' => array_column($proofs, 'rowid'),
            'sampleExpressionKeys' => array_column($proofs, 'expressionKey'),
            'sampleProofs' => $proofs,
            'missingSampleRowids' => array_values(array_unique($missing)),
            'sampleRowidsRejectedByCurrentPartialPredicate' => array_values(array_unique($rejected)),
            'allSamplesSatisfyCurrentPartialPredicate' => $rejected === [] && $missing === [],
            'sampleSignature' => self::signature($samples),
            'proofSignature' => self::signature([$terms, $proofs, $rejected, $missing]),
        ];
    }

    /** @param array<string,mixed> $term */
    private static function normalizeTerm(array $term): array
    {
        $left = $term['left'] ?? null;
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next207 term left side must be an array');
        }
        $operator = strtoupper(trim((string) ($term['operator'] ?? '')));
        if ($operator === '') {
            throw new \InvalidArgumentException('SQLite next207 term operator must be non-empty');
        }
        $normalized = [
            'leftKey' => self::leftKey($left),
            'operator' => $operator,
        ];
        foreach (['right', 'lower', 'upper'] as $key) {
            if (array_key_exists($key, $term)) {
                $normalized[$key] = $term[$key];
            }
        }

        return $normalized;
    }

    /** @param array<string,mixed> $left */
    private static function leftKey(array $left): string
    {
        if (isset($left['column'])) {
            return 'column:' . strtolower((string) $left['column']);
        }
        if (isset($left['expression'])) {
            return 'expression:' . strtolower(preg_replace('/\s+/', '', (string) $left['expression']) ?? '');
        }

        throw new \InvalidArgumentException('SQLite next207 term left side needs column or expression');
    }

    /**
     * @param array<string,mixed> $term
     * @param array<string,mixed> $row
     */
    private static function termMatchesRow(array $term, array $row): bool
    {
        $value = self::valueForLeftKey((string) ($term['leftKey'] ?? ''), $row);
        $operator = (string) ($term['operator'] ?? '');

        return match ($operator) {
            'IS NOT NULL' => $value !== null,
            '=' => self::compareValues($value, $term['right'] ?? null) === 0,
            '>=' => self::compareValues($value, $term['right'] ?? null) >= 0,
            '>' => self::compareValues($value, $term['right'] ?? null) > 0,
            '<=' => self::compareValues($value, $term['right'] ?? null) <= 0,
            '<' => self::compareValues($value, $term['right'] ?? null) < 0,
            default => false,
        };
    }

    /** @param array<string,mixed> $row */
    private static function valueForLeftKey(string $leftKey, array $row): mixed
    {
        if ($leftKey === 'expression:lower(option_name)') {
            $value = $row['option_name'] ?? $row['__expr_lower_option_name'] ?? null;
            return $value === null ? null : strtolower((string) $value);
        }
        if (str_starts_with($leftKey, 'column:')) {
            return $row[substr($leftKey, 7)] ?? null;
        }

        return $row[$leftKey] ?? null;
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row, string $label): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next207 ' . $label . ' must be an integer');
        }

        return (int) $row['rowid'];
    }

    private static function compareValues(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param array<string,mixed> $sampleFence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $sampleFence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'RecheckCurrentStat4SamplesAgainstPartialPredicate',
            'mode' => 'next207-current-source-stat4-expression-partial-samples',
            'sampleRowids' => $sampleFence['sampleRowids'],
            'signature' => $sampleFence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
