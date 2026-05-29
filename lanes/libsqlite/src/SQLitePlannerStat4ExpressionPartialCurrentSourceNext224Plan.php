<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan
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
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        return SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext224(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
    }
}
