<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext240Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @param list<string> $trailingColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $whereTerms,
        array $neededColumns,
        array $trailingColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext237Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $trailingColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $preparedIndex = self::indexByName($preparedSource, $selectedName);
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $fence = self::partialPredicateFence($preparedIndex, $currentIndex, $whereTerms);
        $trailingFence = is_array($base['stat4TrailingPayloadFence'] ?? null) ? $base['stat4TrailingPayloadFence'] : [];
        $trailingReady = ($trailingFence['allTrailingPayloadRowsResolveToCurrentSource'] ?? false) === true
            && ($trailingFence['allTrailingPayloadsMatchCurrentRows'] ?? false) === true
            && ($trailingFence['matchedSamplesRemainTrailingCompatible'] ?? false) === true;
        $ready = $trailingReady
            && $fence['currentPartialPredicateImplied'] === true
            && $fence['stalePreparedOnlyPredicatesUsed'] === []
            && $fence['unsupportedCurrentPartialPredicates'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next240-ready' : 'requires-current-source-partial-predicate-reprepare',
            'stat4CurrentPartialPredicateFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next240Ready' => $ready,
                'next240PreparedPartialSignature' => $fence['preparedPartialSignature'],
                'next240CurrentPartialSignature' => $fence['currentPartialSignature'],
                'next240WhereSignature' => $fence['whereSignature'],
                'next240CurrentOnlyPredicates' => $fence['currentOnlyPredicates'],
                'next240PreparedOnlyPredicates' => $fence['preparedOnlyPredicates'],
                'next240UnsupportedCurrentPartialPredicates' => $fence['unsupportedCurrentPartialPredicates'],
                'next240StalePreparedOnlyPredicatesUsed' => $fence['stalePreparedOnlyPredicatesUsed'],
                'next240ProofSignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next240CurrentPartialPredicateReady' => $ready,
                'next240CurrentPartialSignature' => $fence['currentPartialSignature'],
                'next240ProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT240 PARTIAL PREDICATE FENCE '
                . $selectedName
                . ($ready ? ' CURRENT PARTIAL PREDICATES PROVED' : ' REQUIRES CURRENT PARTIAL PREDICATE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext237Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next240',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next240 reuses current-source STAT4 expression partial planning and adds current partial-predicate implication fencing',
            'non_overlap' => 'adds current-source partial predicate implication/stale prepared predicate rejection after accepted next237 trailing-payload validation; avoids next237 payload duplicates, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next240 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next240 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next240 selected index missing from source');
    }

    /**
     * @param array<string,mixed> $preparedIndex
     * @param array<string,mixed> $currentIndex
     * @param list<array<string,mixed>> $whereTerms
     * @return array<string,mixed>
     */
    private static function partialPredicateFence(array $preparedIndex, array $currentIndex, array $whereTerms): array
    {
        $prepared = self::termList($preparedIndex['partialPredicateTerms'] ?? null);
        $current = self::termList($currentIndex['partialPredicateTerms'] ?? null);
        $where = self::termList($whereTerms);
        $preparedBySignature = self::termsBySignature($prepared);
        $currentBySignature = self::termsBySignature($current);
        $whereBySignature = self::termsBySignature($where);
        $preparedOnlySignatures = array_values(array_diff(array_keys($preparedBySignature), array_keys($currentBySignature)));
        $currentOnlySignatures = array_values(array_diff(array_keys($currentBySignature), array_keys($preparedBySignature)));
        $proofs = [];
        $unsupported = [];
        $missing = [];

        foreach ($currentBySignature as $signature => $term) {
            $implied = self::termImplied($term, $where, $whereBySignature);
            if (!$implied['supported']) {
                $unsupported[] = $term;
            }
            if (!$implied['implied']) {
                $missing[] = $term;
            }
            $proofs[] = [
                'signature' => $signature,
                'term' => $term,
                'currentOnly' => in_array($signature, $currentOnlySignatures, true),
                'matchedWhereSignature' => $implied['whereSignature'],
                'supported' => $implied['supported'],
                'implied' => $implied['implied'],
                'reason' => $implied['reason'],
            ];
        }

        $stalePreparedUsed = [];
        foreach ($preparedOnlySignatures as $signature) {
            if (isset($whereBySignature[$signature])) {
                $stalePreparedUsed[] = $preparedBySignature[$signature];
            }
        }

        $proof = [
            'preparedPartialPredicates' => array_values($preparedBySignature),
            'currentPartialPredicates' => array_values($currentBySignature),
            'wherePredicates' => array_values($whereBySignature),
            'proofs' => $proofs,
            'currentOnlyPredicates' => array_values(array_map(static fn (string $signature): array => $currentBySignature[$signature], $currentOnlySignatures)),
            'preparedOnlyPredicates' => array_values(array_map(static fn (string $signature): array => $preparedBySignature[$signature], $preparedOnlySignatures)),
            'unsupportedCurrentPartialPredicates' => $unsupported,
            'missingCurrentPartialPredicates' => $missing,
            'stalePreparedOnlyPredicatesUsed' => $stalePreparedUsed,
        ];

        return $proof + [
            'currentPartialPredicateImplied' => $missing === [] && $unsupported === [],
            'preparedPartialSignature' => self::signature(array_values($preparedBySignature)),
            'currentPartialSignature' => self::signature(array_values($currentBySignature)),
            'whereSignature' => self::signature(array_values($whereBySignature)),
            'proofSignature' => self::signature($proof),
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function termList(mixed $terms): array
    {
        if (!is_array($terms) || !array_is_list($terms)) {
            throw new \InvalidArgumentException('SQLite next240 needs list partial/where terms');
        }
        $out = [];
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next240 predicate terms must be arrays');
            }
            $out[] = self::normalizeTerm($term);
        }

        return $out;
    }

    /** @param array<string,mixed> $term @return array<string,mixed> */
    private static function normalizeTerm(array $term): array
    {
        $left = $term['left'] ?? null;
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next240 predicate terms need a left side');
        }
        $operator = strtoupper(trim((string) ($term['operator'] ?? '')));
        if ($operator === '') {
            throw new \InvalidArgumentException('SQLite next240 predicate terms need an operator');
        }
        $normalized = [
            'left' => array_key_exists('expression', $left)
                ? ['expression' => strtolower(trim((string) $left['expression']))]
                : ['column' => strtolower(trim((string) ($left['column'] ?? '')))],
            'operator' => $operator,
        ];
        if (array_key_exists('right', $term)) {
            $normalized['right'] = self::normalizeValue($term['right']);
        }
        if (array_key_exists('lower', $term)) {
            $normalized['lower'] = self::normalizeValue($term['lower']);
        }
        if (array_key_exists('upper', $term)) {
            $normalized['upper'] = self::normalizeValue($term['upper']);
        }

        return $normalized;
    }

    private static function normalizeValue(mixed $value): mixed
    {
        return is_string($value) ? strtolower($value) : $value;
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return array<string,array<string,mixed>>
     */
    private static function termsBySignature(array $terms): array
    {
        $out = [];
        foreach ($terms as $term) {
            $out[self::termSignature($term)] = $term;
        }
        ksort($out);

        return $out;
    }

    /**
     * @param array<string,mixed> $term
     * @param list<array<string,mixed>> $whereTerms
     * @param array<string,array<string,mixed>> $whereBySignature
     * @return array{supported:bool,implied:bool,whereSignature:string|null,reason:string}
     */
    private static function termImplied(array $term, array $whereTerms, array $whereBySignature): array
    {
        $signature = self::termSignature($term);
        if (isset($whereBySignature[$signature])) {
            return ['supported' => true, 'implied' => true, 'whereSignature' => $signature, 'reason' => 'exact'];
        }
        $operator = (string) $term['operator'];
        if ($operator === 'IS NOT NULL') {
            foreach ($whereTerms as $where) {
                if (self::leftSignature($where) === self::leftSignature($term)
                    && in_array((string) $where['operator'], ['=', 'LIKE', 'BETWEEN', '>', '>=', '<', '<='], true)
                ) {
                    return ['supported' => true, 'implied' => true, 'whereSignature' => self::termSignature($where), 'reason' => 'comparison-implies-not-null'];
                }
            }

            return ['supported' => true, 'implied' => false, 'whereSignature' => null, 'reason' => 'missing-not-null'];
        }
        if (in_array($operator, ['>=', '>'], true)) {
            return self::rangeImplied($term, $whereTerms, 'lower');
        }
        if (in_array($operator, ['<=', '<'], true)) {
            return self::rangeImplied($term, $whereTerms, 'upper');
        }

        return ['supported' => in_array($operator, ['=', 'LIKE', 'BETWEEN'], true), 'implied' => false, 'whereSignature' => null, 'reason' => 'missing-exact-current-partial-term'];
    }

    /**
     * @param array<string,mixed> $term
     * @param list<array<string,mixed>> $whereTerms
     * @return array{supported:bool,implied:bool,whereSignature:string|null,reason:string}
     */
    private static function rangeImplied(array $term, array $whereTerms, string $side): array
    {
        $left = self::leftSignature($term);
        $needle = (string) ($term['right'] ?? '');
        foreach ($whereTerms as $where) {
            if (self::leftSignature($where) !== $left) {
                continue;
            }
            $operator = (string) $where['operator'];
            if ($operator === 'BETWEEN') {
                $value = (string) ($side === 'lower' ? ($where['lower'] ?? '') : ($where['upper'] ?? ''));
                if (($side === 'lower' && strcmp($value, $needle) >= 0) || ($side === 'upper' && strcmp($value, $needle) <= 0)) {
                    return ['supported' => true, 'implied' => true, 'whereSignature' => self::termSignature($where), 'reason' => 'between-implies-range'];
                }
            }
            if ($side === 'lower' && in_array($operator, ['>=', '>'], true) && strcmp((string) ($where['right'] ?? ''), $needle) >= 0) {
                return ['supported' => true, 'implied' => true, 'whereSignature' => self::termSignature($where), 'reason' => 'stronger-lower-bound'];
            }
            if ($side === 'upper' && in_array($operator, ['<=', '<'], true) && strcmp((string) ($where['right'] ?? ''), $needle) <= 0) {
                return ['supported' => true, 'implied' => true, 'whereSignature' => self::termSignature($where), 'reason' => 'stronger-upper-bound'];
            }
        }

        return ['supported' => true, 'implied' => false, 'whereSignature' => null, 'reason' => 'missing-range-bound'];
    }

    /** @param array<string,mixed> $term */
    private static function termSignature(array $term): string
    {
        return self::leftSignature($term) . ' ' . (string) $term['operator'] . ' ' . json_encode([
            'right' => $term['right'] ?? null,
            'lower' => $term['lower'] ?? null,
            'upper' => $term['upper'] ?? null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string,mixed> $term */
    private static function leftSignature(array $term): string
    {
        $left = $term['left'];

        return array_key_exists('expression', $left)
            ? 'expr:' . (string) $left['expression']
            : 'col:' . (string) $left['column'];
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
            'opcode' => 'RecheckCurrentPartialPredicateImplication',
            'mode' => 'next240-current-source-stat4-expression-partial-predicate',
            'currentPartialSignature' => $fence['currentPartialSignature'],
            'preparedPartialSignature' => $fence['preparedPartialSignature'],
            'currentOnlyCount' => count($fence['currentOnlyPredicates']),
            'preparedOnlyCount' => count($fence['preparedOnlyPredicates']),
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
