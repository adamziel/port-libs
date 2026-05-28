<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext181Plan
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
            throw new \InvalidArgumentException('SQLite next181 WHERE terms cannot be empty');
        }

        $preparedProof = self::orProof($preparedSource, $whereTerms);
        $currentProof = self::orProof($currentSource, $whereTerms);
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
            && ($selectedProof['orPredicateImplied'] ?? false) === true;

        $plan['status'] = $ready ? 'stat4-expression-partial-current-source-next181-ready' : 'requires-next-stage';
        $plan['selectedPlan']['next181Ready'] = $ready;
        $plan['selectedPlan']['next181PartialOrPredicateImplied'] = (bool) ($selectedProof['orPredicateImplied'] ?? false);
        $plan['selectedPlan']['next181MatchedOrTerm'] = $selectedProof['matchedOrTerm'] ?? null;
        $plan['preparedPlan']['next181PartialOrPredicateImplied'] = (bool) ($preparedProof['orPredicateImplied'] ?? false);
        $plan['currentPlan']['next181PartialOrPredicateImplied'] = (bool) ($currentProof['orPredicateImplied'] ?? false);
        $plan['partialOrPredicate'] = [
            'prepared' => $preparedProof,
            'current' => $currentProof,
            'selected' => $selectedProof,
        ];
        $plan['stat4Fence']['next181PartialOrSignature'] = self::signature($selectedProof);
        $plan['cursorProgram'] = self::cursorProgram($plan['cursorProgram'], $selectedProof, $ready);
        $plan['detail'] = (($plan['selectedSource'] ?? null) === 'current' ? 'REPREPARE' : 'REUSE')
            . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT181 OR-PREDICATE FENCE '
            . (string) ($plan['selectedPlan']['name'] ?? 'NO INDEX');
        $plan['dependencies'] = ['sqlite-sqlplanner-stat4-expression-partial-current-source-next181'];
        $plan['dependency_closure'] = 'no new support component needed; next181 reuses next178 STAT4 expression ORDER fences and adds lane-local partial OR-predicate implication proof';
        $plan['non_overlap'] = 'extends accepted next178 STAT4 expression partial ORDER fence with SQLite partial-index OR-predicate implication; avoids accepted range-cost, expression ORDER BY, hidden JSON constraints, and prior AND-only partial-index proof slices';

        return $plan;
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $whereTerms
     * @return array{orPredicateImplied:bool,matchedOrTerm:?array<string,mixed>,candidateOrTerms:list<array<string,mixed>>,matchedWhereTerm:?array<string,mixed>,rawMatchedOrTerm:?array<string,mixed>}
     */
    private static function orProof(array $source, array $whereTerms): array
    {
        $candidateTerms = [];
        foreach (self::listValue($source['indexes'] ?? []) as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next181 indexes must be arrays');
            }
            foreach (self::listValue($index['partialPredicateAnyTerms'] ?? []) as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite next181 partial OR terms must be arrays');
                }
                $candidateTerms[] = $term;
            }
        }

        foreach ($candidateTerms as $candidate) {
            foreach ($whereTerms as $where) {
                if (self::termKey($candidate) === self::termKey($where)
                    && self::literal($candidate['right'] ?? null) === self::literal($where['right'] ?? null)) {
                    return [
                        'orPredicateImplied' => true,
                        'matchedOrTerm' => self::normalizedTerm($candidate),
                        'candidateOrTerms' => array_map(self::normalizedTerm(...), $candidateTerms),
                        'matchedWhereTerm' => self::normalizedTerm($where),
                        'rawMatchedOrTerm' => $candidate,
                    ];
                }
            }
        }

        return [
            'orPredicateImplied' => $candidateTerms === [],
            'matchedOrTerm' => null,
            'candidateOrTerms' => array_map(self::normalizedTerm(...), $candidateTerms),
            'matchedWhereTerm' => null,
            'rawMatchedOrTerm' => null,
        ];
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $proof @return array<string,mixed> */
    private static function adaptSource(array $source, array $proof): array
    {
        foreach (self::listValue($source['indexes'] ?? []) as $offset => $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next181 indexes must be arrays');
            }
            if (($index['partialPredicateAnyTerms'] ?? []) === []) {
                continue;
            }
            if (($proof['orPredicateImplied'] ?? false) !== true || !is_array($proof['matchedOrTerm'] ?? null)) {
                $index['partialPredicateTerms'][] = [
                    'left' => ['column' => '__next181_unproved_partial_or__'],
                    'operator' => '=',
                    'right' => '__never__',
                ];
            } elseif (is_array($proof['rawMatchedOrTerm'] ?? null)) {
                $index['partialPredicateTerms'][] = $proof['rawMatchedOrTerm'];
            }
            unset($index['partialPredicateAnyTerms']);
            $source['indexes'][$offset] = $index;
        }

        return $source;
    }

    /** @param mixed $value @return list<mixed> */
    private static function listValue(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next181 expected a list');
        }

        return $value;
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

    /** @param array<string,mixed> $term */
    private static function termKey(array $term): string
    {
        return self::leftKey($term['left'] ?? null) . ':' . strtoupper((string) ($term['operator'] ?? ''));
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
            return [['opcode' => 'Replan', 'reason' => 'stat4-expression-partial-or-predicate-fence']];
        }

        array_splice($cursorProgram, 5, 0, [[
            'opcode' => 'RecheckPartialOrPredicate',
            'implied' => true,
            'matchedTerm' => $proof['matchedOrTerm'] ?? null,
        ]]);

        return $cursorProgram;
    }

    /** @param mixed $value */
    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
