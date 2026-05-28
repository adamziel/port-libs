<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext228Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $fence = self::samplePartialPredicateFence(
            self::rowsByRowid($currentSource),
            self::partialPredicateTerms($currentIndex),
            self::stat4Samples($currentIndex),
            self::matchedSampleProofs($base),
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next224-ready'
            && $fence['allSampleRowsResolveToCurrentSource'] === true
            && $fence['allSampleExpressionKeysMatchCurrentRows'] === true
            && $fence['allSampleRowsSatisfyCurrentPartialPredicate'] === true
            && $fence['sampleRowidsRejectedByPartialPredicate'] === []
            && $fence['missingCurrentSampleRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next228-ready' : 'requires-current-source-stat4-partial-sample-reprepare',
            'stat4SamplePartialPredicateFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next228Ready' => $ready,
                'next228SamplePartialPredicateSignature' => $fence['samplePartialPredicateSignature'],
                'next228ProofSignature' => $fence['proofSignature'],
                'next228MissingCurrentSampleRowids' => $fence['missingCurrentSampleRowids'],
                'next228RowsRejectedByPartialPredicate' => $fence['sampleRowidsRejectedByPartialPredicate'],
                'next228RowsRejectedByExpressionKey' => $fence['sampleRowidsRejectedByExpressionKey'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next228SamplePartialPredicateSignature' => $fence['samplePartialPredicateSignature'],
                'next228ProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT228 SAMPLE PARTIAL-PREDICATE FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 SAMPLE ROWS PROVED' : ' REQUIRES CURRENT STAT4 PARTIAL SAMPLE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next228',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next228 reuses current-source STAT4 expression partial fences and adds sample-row partial-predicate validation',
            'non_overlap' => 'adds current sqlite_stat4 sample-row partial-predicate validation after accepted next224 sample-order validation; avoids grouped LIKE/OR, rowid alias, payload coverage, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next228 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next228 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next228 selected index missing from source');
    }

    /** @param array<string,mixed> $source @return array<int,array<string,mixed>> */
    private static function rowsByRowid(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next228 needs current source rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next228 current source rows must be arrays');
            }
            $out[self::rowid($row, 'current rowid')] = $row;
        }

        return $out;
    }

    /** @param array<string,mixed> $index @return list<array<string,mixed>> */
    private static function partialPredicateTerms(array $index): array
    {
        $terms = $index['partialPredicateTerms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite next228 needs partialPredicateTerms');
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next228 partial predicate terms must be arrays');
            }
        }

        return $terms;
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedSampleProofs(array $base): array
    {
        $proofs = $base['stat4SampleOrderFence']['matchedSampleProofs'] ?? null;
        if (!is_array($proofs) || !array_is_list($proofs)) {
            throw new \InvalidArgumentException('SQLite next228 needs next224 matched sample proofs');
        }
        foreach ($proofs as $proof) {
            if (!is_array($proof)) {
                throw new \InvalidArgumentException('SQLite next228 matched sample proofs must be arrays');
            }
        }

        return $proofs;
    }

    /**
     * @param array<int,array<string,mixed>> $rowsByRowid
     * @param list<array<string,mixed>> $partialTerms
     * @param list<array{expressionKey:string,rowid:int}> $samples
     * @param list<array<string,mixed>> $sampleProofs
     * @return array<string,mixed>
     */
    private static function samplePartialPredicateFence(array $rowsByRowid, array $partialTerms, array $samples, array $sampleProofs): array
    {
        $matchedKeys = array_values(array_unique(array_map(
            static fn (array $proof): string => self::expressionKey($proof),
            $sampleProofs,
        )));
        $proofs = [];
        $missing = [];
        $keyRejected = [];
        $partialRejected = [];

        foreach ($samples as $sample) {
            $sampleRowid = $sample['rowid'];
            $sampleKey = $sample['expressionKey'];
            $row = $rowsByRowid[$sampleRowid] ?? null;
            if ($row === null) {
                $missing[] = $sampleRowid;
            $proofs[] = [
                'sampleRowid' => $sampleRowid,
                'sampleExpressionKey' => $sampleKey,
                'matchedBySelectedPage' => in_array($sampleKey, $matchedKeys, true),
                'currentRowPresent' => false,
                'currentExpressionKey' => null,
                'expressionKeyMatchesCurrentRow' => false,
                    'partialPredicateTermProofs' => [],
                    'satisfiesCurrentPartialPredicate' => false,
                ];
                continue;
            }

            $currentKey = strtolower((string) ($row['option_name'] ?? ''));
            $keyMatches = $currentKey === $sampleKey;
            if (!$keyMatches) {
                $keyRejected[] = $sampleRowid;
            }
            $termProofs = self::termProofs($partialTerms, $row);
            $partialOk = !in_array(false, array_column($termProofs, 'satisfied'), true);
            if (!$partialOk) {
                $partialRejected[] = $sampleRowid;
            }
            $proofs[] = [
                'sampleRowid' => $sampleRowid,
                'sampleExpressionKey' => $sampleKey,
                'matchedBySelectedPage' => in_array($sampleKey, $matchedKeys, true),
                'currentRowPresent' => true,
                'currentExpressionKey' => $currentKey,
                'expressionKeyMatchesCurrentRow' => $keyMatches,
                'partialPredicateTermProofs' => $termProofs,
                'satisfiesCurrentPartialPredicate' => $partialOk,
            ];
        }

        return [
            'sampleRowCount' => count($proofs),
            'matchedSampleKeys' => $matchedKeys,
            'matchedSampleRowids' => array_values(array_map(
                static fn (array $proof): int => $proof['sampleRowid'],
                array_values(array_filter($proofs, static fn (array $proof): bool => $proof['matchedBySelectedPage'] === true)),
            )),
            'sampleRowProofs' => $proofs,
            'partialPredicateTermCount' => count($partialTerms),
            'allSampleRowsResolveToCurrentSource' => $missing === [],
            'allSampleExpressionKeysMatchCurrentRows' => $keyRejected === [],
            'allSampleRowsSatisfyCurrentPartialPredicate' => $partialRejected === [],
            'missingCurrentSampleRowids' => array_values(array_unique($missing)),
            'sampleRowidsRejectedByExpressionKey' => array_values(array_unique($keyRejected)),
            'sampleRowidsRejectedByPartialPredicate' => array_values(array_unique($partialRejected)),
            'samplePartialPredicateSignature' => self::signature([$partialTerms, $samples, $matchedKeys]),
            'proofSignature' => self::signature([$partialTerms, $samples, $matchedKeys, $proofs, $missing, $keyRejected, $partialRejected]),
        ];
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array{expressionKey:string,rowid:int}>
     */
    private static function stat4Samples(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next228 needs stat4Samples');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 2) {
                throw new \InvalidArgumentException('SQLite next228 stat4 samples are malformed');
            }
            $out[] = [
                'expressionKey' => strtolower((string) $sample['sample'][0]),
                'rowid' => self::rowid(['rowid' => $sample['sample'][1]], 'stat4 sample rowid'),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $partialTerms
     * @param array<string,mixed> $row
     * @return list<array<string,mixed>>
     */
    private static function termProofs(array $partialTerms, array $row): array
    {
        $proofs = [];
        foreach ($partialTerms as $position => $term) {
            $left = $term['left'] ?? null;
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $leftKey = self::leftKey($left);
            $value = self::leftValue($left, $row);
            $satisfied = self::termSatisfied($operator, $value, $term);
            $proofs[] = [
                'position' => $position,
                'leftKey' => $leftKey,
                'operator' => $operator,
                'value' => $value,
                'right' => $term['right'] ?? null,
                'lower' => $term['lower'] ?? null,
                'upper' => $term['upper'] ?? null,
                'satisfied' => $satisfied,
            ];
        }

        return $proofs;
    }

    private static function termSatisfied(string $operator, mixed $value, array $term): bool
    {
        return match ($operator) {
            '=' => self::compare($value, $term['right'] ?? null) === 0,
            '>=', '=>' => self::compare($value, $term['right'] ?? null) >= 0,
            '<=' => self::compare($value, $term['right'] ?? null) <= 0,
            '>' => self::compare($value, $term['right'] ?? null) > 0,
            '<' => self::compare($value, $term['right'] ?? null) < 0,
            'IS NOT NULL' => $value !== null,
            'LIKE' => self::likePrefix((string) $value, (string) ($term['right'] ?? '')),
            'BETWEEN' => self::compare($value, $term['lower'] ?? null) >= 0
                && self::compare($value, $term['upper'] ?? null) <= 0,
            default => throw new \InvalidArgumentException('SQLite next228 unsupported partial predicate operator ' . $operator),
        };
    }

    private static function compare(mixed $left, mixed $right): int
    {
        if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
            return ((float) $left) <=> ((float) $right);
        }

        return strcmp(strtolower((string) $left), strtolower((string) $right));
    }

    private static function likePrefix(string $value, string $pattern): bool
    {
        if (!str_ends_with($pattern, '%') || str_contains(substr($pattern, 0, -1), '%') || str_contains($pattern, '_')) {
            throw new \InvalidArgumentException('SQLite next228 only supports simple LIKE prefix partial terms');
        }

        return str_starts_with(strtolower($value), strtolower(substr($pattern, 0, -1)));
    }

    private static function leftKey(mixed $left): string
    {
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next228 partial predicate term needs left operand');
        }
        if (isset($left['column']) && is_string($left['column']) && $left['column'] !== '') {
            return 'column:' . strtolower($left['column']);
        }
        if (isset($left['expression']) && is_string($left['expression']) && $left['expression'] !== '') {
            return 'expression:' . strtolower($left['expression']);
        }

        throw new \InvalidArgumentException('SQLite next228 partial predicate left operand is unsupported');
    }

    /** @param array<string,mixed>|mixed $left @param array<string,mixed> $row */
    private static function leftValue(mixed $left, array $row): mixed
    {
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next228 partial predicate term needs left operand');
        }
        if (isset($left['column']) && is_string($left['column'])) {
            return $row[$left['column']] ?? null;
        }
        $expression = strtolower((string) ($left['expression'] ?? ''));
        if ($expression === 'lower(option_name)') {
            return strtolower((string) ($row['option_name'] ?? ''));
        }

        throw new \InvalidArgumentException('SQLite next228 partial predicate expression is unsupported');
    }

    /** @param array<string,mixed> $row */
    private static function expressionKey(array $row): string
    {
        if (array_key_exists('expressionKey', $row)) {
            return strtolower((string) $row['expressionKey']);
        }

        throw new \InvalidArgumentException('SQLite next228 sample proof needs expressionKey');
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row, string $label): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next228 ' . $label . ' must be an integer');
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
            'opcode' => 'RecheckCurrentStat4PartialSamples',
            'mode' => 'next228-current-source-stat4-expression-partial-sample-predicate',
            'sampleRowids' => array_column($fence['sampleRowProofs'], 'sampleRowid'),
            'matchedSampleRowids' => $fence['matchedSampleRowids'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
