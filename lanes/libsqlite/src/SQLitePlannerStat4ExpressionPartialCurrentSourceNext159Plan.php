<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext159Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @param array<string,mixed>|null $nextSource
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        SQLiteIndexPredicate $partialPredicate,
        array $queryTerms,
        array $neededColumns,
        ?array $nextSource = null,
    ): array {
        self::validateTerms($queryTerms);
        self::validateNeededColumns($neededColumns);

        $preparedPlan = self::sourcePlan($preparedSource, $partialPredicate, $queryTerms, $neededColumns);
        $currentPlan = self::sourcePlan($currentSource, $partialPredicate, $queryTerms, $neededColumns);
        $preparedSignature = self::sourceSignature($preparedSource);
        $currentSignature = self::sourceSignature($currentSource);
        $stale = $preparedSignature !== $currentSignature;
        $selectedSource = $stale ? $currentSource : $preparedSource;
        $selectedPlan = $stale ? $currentPlan : $preparedPlan;
        $nextPlan = $nextSource === null ? null : self::sourcePlan($nextSource, $partialPredicate, $queryTerms, $neededColumns);
        $nextAdmitted = $nextSource === null || self::sourceSignature($nextSource) === self::sourceSignature($selectedSource);
        $ready = $selectedPlan['usable'] === true && $selectedPlan['partialPredicateImplied'] === true;

        return [
            'status' => $ready && $nextAdmitted
                ? 'stat4-expression-partial-current-source-next159-ready'
                : 'requires-current-source-reprepare',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale || !$nextAdmitted || !$ready,
            'schemaCookieChanged' => self::intValue($preparedSource, 'schemaCookie') !== self::intValue($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::intValue($preparedSource, 'stat4Generation') !== self::intValue($currentSource, 'stat4Generation'),
            'indexSignatureChanged' => self::indexSignature($preparedSource) !== self::indexSignature($currentSource),
            'preparedPlan' => $preparedPlan,
            'currentPlan' => $currentPlan,
            'nextPlan' => $nextPlan,
            'nextSourceAdmitted' => $nextAdmitted,
            'selectedPlan' => $selectedPlan,
            'yieldProgram' => self::yieldProgram($selectedPlan, $selectedSource),
            'coveringRows' => self::coveringRows($selectedPlan, $neededColumns),
            'tableLookupRows' => self::tableLookupRows($selectedPlan, $neededColumns),
            'stat4YieldPairs' => self::stat4YieldPairs($selectedPlan),
            'currentSourceFence' => [
                'schemaCookie' => self::intValue($currentSource, 'schemaCookie'),
                'stat4Generation' => self::intValue($currentSource, 'stat4Generation'),
                'indexSignature' => self::indexSignature($currentSource),
                'rowsetSignature' => self::signature($currentPlan['rowids']),
                'stat4Signature' => self::signature($currentPlan['stat4Samples']),
                'programSignature' => self::signature(self::yieldProgram($currentPlan, $currentSource)),
            ],
            'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                . ' STAT4 PARTIAL EXPRESSION CURRENT-SOURCE YIELD next159',
            'dependencies' => ['sqlite-sqlplanner-stat4-expression-partial-current-source-next159'],
            'dependency_closure' => 'no new support component needed; next159 reuses native partial-index proof, expression-key materialization, STAT4 sample diagnostics, and current-source fences',
            'non_overlap' => 'avoids accepted expression ORDER BY, expression-index range-cost, STAT4 skip-scan, subquery partial-index, JSON, VFS, WAL, and B-tree clusters; this slice is the non-skip-scan STAT4 expression partial current-source yield boundary',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function sourcePlan(
        array $source,
        SQLiteIndexPredicate $partialPredicate,
        array $queryTerms,
        array $neededColumns,
    ): array {
        $expression = self::stringValue($source, 'expression');
        $expressionColumn = self::stringValue($source, 'expressionColumn');
        $lower = self::rangeBound($queryTerms, $expression, 'lower');
        $upper = self::rangeBound($queryTerms, $expression, 'upper');
        $partialImplied = self::partialImplied($partialPredicate, $queryTerms);
        $rows = [];
        foreach (self::listValue($source, 'rows') as $row) {
            $expressionValue = $row[$expressionColumn] ?? null;
            if (!$partialImplied || !self::rowMatchesPartial($partialPredicate, $row)) {
                continue;
            }
            if (!self::withinRange($expressionValue, $lower, $upper)) {
                continue;
            }
            $rows[] = $row;
        }

        usort($rows, static fn (array $left, array $right): int => [
            strtolower((string) ($left[$expressionColumn] ?? '')),
            (int) ($left['rowid'] ?? 0),
        ] <=> [
            strtolower((string) ($right[$expressionColumn] ?? '')),
            (int) ($right['rowid'] ?? 0),
        ]);

        $coveringColumns = self::stringList($source, 'coveringColumns');
        $missingColumns = array_values(array_diff($neededColumns, $coveringColumns));
        $samples = self::matchedStat4Samples($source, $lower, $upper);

        return [
            'usable' => $partialImplied && ($lower !== null || $upper !== null),
            'sourceName' => self::stringValue($source, 'name'),
            'indexName' => self::stringValue($source, 'indexName'),
            'rootPage' => self::intValue($source, 'rootPage'),
            'expression' => $expression,
            'expressionColumn' => $expressionColumn,
            'partialPredicateImplied' => $partialImplied,
            'rangeLower' => $lower['value'] ?? null,
            'rangeUpper' => $upper['value'] ?? null,
            'lowerInclusive' => $lower['inclusive'] ?? false,
            'upperInclusive' => $upper['inclusive'] ?? false,
            'covering' => $missingColumns === [],
            'tableLookupRequired' => $missingColumns !== [],
            'missingCoveringColumns' => $missingColumns,
            'estimatedRows' => max(1, array_sum(array_map(static fn (array $sample): int => (int) $sample['nEq'], $samples))),
            'estimatedCost' => max(1, count($rows) + count($missingColumns) * 15),
            'rowids' => array_map(static fn (array $row): int => (int) ($row['rowid'] ?? 0), $rows),
            'rows' => $rows,
            'stat4Samples' => $samples,
            'stat4SampleCount' => count($samples),
            'detail' => 'SEARCH ' . self::stringValue($source, 'indexName') . ' USING STAT4 PARTIAL EXPRESSION RANGE',
        ];
    }

    /**
     * @param list<array<string,mixed>> $queryTerms
     * @return array{value:mixed,inclusive:bool}|null
     */
    private static function rangeBound(array $queryTerms, string $expression, string $side): ?array
    {
        $candidate = null;
        foreach ($queryTerms as $term) {
            if (self::termExpression($term) !== strtolower($expression)) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if ($side === 'lower' && ($operator === '>' || $operator === '>=')) {
                $candidate = ['value' => $term['right'] ?? null, 'inclusive' => $operator === '>='];
            }
            if ($side === 'upper' && ($operator === '<' || $operator === '<=')) {
                $candidate = ['value' => $term['right'] ?? null, 'inclusive' => $operator === '<='];
            }
            if ($operator === 'BETWEEN') {
                $candidate = [
                    'value' => $side === 'lower' ? ($term['lower'] ?? null) : ($term['upper'] ?? null),
                    'inclusive' => true,
                ];
            }
        }

        return $candidate;
    }

    private static function termExpression(array $term): ?string
    {
        $left = $term['left'] ?? null;
        if (is_array($left) && is_string($left['expression'] ?? null)) {
            return strtolower($left['expression']);
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $queryTerms
     */
    private static function partialImplied(SQLiteIndexPredicate $predicate, array $queryTerms): bool
    {
        foreach (self::predicateChildren($predicate) as $child) {
            if (!self::singlePredicateImplied($child, $queryTerms)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $queryTerms
     */
    private static function singlePredicateImplied(SQLiteIndexPredicate $predicate, array $queryTerms): bool
    {
        if ($predicate->operator === SQLiteIndexPredicate::EQUALS) {
            foreach ($queryTerms as $term) {
                $left = $term['left'] ?? null;
                if (($term['operator'] ?? null) === '=' && is_array($left) && ($left['column'] ?? null) === $predicate->columnName && ($term['right'] ?? null) === $predicate->value) {
                    return true;
                }
            }
        }
        if ($predicate->operator === SQLiteIndexPredicate::IS_NOT_NULL) {
            foreach ($queryTerms as $term) {
                $left = $term['left'] ?? null;
                if (($term['operator'] ?? null) === 'IS NOT NULL' && is_array($left) && ($left['column'] ?? null) === $predicate->columnName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowMatchesPartial(SQLiteIndexPredicate $predicate, array $row): bool
    {
        foreach (self::predicateChildren($predicate) as $child) {
            if ($child->operator === SQLiteIndexPredicate::EQUALS && ($row[$child->columnName] ?? null) !== $child->value) {
                return false;
            }
            if ($child->operator === SQLiteIndexPredicate::IS_NOT_NULL && ($row[$child->columnName] ?? null) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<SQLiteIndexPredicate>
     */
    private static function predicateChildren(SQLiteIndexPredicate $predicate): array
    {
        if ($predicate->operator !== SQLiteIndexPredicate::AND) {
            return [$predicate];
        }
        if (!is_array($predicate->value) || !array_is_list($predicate->value)) {
            throw new \InvalidArgumentException('SQLite planner STAT4 expression partial next159 expected AND predicate children');
        }
        foreach ($predicate->value as $child) {
            if (!$child instanceof SQLiteIndexPredicate) {
                throw new \InvalidArgumentException('SQLite planner STAT4 expression partial next159 expected predicate child objects');
            }
        }

        return $predicate->value;
    }

    /**
     * @param array{value:mixed,inclusive:bool}|null $lower
     * @param array{value:mixed,inclusive:bool}|null $upper
     */
    private static function withinRange(mixed $value, ?array $lower, ?array $upper): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $normalized = strtolower($value);
        if ($lower !== null) {
            $lowerValue = strtolower((string) $lower['value']);
            $compare = $normalized <=> $lowerValue;
            if ($compare < 0 || ($compare === 0 && !$lower['inclusive'])) {
                return false;
            }
        }
        if ($upper !== null) {
            $upperValue = strtolower((string) $upper['value']);
            $compare = $normalized <=> $upperValue;
            if ($compare > 0 || ($compare === 0 && !$upper['inclusive'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $source
     * @param array{value:mixed,inclusive:bool}|null $lower
     * @param array{value:mixed,inclusive:bool}|null $upper
     * @return list<array<string,mixed>>
     */
    private static function matchedStat4Samples(array $source, ?array $lower, ?array $upper): array
    {
        $matched = [];
        foreach (self::listValue($source, 'stat4Samples') as $sample) {
            $key = $sample['key'] ?? null;
            if (!self::withinRange(is_string($key) ? $key : null, $lower, $upper)) {
                continue;
            }
            $matched[] = [
                'key' => $key,
                'nEq' => (int) ($sample['nEq'] ?? 1),
                'nLt' => (int) ($sample['nLt'] ?? 0),
                'nDLt' => (int) ($sample['nDLt'] ?? 0),
                'rowid' => (int) ($sample['rowid'] ?? 0),
            ];
        }

        return $matched;
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $source
     * @return list<array<string,mixed>>
     */
    private static function yieldProgram(array $plan, array $source): array
    {
        if ($plan['usable'] !== true) {
            return [];
        }

        return [
            ['opcode' => 'OpenRead', 'rootPage' => $plan['rootPage'], 'indexName' => $plan['indexName']],
            ['opcode' => ($plan['lowerInclusive'] ?? false) ? 'SeekGE' : 'SeekGT', 'column' => $plan['expressionColumn'], 'value' => $plan['rangeLower']],
            ['opcode' => ($plan['upperInclusive'] ?? false) ? 'IdxGT' : 'IdxGE', 'column' => $plan['expressionColumn'], 'value' => $plan['rangeUpper']],
            ['opcode' => 'Column', 'columns' => self::stringList($source, 'coveringColumns')],
            ['opcode' => $plan['tableLookupRequired'] ? 'DeferredSeek' : 'NoTableSeek', 'columns' => $plan['missingCoveringColumns']],
            ['opcode' => 'Next', 'rowids' => $plan['rowids']],
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function coveringRows(array $plan, array $neededColumns): array
    {
        $rows = [];
        foreach ($plan['rows'] as $row) {
            $payload = [];
            foreach ($neededColumns as $column) {
                if (!in_array($column, $plan['missingCoveringColumns'], true)) {
                    $payload[$column] = $row[$column] ?? null;
                }
            }
            $rows[] = ['rowid' => (int) ($row['rowid'] ?? 0), 'payload' => $payload];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function tableLookupRows(array $plan, array $neededColumns): array
    {
        if ($plan['missingCoveringColumns'] === []) {
            return [];
        }
        $rows = [];
        foreach ($plan['rows'] as $row) {
            $payload = [];
            foreach ($neededColumns as $column) {
                $payload[$column] = $row[$column] ?? null;
            }
            $rows[] = ['rowid' => (int) ($row['rowid'] ?? 0), 'payload' => $payload];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    private static function stat4YieldPairs(array $plan): array
    {
        $pairs = [];
        $samples = $plan['stat4Samples'];
        foreach ($samples as $offset => $sample) {
            $pairs[] = [
                'current' => $sample,
                'next' => $samples[$offset + 1] ?? null,
                'yieldAfterRowid' => $sample['rowid'],
            ];
        }

        return $pairs;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function listValue(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("SQLite planner STAT4 expression partial next159 expected list {$key}");
        }
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException("SQLite planner STAT4 expression partial next159 expected array items in {$key}");
            }
        }

        return $value;
    }

    private static function stringValue(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite planner STAT4 expression partial next159 expected string {$key}");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("SQLite planner STAT4 expression partial next159 expected list {$key}");
        }
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException("SQLite planner STAT4 expression partial next159 expected non-empty string values in {$key}");
            }
        }

        return array_values($value);
    }

    private static function intValue(array $source, string $key): int
    {
        $value = $source[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite planner STAT4 expression partial next159 expected non-negative int {$key}");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function validateTerms(array $terms): void
    {
        foreach ($terms as $term) {
            if (!isset($term['operator']) || !is_string($term['operator'])) {
                throw new \InvalidArgumentException('SQLite planner STAT4 expression partial next159 query terms require operators');
            }
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function validateNeededColumns(array $columns): void
    {
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite planner STAT4 expression partial next159 needed columns must be non-empty strings');
            }
        }
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceSignature(array $source): string
    {
        return self::signature([
            self::intValue($source, 'schemaCookie'),
            self::intValue($source, 'stat4Generation'),
            self::indexSignature($source),
        ]);
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function indexSignature(array $source): string
    {
        return self::signature([
            self::stringValue($source, 'indexName'),
            self::intValue($source, 'rootPage'),
            self::stringValue($source, 'expression'),
            self::stringList($source, 'coveringColumns'),
        ]);
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
