<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext195Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext191Plan::materialize(
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
        $preparedTerms = self::partialTerms($preparedIndex, 'prepared');
        $currentTerms = self::partialTerms($currentIndex, 'current');
        $predicateFence = self::predicateFence($preparedTerms, $currentTerms, $whereTerms, self::matchedRows($base));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next191-ready'
            && $predicateFence['currentPartialPredicateImplied'] === true
            && $predicateFence['allRowsSatisfyCurrentPartialPredicate'] === true
            && $predicateFence['unsupportedCurrentPartialTerms'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next195-ready' : 'requires-current-source-partial-predicate-reprepare',
            'partialPredicateFence' => $predicateFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next195Ready' => $ready,
                'next195PreparedPartialPredicateSignature' => $predicateFence['preparedSignature'],
                'next195CurrentPartialPredicateSignature' => $predicateFence['currentSignature'],
                'next195PartialPredicateChanged' => $predicateFence['partialPredicateChanged'],
                'next195RowsRejectedByCurrentPartialPredicate' => $predicateFence['rowidsRejectedByCurrentPartialPredicate'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next195CurrentPartialPredicateSignature' => $predicateFence['currentSignature'],
                'next195PartialPredicateProofSignature' => $predicateFence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $predicateFence
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT195 PARTIAL PREDICATE FENCE '
                . $selectedName
                . ($ready ? ' CURRENT PARTIAL WHERE PROVED' : ' REQUIRES CURRENT SOURCE PARTIAL PREDICATE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext191Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next195',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next195 reuses current-source STAT4 expression partial payload fences and adds a partial-index WHERE predicate proof fence',
            'non_overlap' => 'avoids accepted next191 payload expression-key rechecks, next188 peer rowid fencing, next185 sample provenance, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only admits a current-source partial expression index after its changed partial WHERE predicate is proven by the query and selected rows',
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
            throw new \InvalidArgumentException('SQLite next195 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next195 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next195 selected index missing from source');
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array<string,mixed>>
     */
    private static function partialTerms(array $index, string $source): array
    {
        $terms = $index['partialPredicateTerms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms)) {
            throw new \InvalidArgumentException('SQLite next195 ' . $source . ' index needs partialPredicateTerms');
        }
        $out = [];
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next195 partial predicate terms must be arrays');
            }
            $out[] = self::normalizeTerm($term);
        }

        usort($out, static fn (array $a, array $b): int => [$a['leftKey'], $a['operator'], json_encode($a['right'] ?? null)] <=> [$b['leftKey'], $b['operator'], json_encode($b['right'] ?? null)]);

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $preparedTerms
     * @param list<array<string,mixed>> $currentTerms
     * @param list<array<string,mixed>> $whereTerms
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function predicateFence(array $preparedTerms, array $currentTerms, array $whereTerms, array $rows): array
    {
        $normalizedWhere = array_map(static fn (array $term): array => self::normalizeTerm($term), $whereTerms);
        $proofs = [];
        $unsupported = [];
        foreach ($currentTerms as $term) {
            $proof = self::proofForTerm($term, $normalizedWhere);
            if (($proof['implied'] ?? false) !== true) {
                $unsupported[] = $term;
            }
            $proofs[] = $proof;
        }

        $rejected = [];
        $rowProofs = [];
        foreach ($rows as $row) {
            $payload = self::payload($row);
            $rowid = self::rowid($row);
            $termResults = [];
            foreach ($currentTerms as $term) {
                $termResults[] = [
                    'term' => $term,
                    'satisfied' => self::termMatchesPayload($term, $payload, $row),
                ];
            }
            $satisfied = !in_array(false, array_column($termResults, 'satisfied'), true);
            if (!$satisfied) {
                $rejected[] = $rowid;
            }
            $rowProofs[] = [
                'rowid' => $rowid,
                'termResults' => $termResults,
                'satisfiesCurrentPartialPredicate' => $satisfied,
            ];
        }

        return [
            'preparedPartialPredicateTerms' => $preparedTerms,
            'currentPartialPredicateTerms' => $currentTerms,
            'preparedSignature' => self::signature($preparedTerms),
            'currentSignature' => self::signature($currentTerms),
            'partialPredicateChanged' => self::signature($preparedTerms) !== self::signature($currentTerms),
            'currentPartialPredicateProofs' => $proofs,
            'currentPartialPredicateImplied' => $unsupported === [],
            'unsupportedCurrentPartialTerms' => $unsupported,
            'rowProofs' => $rowProofs,
            'rowidsRejectedByCurrentPartialPredicate' => $rejected,
            'allRowsSatisfyCurrentPartialPredicate' => $rejected === [],
            'proofSignature' => self::signature([$proofs, $rowProofs]),
        ];
    }

    /** @param array<string,mixed> $term */
    private static function normalizeTerm(array $term): array
    {
        $left = $term['left'] ?? null;
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next195 term left side must be an array');
        }
        $operator = strtoupper(trim((string) ($term['operator'] ?? '')));
        if ($operator === '') {
            throw new \InvalidArgumentException('SQLite next195 term operator must be non-empty');
        }
        $normalized = [
            'leftKey' => self::leftKey($left),
            'operator' => $operator,
        ];
        if (array_key_exists('right', $term)) {
            $normalized['right'] = self::literal($term['right']);
        }
        if (array_key_exists('lower', $term)) {
            $normalized['lower'] = self::literal($term['lower']);
        }
        if (array_key_exists('upper', $term)) {
            $normalized['upper'] = self::literal($term['upper']);
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
            return 'expression:' . self::normalizeExpression((string) $left['expression']);
        }

        throw new \InvalidArgumentException('SQLite next195 term left side needs column or expression');
    }

    private static function normalizeExpression(string $expression): string
    {
        return strtolower(preg_replace('/\s+/', '', $expression) ?? '');
    }

    private static function literal(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('literal', $value) ? $value['literal'] : $value;
    }

    /**
     * @param array<string,mixed> $term
     * @param list<array<string,mixed>> $whereTerms
     * @return array<string,mixed>
     */
    private static function proofForTerm(array $term, array $whereTerms): array
    {
        foreach ($whereTerms as $where) {
            if (($where['leftKey'] ?? null) !== ($term['leftKey'] ?? null)) {
                continue;
            }
            if (self::whereImpliesTerm($where, $term)) {
                return [
                    'term' => $term,
                    'implied' => true,
                    'proof' => $where,
                ];
            }
        }

        return [
            'term' => $term,
            'implied' => false,
            'proof' => null,
        ];
    }

    /** @param array<string,mixed> $where @param array<string,mixed> $term */
    private static function whereImpliesTerm(array $where, array $term): bool
    {
        $operator = (string) ($term['operator'] ?? '');
        $whereOperator = (string) ($where['operator'] ?? '');
        if ($operator === 'IS NOT NULL') {
            return in_array($whereOperator, ['IS NOT NULL', '=', '>', '>=', '<', '<=', 'BETWEEN'], true);
        }
        if ($operator === '=' && $whereOperator === '=') {
            return self::compareValues($where['right'] ?? null, $term['right'] ?? null) === 0;
        }
        if (in_array($operator, ['>=', '>'], true) && in_array($whereOperator, ['>=', '>', '=', 'BETWEEN'], true)) {
            $whereLower = $whereOperator === 'BETWEEN' ? ($where['lower'] ?? null) : ($where['right'] ?? null);
            return self::compareValues($whereLower, $term['right'] ?? null) >= 0;
        }
        if (in_array($operator, ['<=', '<'], true) && in_array($whereOperator, ['<=', '<', '=', 'BETWEEN'], true)) {
            $whereUpper = $whereOperator === 'BETWEEN' ? ($where['upper'] ?? null) : ($where['right'] ?? null);
            return self::compareValues($whereUpper, $term['right'] ?? null) <= 0;
        }
        if ($operator === 'BETWEEN') {
            if ($whereOperator === 'BETWEEN') {
                return self::compareValues($where['lower'] ?? null, $term['lower'] ?? null) >= 0
                    && self::compareValues($where['upper'] ?? null, $term['upper'] ?? null) <= 0;
            }
            if ($whereOperator === '=') {
                return self::compareValues($where['right'] ?? null, $term['lower'] ?? null) >= 0
                    && self::compareValues($where['right'] ?? null, $term['upper'] ?? null) <= 0;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $term
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $row
     */
    private static function termMatchesPayload(array $term, array $payload, array $row): bool
    {
        $value = self::valueForLeftKey((string) ($term['leftKey'] ?? ''), $payload, $row);
        $operator = (string) ($term['operator'] ?? '');
        return match ($operator) {
            'IS NOT NULL' => $value !== null,
            '=' => self::compareValues($value, $term['right'] ?? null) === 0,
            '>=' => self::compareValues($value, $term['right'] ?? null) >= 0,
            '>' => self::compareValues($value, $term['right'] ?? null) > 0,
            '<=' => self::compareValues($value, $term['right'] ?? null) <= 0,
            '<' => self::compareValues($value, $term['right'] ?? null) < 0,
            'BETWEEN' => self::compareValues($value, $term['lower'] ?? null) >= 0
                && self::compareValues($value, $term['upper'] ?? null) <= 0,
            default => false,
        };
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $row
     */
    private static function valueForLeftKey(string $leftKey, array $payload, array $row): mixed
    {
        if ($leftKey === 'expression:lower(option_name)') {
            $value = $payload['option_name'] ?? null;
            return $value === null ? null : strtolower((string) $value);
        }
        if (str_starts_with($leftKey, 'column:')) {
            return $payload[substr($leftKey, 7)] ?? null;
        }

        return $row[$leftKey] ?? null;
    }

    /** @param array<string,mixed> $row */
    private static function payload(array $row): array
    {
        $payload = $row['payload'] ?? null;
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('SQLite next195 matched rows need payload arrays');
        }

        return $payload;
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next195 matched rowid must be an integer');
        }

        return (int) $row['rowid'];
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next195 needs matched row list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next195 matched rows must be arrays');
            }
        }

        return $rows;
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
     * @param array<string,mixed> $predicateFence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $predicateFence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'RecheckCurrentPartialPredicate',
            'mode' => 'next195-current-source-stat4-expression-partial-where',
            'partialPredicateChanged' => $predicateFence['partialPredicateChanged'],
            'rowids' => array_column($predicateFence['rowProofs'], 'rowid'),
            'signature' => $predicateFence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
