<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext165Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns
    ): array {
        $plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNext154Plan::materialize(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns
        );
        $ready = ($plan['status'] ?? null) === 'stat4-expression-partial-current-source-next154-ready';

        $plan['status'] = $ready
            ? 'stat4-expression-partial-range-current-source-next165-ready'
            : 'requires-next-stage';
        $plan['rangeConstraintOperators'] = self::rangeConstraintOperators($queryTerms);
        $plan['partialRangePredicateOperators'] = self::partialRangeOperators($plan['selectedPlan']['partialPredicateTerms'] ?? []);
        $plan['detail'] = str_replace('NEXT154', 'NEXT165 RANGE', (string) ($plan['detail'] ?? ''));
        $plan['dependencies'] = ['sqlite-sqlplanner-stat4-expression-partial-range-current-source-next165'];
        $plan['dependency_closure'] = 'no new support component needed; next165 reuses lane-local STAT4 expression row streams and adds bounded one-sided range implication for partial predicates';
        $plan['non_overlap'] = 'avoids accepted next154 equality/IN/BETWEEN row streams, expression partial covering, expression ORDER BY, range-cost ranking, JSON, VFS/WAL, and B-tree clusters by testing one-sided range constraints proving a partial expression index from the current STAT4 source';

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $queryTerms
     * @return list<string>
     */
    private static function rangeConstraintOperators(array $queryTerms): array
    {
        $operators = [];
        foreach ($queryTerms as $term) {
            if (!is_array($term['left'] ?? null) || !isset($term['left']['expression'])) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if (in_array($operator, ['>', '>=', '<', '<='], true)) {
                $operators[] = $operator;
            }
        }

        return array_values(array_unique($operators));
    }

    /**
     * @param mixed $partialTerms
     * @return list<string>
     */
    private static function partialRangeOperators(mixed $partialTerms): array
    {
        if (!is_array($partialTerms)) {
            return [];
        }
        $operators = [];
        foreach ($partialTerms as $term) {
            if (!is_array($term)) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if (in_array($operator, ['>', '>=', '<', '<='], true)) {
                $operators[] = $operator;
            }
        }

        return array_values(array_unique($operators));
    }
}
