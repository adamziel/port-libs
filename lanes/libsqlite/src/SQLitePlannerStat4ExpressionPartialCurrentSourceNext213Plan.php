<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext213Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext212Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $fence = self::likeCaseFence(
            self::likeTerms($currentIndex),
            $whereTerms,
            self::matchedRows($base),
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next212-ready'
            && $fence['currentLikeCaseContractImplied'] === true
            && $fence['allRowsSatisfyCurrentLikeCaseContract'] === true
            && $fence['caseContractMismatches'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next213-ready' : 'requires-current-source-like-case-reprepare',
            'likeCaseContractFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next213Ready' => $ready,
                'next213LikeCaseSignature' => $fence['currentLikeCaseSignature'],
                'next213LikeCaseMode' => $fence['matchedLikeCaseMode'],
                'next213RowsRejectedByLikeCaseContract' => $fence['rowidsRejectedByLikeCaseContract'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next213LikeCaseSignature' => $fence['currentLikeCaseSignature'],
                'next213LikeCaseProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT213 LIKE CASE CONTRACT FENCE '
                . $selectedName
                . ($ready ? ' CURRENT LIKE CASE CONTRACT PROVED' : ' REQUIRES CURRENT SOURCE LIKE CASE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext212Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next213',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next213 reuses current-source STAT4 expression partial grouped-LIKE proof rows and adds a lane-local LIKE case/collation contract fence',
            'non_overlap' => 'extends accepted next212 grouped LIKE arm proof with case-sensitive LIKE and NOCASE/BINARY collation compatibility checks; avoids accepted next209 grouped OR, next206 OR proof, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and Unicode GLOB clusters',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next213 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next213 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next213 selected index missing from source');
    }

    /** @param array<string,mixed> $index @return list<array<string,mixed>> */
    private static function likeTerms(array $index): array
    {
        $arms = $index['partialGroupedLikePredicateArms'] ?? null;
        if (!is_array($arms) || !array_is_list($arms) || $arms === []) {
            throw new \InvalidArgumentException('SQLite next213 selected index needs partialGroupedLikePredicateArms');
        }
        $terms = [];
        foreach ($arms as $armNumber => $arm) {
            if (!is_array($arm) || !array_is_list($arm)) {
                throw new \InvalidArgumentException('SQLite next213 grouped LIKE arms must be term lists');
            }
            foreach ($arm as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite next213 grouped LIKE arm terms must be arrays');
                }
                if (strtoupper((string) ($term['operator'] ?? '')) !== 'LIKE') {
                    continue;
                }
                $terms[] = self::normalizeLikeTerm($term, (int) $armNumber);
            }
        }
        if ($terms === []) {
            throw new \InvalidArgumentException('SQLite next213 grouped LIKE arms need a LIKE term');
        }

        return $terms;
    }

    /** @param array<string,mixed> $term @return array<string,mixed> */
    private static function normalizeLikeTerm(array $term, int $arm): array
    {
        $left = $term['left'] ?? null;
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next213 LIKE term needs left side');
        }
        $right = self::literal($term['right'] ?? null);
        if (!is_string($right)) {
            throw new \InvalidArgumentException('SQLite next213 LIKE term needs string pattern');
        }
        $caseSensitive = (bool) ($term['caseSensitive'] ?? false);
        $collation = strtoupper((string) ($term['collation'] ?? ($caseSensitive ? 'BINARY' : 'NOCASE')));

        return [
            'arm' => $arm,
            'leftKey' => self::leftKey($left),
            'operator' => 'LIKE',
            'right' => $right,
            'escape' => (string) self::literal($term['escape'] ?? '\\'),
            'caseSensitive' => $caseSensitive,
            'collation' => $collation,
            'prefix' => self::likePrefix($right, (string) self::literal($term['escape'] ?? '\\')),
        ];
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

        throw new \InvalidArgumentException('SQLite next213 term left side needs column or expression');
    }

    /**
     * @param list<array<string,mixed>> $partialLikeTerms
     * @param list<array<string,mixed>> $whereTerms
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function likeCaseFence(array $partialLikeTerms, array $whereTerms, array $rows): array
    {
        $whereLikes = [];
        foreach ($whereTerms as $where) {
            if (strtoupper((string) ($where['operator'] ?? '')) === 'LIKE') {
                $whereLikes[] = self::normalizeLikeTerm($where, -1);
            }
        }

        $proofs = [];
        $mismatches = [];
        $matchedMode = null;
        $impliedCount = 0;
        foreach ($partialLikeTerms as $term) {
            $proof = self::proofForLikeCaseTerm($term, $whereLikes);
            if ($proof['implied']) {
                $impliedCount++;
                $matchedMode = $term['caseSensitive'] ? 'BINARY' : 'NOCASE';
            } elseif (($proof['reason'] ?? null) !== 'like-prefix-not-implied' && ($proof['reason'] ?? null) !== 'missing-like-term') {
                $mismatches[] = ['arm' => $term['arm'], 'leftKey' => $term['leftKey'], 'reason' => $proof['reason']];
            }
            $proofs[] = $proof;
        }

        $rejected = [];
        $rowProofs = [];
        foreach ($rows as $row) {
            $payload = self::payload($row);
            $rowid = self::rowid($row);
            $termResults = [];
            foreach ($partialLikeTerms as $term) {
                $termResults[] = [
                    'term' => $term,
                    'satisfied' => self::likeMatches(
                        (string) self::valueForLeftKey((string) $term['leftKey'], $payload, $row),
                        (string) $term['right'],
                        (string) $term['escape'],
                        (bool) $term['caseSensitive'],
                    ),
                ];
            }
            $satisfied = in_array(true, array_column($termResults, 'satisfied'), true);
            if (!$satisfied) {
                $rejected[] = $rowid;
            }
            $rowProofs[] = [
                'rowid' => $rowid,
                'termResults' => $termResults,
                'satisfiesCurrentLikeCaseContract' => $satisfied,
            ];
        }

        return [
            'currentLikeCaseTerms' => $partialLikeTerms,
            'whereLikeTerms' => $whereLikes,
            'currentLikeCaseProofs' => $proofs,
            'currentLikeCaseContractImplied' => $mismatches === [] && $impliedCount > 0,
            'caseContractMismatches' => $mismatches,
            'matchedLikeCaseMode' => $matchedMode,
            'rowProofs' => $rowProofs,
            'rowidsRejectedByLikeCaseContract' => $rejected,
            'allRowsSatisfyCurrentLikeCaseContract' => $rejected === [],
            'currentLikeCaseSignature' => self::signature([$partialLikeTerms, $whereLikes]),
            'proofSignature' => self::signature([$proofs, $rowProofs, $mismatches]),
        ];
    }

    /** @param array<string,mixed> $term @param list<array<string,mixed>> $whereLikes @return array<string,mixed> */
    private static function proofForLikeCaseTerm(array $term, array $whereLikes): array
    {
        foreach ($whereLikes as $where) {
            if (($where['leftKey'] ?? null) !== ($term['leftKey'] ?? null)) {
                continue;
            }
            $termPrefix = (string) ($term['prefix'] ?? '');
            $wherePrefix = (string) ($where['prefix'] ?? '');
            if ($termPrefix === '' || !str_starts_with($wherePrefix, $termPrefix)) {
                return ['term' => $term, 'implied' => false, 'proof' => $where, 'reason' => 'like-prefix-not-implied'];
            }
            if (($where['caseSensitive'] ?? false) !== ($term['caseSensitive'] ?? false)) {
                return ['term' => $term, 'implied' => false, 'proof' => $where, 'reason' => 'case-sensitive-like-mode-changed'];
            }
            if (($where['collation'] ?? null) !== ($term['collation'] ?? null)) {
                return ['term' => $term, 'implied' => false, 'proof' => $where, 'reason' => 'like-collation-changed'];
            }
            return ['term' => $term, 'implied' => true, 'proof' => $where, 'reason' => 'like-case-contract-compatible'];
        }

        return ['term' => $term, 'implied' => false, 'proof' => null, 'reason' => 'missing-like-term'];
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

    private static function likeMatches(string $value, string $pattern, string $escape, bool $caseSensitive): bool
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

        return preg_match('/^' . $quoted . '$/' . ($caseSensitive ? '' : 'i'), $value) === 1;
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

    private static function literal(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('literal', $value) ? $value['literal'] : $value;
    }

    /** @param array<string,mixed> $row */
    private static function payload(array $row): array
    {
        $payload = $row['payload'] ?? null;
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('SQLite next213 matched rows need payload arrays');
        }

        return $payload;
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next213 matched rowid must be an integer');
        }

        return (int) $row['rowid'];
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next213 needs matched row list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next213 matched rows must be arrays');
            }
        }

        return $rows;
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
            'opcode' => 'RecheckCurrentLikeCaseContract',
            'mode' => 'next213-current-source-stat4-expression-partial-like-case-contract',
            'likeCaseMode' => $fence['matchedLikeCaseMode'],
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
