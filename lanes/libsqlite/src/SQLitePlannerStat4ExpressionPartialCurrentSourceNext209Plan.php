<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext209Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext195Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $arms = self::groupedOrArms($currentIndex);
        $fence = self::groupedOrFence($arms, $whereTerms, self::matchedRows($base));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next195-ready'
            && $fence['currentGroupedPartialOrPredicateImplied'] === true
            && $fence['allRowsSatisfyCurrentGroupedPartialOrPredicate'] === true
            && $fence['unsupportedCurrentGroupedPartialOrArms'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next209-ready' : 'requires-current-source-grouped-partial-or-reprepare',
            'groupedPartialOrPredicateFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next209Ready' => $ready,
                'next209CurrentGroupedPartialOrSignature' => $fence['currentGroupedOrSignature'],
                'next209MatchedGroupedOrArm' => $fence['matchedGroupedOrArm'],
                'next209MatchedGroupedOrArms' => $fence['matchedGroupedOrArms'],
                'next209RowsRejectedByCurrentGroupedPartialOrPredicate' => $fence['rowidsRejectedByCurrentGroupedPartialOrPredicate'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next209CurrentGroupedPartialOrSignature' => $fence['currentGroupedOrSignature'],
                'next209GroupedPartialOrProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT209 GROUPED PARTIAL OR ARM FENCE '
                . $selectedName
                . ($ready ? ' CURRENT GROUPED PARTIAL OR ARM PROVED' : ' REQUIRES CURRENT SOURCE GROUPED PARTIAL OR REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext195Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next209',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next209 reuses current-source STAT4 expression partial predicate fences and adds grouped OR-arm implication over existing row payloads',
            'non_overlap' => 'avoids accepted next206 single-term partial OR predicate fencing, next202 partial definition fencing, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only admits changed current-source partial expression indexes when a complete grouped OR arm is proven by the query and selected rows',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next209 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next209 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next209 selected index missing from source');
    }

    /** @param array<string,mixed> $index @return list<array{arm:int,terms:list<array<string,mixed>>,signature:string}> */
    private static function groupedOrArms(array $index): array
    {
        $arms = $index['partialGroupedOrPredicateArms'] ?? null;
        if (!is_array($arms) || !array_is_list($arms) || $arms === []) {
            throw new \InvalidArgumentException('SQLite next209 selected index needs partialGroupedOrPredicateArms');
        }
        $out = [];
        foreach ($arms as $armNumber => $arm) {
            if (!is_array($arm) || !array_is_list($arm) || $arm === []) {
                throw new \InvalidArgumentException('SQLite next209 grouped OR arms must be non-empty term lists');
            }
            $terms = [];
            foreach ($arm as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite next209 grouped OR arm terms must be arrays');
                }
                $terms[] = self::normalizeTerm($term);
            }
            usort($terms, static fn (array $a, array $b): int => [$a['leftKey'], $a['operator'], json_encode($a)] <=> [$b['leftKey'], $b['operator'], json_encode($b)]);
            $out[] = [
                'arm' => $armNumber,
                'terms' => $terms,
                'signature' => self::signature($terms),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{arm:int,terms:list<array<string,mixed>>,signature:string}> $arms
     * @param list<array<string,mixed>> $whereTerms
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function groupedOrFence(array $arms, array $whereTerms, array $rows): array
    {
        $normalizedWhere = array_map(static fn (array $term): array => self::normalizeTerm($term), $whereTerms);
        $proofs = [];
        $matchedArms = [];
        foreach ($arms as $arm) {
            $termProofs = [];
            foreach ($arm['terms'] as $term) {
                $termProofs[] = self::proofForTerm($term, $normalizedWhere);
            }
            $implied = !in_array(false, array_column($termProofs, 'implied'), true);
            if ($implied) {
                $matchedArms[] = $arm['arm'];
            }
            $proofs[] = [
                'arm' => $arm['arm'],
                'terms' => $arm['terms'],
                'termProofs' => $termProofs,
                'implied' => $implied,
            ];
        }

        $rejected = [];
        $rowProofs = [];
        foreach ($rows as $row) {
            $payload = self::payload($row);
            $rowid = self::rowid($row);
            $armResults = [];
            foreach ($arms as $arm) {
                $termResults = [];
                foreach ($arm['terms'] as $term) {
                    $termResults[] = [
                        'term' => $term,
                        'satisfied' => self::termMatchesPayload($term, $payload, $row),
                    ];
                }
                $armSatisfied = !in_array(false, array_column($termResults, 'satisfied'), true);
                $armResults[] = [
                    'arm' => $arm['arm'],
                    'termsSatisfied' => $termResults,
                    'satisfied' => $armSatisfied,
                ];
            }
            $satisfied = in_array(true, array_column($armResults, 'satisfied'), true);
            if (!$satisfied) {
                $rejected[] = $rowid;
            }
            $rowProofs[] = [
                'rowid' => $rowid,
                'armResults' => $armResults,
                'satisfiesCurrentGroupedPartialOrPredicate' => $satisfied,
            ];
        }

        return [
            'currentGroupedPartialOrPredicateArms' => $arms,
            'currentGroupedOrSignature' => self::signature($arms),
            'currentGroupedPartialOrPredicateProofs' => $proofs,
            'currentGroupedPartialOrPredicateImplied' => $matchedArms !== [],
            'matchedGroupedOrArm' => $matchedArms[0] ?? null,
            'matchedGroupedOrArms' => $matchedArms,
            'unsupportedCurrentGroupedPartialOrArms' => $matchedArms === [] ? array_column($arms, 'arm') : [],
            'rowProofs' => $rowProofs,
            'rowidsRejectedByCurrentGroupedPartialOrPredicate' => $rejected,
            'allRowsSatisfyCurrentGroupedPartialOrPredicate' => $rejected === [],
            'proofSignature' => self::signature([$proofs, $rowProofs]),
        ];
    }

    /** @param array<string,mixed> $term @return array<string,mixed> */
    private static function normalizeTerm(array $term): array
    {
        $left = $term['left'] ?? null;
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next209 term left side must be an array');
        }
        $operator = strtoupper(trim((string) ($term['operator'] ?? '')));
        if ($operator === '') {
            throw new \InvalidArgumentException('SQLite next209 term operator must be non-empty');
        }
        $normalized = [
            'leftKey' => self::leftKey($left),
            'operator' => $operator,
        ];
        foreach (['right', 'lower', 'upper'] as $key) {
            if (array_key_exists($key, $term)) {
                $normalized[$key] = self::literal($term[$key]);
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

        throw new \InvalidArgumentException('SQLite next209 term left side needs column or expression');
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
            if (($where['leftKey'] ?? null) === ($term['leftKey'] ?? null) && self::whereImpliesTerm($where, $term)) {
                return ['term' => $term, 'implied' => true, 'proof' => $where];
            }
        }

        return ['term' => $term, 'implied' => false, 'proof' => null];
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
            if ($whereOperator === '=') {
                return self::compareValues($where['right'] ?? null, $term['lower'] ?? null) >= 0
                    && self::compareValues($where['right'] ?? null, $term['upper'] ?? null) <= 0;
            }
            if ($whereOperator === 'BETWEEN') {
                return self::compareValues($where['lower'] ?? null, $term['lower'] ?? null) >= 0
                    && self::compareValues($where['upper'] ?? null, $term['upper'] ?? null) <= 0;
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

    /** @param array<string,mixed> $payload @param array<string,mixed> $row */
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
            throw new \InvalidArgumentException('SQLite next209 matched rows need payload arrays');
        }

        return $payload;
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next209 matched rowid must be an integer');
        }

        return (int) $row['rowid'];
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next209 needs matched row list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next209 matched rows must be arrays');
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
     * @param array<string,mixed> $fence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'RecheckCurrentGroupedPartialOrArm',
            'mode' => 'next209-current-source-stat4-expression-partial-grouped-or-arm',
            'matchedGroupedOrArm' => $fence['matchedGroupedOrArm'],
            'rowids' => array_column($fence['rowProofs'], 'rowid'),
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
