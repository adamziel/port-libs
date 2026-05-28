<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext184Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @param array{expression:string,direction?:string,collation?:string}|null $orderBy
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $whereTerms,
        array $neededColumns,
        ?array $orderBy = null
    ): array {
        if ($whereTerms === []) {
            throw new \InvalidArgumentException('SQLite next184 WHERE terms cannot be empty');
        }

        $preparedProof = self::inProof($preparedSource, $whereTerms);
        $currentProof = self::inProof($currentSource, $whereTerms);
        $preparedAdapted = self::adaptSource($preparedSource, $preparedProof);
        $currentAdapted = self::adaptSource($currentSource, $currentProof);

        $plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNext178Plan::materialize(
            $preparedAdapted,
            $currentAdapted,
            $whereTerms,
            $neededColumns,
            $orderBy,
        );
        $selectedProof = ($plan['selectedSource'] ?? null) === 'current' ? $currentProof : $preparedProof;
        $ready = ($plan['status'] ?? null) === 'stat4-expression-partial-current-source-next178-ready'
            && ($selectedProof['inPredicateImplied'] ?? false) === true;

        $plan['status'] = $ready ? 'stat4-expression-partial-current-source-next184-ready' : 'requires-next-stage';
        $plan['selectedPlan']['next184Ready'] = $ready;
        $plan['selectedPlan']['next184PartialInPredicateImplied'] = (bool) ($selectedProof['inPredicateImplied'] ?? false);
        $plan['selectedPlan']['next184MatchedInColumn'] = $selectedProof['matchedColumn'] ?? null;
        $plan['selectedPlan']['next184MatchedInValue'] = $selectedProof['matchedValue'] ?? null;
        $plan['preparedPlan']['next184PartialInPredicateImplied'] = (bool) ($preparedProof['inPredicateImplied'] ?? false);
        $plan['currentPlan']['next184PartialInPredicateImplied'] = (bool) ($currentProof['inPredicateImplied'] ?? false);
        $plan['partialInPredicate'] = [
            'prepared' => $preparedProof,
            'current' => $currentProof,
            'selected' => $selectedProof,
        ];
        $plan['stat4Fence']['next184PartialInSignature'] = self::signature($selectedProof);
        $plan['cursorProgram'] = self::cursorProgram($plan['cursorProgram'], $selectedProof, $ready);
        $plan['detail'] = (($plan['selectedSource'] ?? null) === 'current' ? 'REPREPARE' : 'REUSE')
            . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT184 IN-PREDICATE FENCE '
            . (string) ($plan['selectedPlan']['name'] ?? 'NO INDEX');
        $plan['dependencies'] = ['sqlite-sqlplanner-stat4-expression-partial-current-source-next184'];
        $plan['dependency_closure'] = 'no new support component needed; next184 reuses next178 STAT4 expression partial fences and adds lane-local partial IN-predicate implication proof';
        $plan['non_overlap'] = 'extends accepted next178 STAT4 expression partial current-source scans with partial-index IN-predicate implication; avoids next181 OR-predicate proof, accepted range-cost, expression ORDER BY, JSON, WAL, VFS, and B-tree clusters';

        return $plan;
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $whereTerms
     * @return array{inPredicateImplied:bool,matchedColumn:?string,matchedValue:mixed,candidateInPredicates:list<array<string,mixed>>,matchedWhereTerm:?array<string,mixed>,rawMatchedTerm:?array<string,mixed>}
     */
    private static function inProof(array $source, array $whereTerms): array
    {
        $candidatePredicates = [];
        foreach (self::listValue($source['indexes'] ?? []) as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next184 indexes must be arrays');
            }
            foreach (self::listValue($index['partialPredicateInTerms'] ?? []) as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite next184 partial IN terms must be arrays');
                }
                $candidatePredicates[] = self::normalizedInTerm($term);
            }
        }

        foreach ($candidatePredicates as $candidate) {
            foreach ($whereTerms as $where) {
                if (strtoupper((string) ($where['operator'] ?? '')) !== '=') {
                    continue;
                }
                if (self::leftKey($where['left'] ?? null) !== $candidate['leftKey']) {
                    continue;
                }
                $value = self::literal($where['right'] ?? null);
                if (in_array($value, $candidate['values'], true)) {
                    return [
                        'inPredicateImplied' => true,
                        'matchedColumn' => $candidate['leftKey'],
                        'matchedValue' => $value,
                        'candidateInPredicates' => $candidatePredicates,
                        'matchedWhereTerm' => self::normalizedTerm($where),
                        'rawMatchedTerm' => [
                            'left' => $where['left'],
                            'operator' => '=',
                            'right' => $value,
                        ],
                    ];
                }
            }
        }

        return [
            'inPredicateImplied' => $candidatePredicates === [],
            'matchedColumn' => null,
            'matchedValue' => null,
            'candidateInPredicates' => $candidatePredicates,
            'matchedWhereTerm' => null,
            'rawMatchedTerm' => null,
        ];
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $proof @return array<string,mixed> */
    private static function adaptSource(array $source, array $proof): array
    {
        foreach (self::listValue($source['indexes'] ?? []) as $offset => $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next184 indexes must be arrays');
            }
            if (($index['partialPredicateInTerms'] ?? []) === []) {
                continue;
            }
            if (($proof['inPredicateImplied'] ?? false) !== true || !is_array($proof['rawMatchedTerm'] ?? null)) {
                $index['partialPredicateTerms'][] = [
                    'left' => ['column' => '__next184_unproved_partial_in__'],
                    'operator' => '=',
                    'right' => '__never__',
                ];
            } else {
                $index['partialPredicateTerms'][] = $proof['rawMatchedTerm'];
            }
            unset($index['partialPredicateInTerms']);
            $source['indexes'][$offset] = $index;
        }

        return $source;
    }

    /** @param mixed $value @return list<mixed> */
    private static function listValue(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next184 expected a list');
        }

        return $value;
    }

    /** @param array<string,mixed> $term @return array{leftKey:string,values:list<mixed>} */
    private static function normalizedInTerm(array $term): array
    {
        $values = $term['values'] ?? null;
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            throw new \InvalidArgumentException('SQLite next184 partial IN term needs value list');
        }

        return [
            'leftKey' => self::leftKey($term['left'] ?? null),
            'values' => array_map(self::literal(...), $values),
        ];
    }

    /** @param array<string,mixed> $term @return array<string,mixed> */
    private static function normalizedTerm(array $term): array
    {
        $out = [
            'leftKey' => self::leftKey($term['left'] ?? null),
            'operator' => strtoupper((string) ($term['operator'] ?? '')),
        ];
        if (array_key_exists('right', $term)) {
            $out['right'] = self::literal($term['right']);
        }

        return $out;
    }

    /** @param mixed $left */
    private static function leftKey(mixed $left): string
    {
        if (!is_array($left)) {
            return '';
        }
        if (isset($left['column'])) {
            return 'column:' . strtolower((string) $left['column']);
        }
        if (isset($left['expression'])) {
            return 'expression:' . self::normalizeExpression((string) $left['expression']);
        }

        return '';
    }

    /** @param mixed $value */
    private static function literal(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('literal', $value) ? $value['literal'] : $value;
    }

    private static function normalizeExpression(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', '', $expression));
    }

    /**
     * @param list<array<string,mixed>> $cursorProgram
     * @param array<string,mixed> $proof
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $cursorProgram, array $proof, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'Replan', 'reason' => 'stat4-expression-partial-in-predicate-fence']];
        }

        array_splice($cursorProgram, 5, 0, [[
            'opcode' => 'RecheckPartialInPredicate',
            'implied' => true,
            'matchedColumn' => $proof['matchedColumn'] ?? null,
            'matchedValue' => $proof['matchedValue'] ?? null,
        ]]);

        return $cursorProgram;
    }

    /** @param mixed $value */
    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
