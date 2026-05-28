<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext169Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $whereTerms, array $neededColumns): array
    {
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext164Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
        );
        $selectedSource = ($base['selectedSource'] ?? 'prepared') === 'current' ? $currentSource : $preparedSource;
        $selected = is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [];
        $competitors = self::competingExpressionIndexes($selectedSource, (string) ($selected['name'] ?? ''));
        $partialCost = self::intValue($selected, 'estimatedCost');
        $bestFullCost = self::bestFullCost($competitors);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next164-ready'
            && $competitors !== []
            && $bestFullCost !== null
            && $partialCost < $bestFullCost;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next169-ready' : 'requires-next-stage',
            'selectedPlan' => array_replace($selected, [
                'next169Ready' => $ready,
                'next169PartialBeatsFullExpressionIndex' => $ready,
                'next169PartialCost' => $partialCost,
                'next169BestFullExpressionCost' => $bestFullCost,
            ]),
            'competingExpressionIndexes' => $competitors,
            'rejectedFullExpressionIndexes' => array_values(array_filter(
                $competitors,
                static fn (array $plan): bool => ($plan['partial'] ?? false) === false && ($plan['estimatedCost'] ?? PHP_INT_MAX) > $partialCost,
            )),
            'costFence' => [
                'selectedPartialIndex' => $selected['name'] ?? null,
                'selectedPartialCost' => $partialCost,
                'bestFullExpressionCost' => $bestFullCost,
                'costDelta' => $bestFullCost === null ? null : $bestFullCost - $partialCost,
                'sourceSignature' => self::signature($selectedSource),
                'candidateSignature' => self::signature($competitors),
            ],
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT169 COST-FENCE '
                . (string) ($selected['name'] ?? 'NO INDEX'),
            'dependencies' => ['sqlite-sqlplanner-stat4-expression-partial-current-source-next169'],
            'dependency_closure' => 'no new support component needed; next169 reuses current-source STAT4 range admission and adds lane-local cost fencing between partial and full expression indexes',
            'non_overlap' => 'avoids accepted next154 equality/IN/BETWEEN row streams, next158 stale-row range windows, next161 OR-split probes, next164 range implication, next165 partial-range planning, and expression-index range-cost ranking by proving current-source STAT4 re-costing prefers a partial expression index over a competing full expression index',
        ]);
    }

    /**
     * @param array<string,mixed> $source
     * @return list<array<string,mixed>>
     */
    private static function competingExpressionIndexes(array $source, string $selectedName): array
    {
        $out = [];
        foreach (($source['indexes'] ?? []) as $index) {
            if (!is_array($index) || (string) ($index['expression'] ?? '') === '') {
                continue;
            }
            $stat4 = is_array($index['stat4Samples'] ?? null) ? $index['stat4Samples'] : [];
            $rows = max(1, array_sum(array_map(
                static fn (mixed $sample): int => is_array($sample) ? self::firstStatInt($sample['neq'] ?? 1) : 1,
                $stat4,
            )));
            $partial = (($index['partialPredicateTerms'] ?? []) !== []);
            $covering = self::covers($index['coveringColumns'] ?? [], ['option_name', 'option_value', 'updated_at']);
            $out[] = [
                'name' => (string) ($index['name'] ?? ''),
                'selected' => (string) ($index['name'] ?? '') === $selectedName,
                'partial' => $partial,
                'expression' => (string) ($index['expression'] ?? ''),
                'stat4SampleCount' => count($stat4),
                'estimatedRows' => $rows,
                'estimatedCost' => $rows + ($covering ? 0 : 12) + ($partial ? 0 : 18),
                'covering' => $covering,
            ];
        }
        usort($out, static fn (array $a, array $b): int => [$a['estimatedCost'], $a['name']] <=> [$b['estimatedCost'], $b['name']]);

        return $out;
    }

    /** @param array<string,mixed> $plans */
    private static function bestFullCost(array $plans): ?int
    {
        $cost = null;
        foreach ($plans as $plan) {
            if (($plan['partial'] ?? false) === true) {
                continue;
            }
            $value = self::intValue($plan, 'estimatedCost');
            $cost = $cost === null ? $value : min($cost, $value);
        }

        return $cost;
    }

    private static function covers(mixed $available, array $needed): bool
    {
        $set = array_flip(array_map('strval', is_array($available) ? $available : []));
        foreach ($needed as $column) {
            if (!isset($set[$column])) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $source */
    private static function intValue(array $source, string $key): int
    {
        $value = $source[$key] ?? 0;
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new \InvalidArgumentException('SQLite next169 integer value expected for ' . $key);
        }

        return (int) $value;
    }

    private static function firstStatInt(mixed $value): int
    {
        if (is_string($value)) {
            $value = preg_split('/\s+/', trim($value))[0] ?? '0';
        }
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new \InvalidArgumentException('SQLite next169 STAT4 integer expected');
        }

        return (int) $value;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
