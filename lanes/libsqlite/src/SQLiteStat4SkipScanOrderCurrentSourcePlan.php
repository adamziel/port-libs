<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteStat4SkipScanOrderCurrentSourcePlan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $orderBy
     * @return array<string,mixed>
     */
    public static function compare(array $preparedSource, array $currentSource, array $orderBy = []): array
    {
        $prepared = self::sourcePlan($preparedSource, $orderBy);
        $current = self::sourcePlan($currentSource, $orderBy);

        $preparedCookie = self::intValue($preparedSource, 'schemaCookie');
        $currentCookie = self::intValue($currentSource, 'schemaCookie');
        $preparedStat4 = self::intValue($preparedSource, 'stat4Generation');
        $currentStat4 = self::intValue($currentSource, 'stat4Generation');
        $stale = $preparedCookie !== $currentCookie || $preparedStat4 !== $currentStat4;
        $selected = $stale ? $current : $prepared;

        return [
            'status' => $selected['status'],
            'selectedSource' => $stale ? 'current' : 'prepared',
            'preparedSource' => self::sourceSummary($preparedSource, $prepared),
            'currentSource' => self::sourceSummary($currentSource, $current),
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => $preparedCookie !== $currentCookie,
            'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
            'orderByModeChanged' => ($prepared['orderByMode'] ?? null) !== ($current['orderByMode'] ?? null),
            'orderBySatisfiedChanged' => ($prepared['orderBySatisfied'] ?? null) !== ($current['orderBySatisfied'] ?? null),
            'rowidDelta' => self::rowidDelta((array) ($prepared['rowids'] ?? []), (array) ($current['rowids'] ?? [])),
            'estimatedRowsDelta' => (int) ($current['estimatedRows'] ?? 0) - (int) ($prepared['estimatedRows'] ?? 0),
            'selectedPlan' => $selected,
            'detail' => self::detail($stale, $selected, $currentSource),
            'dependencies' => [
                'SQLiteSkipScanStat4PartialOrderPlan',
                'SQLiteIndexPredicate',
                'SQLiteIndexSkipScanPlan',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $orderBy
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source, array $orderBy): array
    {
        $partial = self::partialPredicate($source['partialPredicate'] ?? null);

        return SQLiteSkipScanStat4PartialOrderPlan::plan(
            self::listValue($source, 'rows'),
            self::stringValue($source, 'indexName'),
            self::stringValue($source, 'skippedColumn'),
            self::stringValue($source, 'rangeColumn'),
            $source['lower'] ?? null,
            $source['upper'] ?? null,
            $partial,
            self::listValue($source, 'queryTerms'),
            self::listValue($source, 'stat4Samples'),
            $orderBy,
            (bool) ($source['upperInclusive'] ?? true),
            self::stringValue($source, 'collation', 'BINARY'),
        );
    }

    private static function partialPredicate(mixed $definition): SQLiteIndexPredicate
    {
        if ($definition instanceof SQLiteIndexPredicate) {
            return $definition;
        }
        if ($definition === null || $definition === []) {
            return new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, []);
        }
        if (!is_array($definition) || !array_is_list($definition)) {
            throw new \InvalidArgumentException('SQLite STAT4 current-source partial predicate must be a list');
        }

        $terms = [];
        foreach ($definition as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite STAT4 current-source partial predicate terms must be arrays');
            }
            $terms[] = new SQLiteIndexPredicate(
                self::stringValue($term, 'column'),
                self::predicateOperator(self::stringValue($term, 'operator')),
                $term['value'] ?? null,
            );
        }

        return new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, $terms);
    }

    private static function predicateOperator(string $operator): string
    {
        return match (strtoupper($operator)) {
            '=', '==' => SQLiteIndexPredicate::EQUALS,
            '!=', '<>' => SQLiteIndexPredicate::NOT_EQUALS,
            '<' => SQLiteIndexPredicate::LESS_THAN,
            '<=' => SQLiteIndexPredicate::LESS_THAN_OR_EQUAL,
            '>' => SQLiteIndexPredicate::GREATER_THAN,
            '>=' => SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL,
            'BETWEEN' => SQLiteIndexPredicate::BETWEEN,
            'IN', 'IN_LIST' => SQLiteIndexPredicate::IN_LIST,
            'IS_NOT_NULL' => SQLiteIndexPredicate::IS_NOT_NULL,
            SQLiteIndexPredicate::EQUALS,
            SQLiteIndexPredicate::NOT_EQUALS,
            SQLiteIndexPredicate::LESS_THAN,
            SQLiteIndexPredicate::LESS_THAN_OR_EQUAL,
            SQLiteIndexPredicate::GREATER_THAN,
            SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL,
            SQLiteIndexPredicate::BETWEEN,
            SQLiteIndexPredicate::IN_LIST,
            SQLiteIndexPredicate::IS_NOT_NULL => strtoupper($operator),
            default => throw new \InvalidArgumentException('SQLite STAT4 current-source partial predicate operator is not supported'),
        };
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function sourceSummary(array $source, array $plan): array
    {
        return [
            'name' => self::stringValue($source, 'name', 'source'),
            'schemaCookie' => self::intValue($source, 'schemaCookie'),
            'stat4Generation' => self::intValue($source, 'stat4Generation'),
            'rowids' => $plan['rowids'] ?? [],
            'estimatedRows' => $plan['estimatedRows'] ?? 0,
            'estimatedCost' => $plan['estimatedCost'] ?? 0,
            'orderByMode' => $plan['orderByMode'] ?? 'none',
            'orderBySatisfied' => $plan['orderBySatisfied'] ?? false,
            'stat4CurrentNextByPrefix' => $plan['stat4CurrentNextByPrefix'] ?? [],
        ];
    }

    /**
     * @param list<mixed> $prepared
     * @param list<mixed> $current
     * @return array{added:list<mixed>,removed:list<mixed>,stable:list<mixed>}
     */
    private static function rowidDelta(array $prepared, array $current): array
    {
        return [
            'added' => array_values(array_diff($current, $prepared)),
            'removed' => array_values(array_diff($prepared, $current)),
            'stable' => array_values(array_intersect($current, $prepared)),
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $currentSource
     */
    private static function detail(bool $stale, array $plan, array $currentSource): string
    {
        $sourceName = self::stringValue($currentSource, 'name', 'current');
        $action = $stale ? 'REPREPARE USING CURRENT SOURCE ' : 'REUSE PREPARED SOURCE ';

        return $action . $sourceName . ' ' . (string) ($plan['detail'] ?? 'STAT4 SKIP-SCAN');
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function stringValue(array $data, string $key, ?string $default = null): string
    {
        $value = $data[$key] ?? $default;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite STAT4 current-source planner needs {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function intValue(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite STAT4 current-source planner needs non-negative integer {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $data
     * @return list<array<string,mixed>>
     */
    private static function listValue(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("SQLite STAT4 current-source planner needs list {$key}");
        }

        return $value;
    }
}
