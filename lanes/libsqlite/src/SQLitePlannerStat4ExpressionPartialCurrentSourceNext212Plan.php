<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext212Plan
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
        $baseTerms = self::baseWhereTerms($whereTerms);
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext209Plan::materialize(
            $preparedSource,
            $currentSource,
            $baseTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $arms = self::likeGroupedArms($currentIndex);
        $fence = self::likeGroupedFence($arms, $whereTerms, self::matchedRows($base));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next209-ready'
            && $fence['currentGroupedLikePredicateImplied'] === true
            && $fence['allRowsSatisfyCurrentGroupedLikePredicate'] === true
            && $fence['unsupportedCurrentGroupedLikeArms'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next212-ready' : 'requires-current-source-grouped-like-reprepare',
            'groupedLikePredicateFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next212Ready' => $ready,
                'next212CurrentGroupedLikeSignature' => $fence['currentGroupedLikeSignature'],
                'next212MatchedGroupedLikeArm' => $fence['matchedGroupedLikeArm'],
                'next212MatchedGroupedLikeArms' => $fence['matchedGroupedLikeArms'],
                'next212RowsRejectedByCurrentGroupedLikePredicate' => $fence['rowidsRejectedByCurrentGroupedLikePredicate'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next212CurrentGroupedLikeSignature' => $fence['currentGroupedLikeSignature'],
                'next212GroupedLikeProofSignature' => $fence['proofSignature'],
                'next212BaseWhereTermCount' => count($baseTerms),
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT212 GROUPED LIKE PARTIAL ARM FENCE '
                . $selectedName
                . ($ready ? ' CURRENT GROUPED LIKE ARM PROVED' : ' REQUIRES CURRENT SOURCE GROUPED LIKE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext209Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next212',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next212 reuses current-source STAT4 expression partial grouped-OR fences and adds a LIKE-prefix grouped-arm proof over existing row payloads',
            'non_overlap' => 'avoids accepted next209 grouped OR arm proof, next206 single-term OR proof, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only admits a current-source partial expression index when a grouped partial arm with a LIKE prefix is proven by the query and selected rows',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $whereTerms
     * @return list<array<string,mixed>>
     */
    private static function baseWhereTerms(array $whereTerms): array
    {
        return array_values(array_filter(
            $whereTerms,
            static fn (array $term): bool => strtoupper((string) ($term['operator'] ?? '')) !== 'LIKE',
        ));
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next212 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next212 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next212 selected index missing from source');
    }

    /** @param array<string,mixed> $index @return list<array{arm:int,terms:list<array<string,mixed>>,signature:string}> */
    private static function likeGroupedArms(array $index): array
    {
        $arms = $index['partialGroupedLikePredicateArms'] ?? null;
        if (!is_array($arms) || !array_is_list($arms) || $arms === []) {
            throw new \InvalidArgumentException('SQLite next212 selected index needs partialGroupedLikePredicateArms');
        }
        $out = [];
        foreach ($arms as $armNumber => $arm) {
            if (!is_array($arm) || !array_is_list($arm) || $arm === []) {
                throw new \InvalidArgumentException('SQLite next212 grouped LIKE arms must be non-empty term lists');
            }
            $terms = [];
            foreach ($arm as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite next212 grouped LIKE arm terms must be arrays');
                }
                $terms[] = self::normalizeTerm($term);
            }
            usort($terms, static fn (array $a, array $b): int => [$a['leftKey'], $a['operator'], json_encode($a)] <=> [$b['leftKey'], $b['operator'], json_encode($b)]);
            $out[] = ['arm' => $armNumber, 'terms' => $terms, 'signature' => self::signature($terms)];
        }

        return $out;
    }

    /**
     * @param list<array{arm:int,terms:list<array<string,mixed>>,signature:string}> $arms
     * @param list<array<string,mixed>> $whereTerms
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function likeGroupedFence(array $arms, array $whereTerms, array $rows): array
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
            $proofs[] = ['arm' => $arm['arm'], 'terms' => $arm['terms'], 'termProofs' => $termProofs, 'implied' => $implied];
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
                    $termResults[] = ['term' => $term, 'satisfied' => self::termMatchesPayload($term, $payload, $row)];
                }
                $armResults[] = [
                    'arm' => $arm['arm'],
                    'termsSatisfied' => $termResults,
                    'satisfied' => !in_array(false, array_column($termResults, 'satisfied'), true),
                ];
            }
            $satisfied = in_array(true, array_column($armResults, 'satisfied'), true);
            if (!$satisfied) {
                $rejected[] = $rowid;
            }
            $rowProofs[] = [
                'rowid' => $rowid,
                'armResults' => $armResults,
                'satisfiesCurrentGroupedLikePredicate' => $satisfied,
            ];
        }

        return [
            'currentGroupedLikePredicateArms' => $arms,
            'currentGroupedLikeSignature' => self::signature($arms),
            'currentGroupedLikePredicateProofs' => $proofs,
            'currentGroupedLikePredicateImplied' => $matchedArms !== [],
            'matchedGroupedLikeArm' => $matchedArms[0] ?? null,
            'matchedGroupedLikeArms' => $matchedArms,
            'unsupportedCurrentGroupedLikeArms' => $matchedArms === [] ? array_column($arms, 'arm') : [],
            'rowProofs' => $rowProofs,
            'rowidsRejectedByCurrentGroupedLikePredicate' => $rejected,
            'allRowsSatisfyCurrentGroupedLikePredicate' => $rejected === [],
            'proofSignature' => self::signature([$proofs, $rowProofs]),
        ];
    }

    /** @param array<string,mixed> $term @return array<string,mixed> */
    private static function normalizeTerm(array $term): array
    {
        $left = $term['left'] ?? null;
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next212 term left side must be an array');
        }
        $operator = strtoupper(trim((string) ($term['operator'] ?? '')));
        if ($operator === '') {
            throw new \InvalidArgumentException('SQLite next212 term operator must be non-empty');
        }
        $normalized = ['leftKey' => self::leftKey($left), 'operator' => $operator];
        foreach (['right', 'lower', 'upper', 'escape'] as $key) {
            if (array_key_exists($key, $term)) {
                $normalized[$key] = self::literal($term[$key]);
            }
        }
        if ($operator === 'LIKE') {
            $normalized['prefix'] = self::likePrefix((string) ($normalized['right'] ?? ''), (string) ($normalized['escape'] ?? '\\'));
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

        throw new \InvalidArgumentException('SQLite next212 term left side needs column or expression');
    }

    private static function literal(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('literal', $value) ? $value['literal'] : $value;
    }

    private static function likePrefix(string $pattern, string $escape): string
    {
        $prefix = '';
        $escaped = false;
        $escape = $escape === '' ? '\\' : $escape[0];
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if (!$escaped && $char === $escape) {
                $escaped = true;
                continue;
            }
            if (!$escaped && ($char === '%' || $char === '_')) {
                break;
            }
            $prefix .= $char;
            $escaped = false;
        }

        return strtolower($prefix);
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
        if ($operator === 'LIKE' && $whereOperator === 'LIKE') {
            $termPrefix = (string) ($term['prefix'] ?? '');
            $wherePrefix = (string) ($where['prefix'] ?? '');
            return $termPrefix !== '' && str_starts_with($wherePrefix, $termPrefix);
        }
        if ($operator === '=' && $whereOperator === '=') {
            return self::compareValues($where['right'] ?? null, $term['right'] ?? null) === 0;
        }
        if ($operator === 'IS NOT NULL') {
            return in_array($whereOperator, ['IS NOT NULL', '=', '>', '>=', '<', '<=', 'BETWEEN', 'LIKE'], true);
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
            'LIKE' => self::likeMatches((string) $value, (string) ($term['right'] ?? ''), (string) ($term['escape'] ?? '\\')),
            '=' => self::compareValues($value, $term['right'] ?? null) === 0,
            'IS NOT NULL' => $value !== null,
            default => false,
        };
    }

    private static function likeMatches(string $value, string $pattern, string $escape): bool
    {
        $escape = $escape === '' ? '\\' : $escape[0];
        $quoted = '';
        $escaped = false;
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if (!$escaped && $char === $escape) {
                $escaped = true;
                continue;
            }
            if (!$escaped && $char === '%') {
                $quoted .= '.*';
            } elseif (!$escaped && $char === '_') {
                $quoted .= '.';
            } else {
                $quoted .= preg_quote($char, '/');
            }
            $escaped = false;
        }

        return preg_match('/^' . $quoted . '$/i', $value) === 1;
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
            throw new \InvalidArgumentException('SQLite next212 matched rows need payload arrays');
        }

        return $payload;
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next212 matched rowid must be an integer');
        }

        return (int) $row['rowid'];
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next212 needs matched row list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next212 matched rows must be arrays');
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
            'opcode' => 'RecheckCurrentGroupedLikeArm',
            'mode' => 'next212-current-source-stat4-expression-partial-grouped-like-arm',
            'matchedGroupedLikeArm' => $fence['matchedGroupedLikeArm'],
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
