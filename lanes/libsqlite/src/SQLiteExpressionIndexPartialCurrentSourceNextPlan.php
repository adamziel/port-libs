<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteExpressionIndexPartialCurrentSourceNextPlan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array{expression:string,direction?:string,collation?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $predicate, array $orderBy = []): array
    {
        $preparedPlan = self::sourcePlan($preparedSource, $predicate, $orderBy);
        $currentPlan = self::sourcePlan($currentSource, $predicate, $orderBy);
        $preparedCookie = self::nonNegativeInt($preparedSource, 'schemaCookie');
        $currentCookie = self::nonNegativeInt($currentSource, 'schemaCookie');
        $preparedSignature = self::sourceSignature($preparedSource);
        $currentSignature = self::sourceSignature($currentSource);
        $stale = $preparedCookie !== $currentCookie || $preparedSignature !== $currentSignature;
        $selected = $stale ? $currentPlan : $preparedPlan;
        $ready = ($selected['usable'] ?? false) === true
            && ($selected['partialPredicateImplied'] ?? false) === true
            && ($selected['currentRowsMaterialized'] ?? false) === true;

        return [
            'status' => $ready ? 'expression-index-partial-current-source-ready' : 'requires-next-stage',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => $preparedCookie !== $currentCookie,
            'indexSignatureChanged' => $preparedSignature !== $currentSignature,
            'preparedSource' => self::sourceSummary($preparedSource, $preparedPlan, $preparedSignature),
            'currentSource' => self::sourceSummary($currentSource, $currentPlan, $currentSignature),
            'selectedPlan' => $selected,
            'cursorTape' => self::cursorTape($selected, $stale ? 'current' : 'prepared', $orderBy),
            'currentSourceFence' => [
                'schemaCookie' => $currentCookie,
                'indexSignature' => $currentSignature,
                'orderSignature' => self::orderSignature($orderBy),
            ],
            'tableLookupDeferred' => true,
            'tempSortElided' => ($selected['orderBySatisfied'] ?? false) === true,
            'residualPredicateRequired' => true,
            'detail' => ($stale ? 'REPREPARE' : 'REUSE') . ' PARTIAL EXPRESSION INDEX CURRENT SOURCE ' . (string) ($selected['name'] ?? 'NO INDEX'),
            'dependencies' => [
                'SQLiteCreateIndex expression partial-predicate parsing',
                'SQLiteExpressionIndexPartialCurrentSourceNextPlan',
                'sqlite-expression-index-partial-current-source-next121',
            ],
            'dependency_closure' => 'no new support component needed; next121 composes existing native CREATE INDEX expression parsing, partial-index implication, and current-source row materialization',
            'non_overlap' => 'avoids accepted expression-index range-cost, covering ORDER BY, STAT4 collation, and JSON generated-index slices by focusing on partial expression-index current-source materialization with OR/AND partial predicate proof',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $predicate
     * @param list<array{expression:string,direction?:string,collation?:string}> $orderBy
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source, array $predicate, array $orderBy): array
    {
        self::validateOrderBy($orderBy);
        $rows = self::listValue($source, 'rows');
        $plans = [];
        foreach (self::listValue($source, 'indexes') as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite partial expression current-source indexes must be arrays');
            }
            $sql = self::stringValue($index, 'sql');
            $expression = self::firstExpression($sql);
            if ($expression === null || !$expression->partial || $expression->partialPredicate === null) {
                continue;
            }
            $expressionSql = self::expressionSql($expression);
            $constraint = self::expressionConstraint($predicate, $expressionSql);
            if ($constraint === null || !self::partialPredicateImplied($expression->partialPredicate, $predicate)) {
                continue;
            }

            $matchedRows = self::matchedRows($rows, $expression, $constraint, $predicate);
            $orderSatisfied = self::orderSatisfied($orderBy, $expressionSql, $expression->collation, $expression->descending);
            $plans[] = [
                'usable' => true,
                'name' => self::stringValue($index, 'name', self::indexName($sql)),
                'rootPage' => self::nonNegativeInt($index, 'rootPage'),
                'expression' => $expressionSql,
                'expressionColumn' => $expression->columnName,
                'collation' => strtoupper($expression->collation),
                'descending' => $expression->descending,
                'partial' => true,
                'partialPredicate' => self::predicateSummary($expression->partialPredicate),
                'partialPredicateImplied' => true,
                'constraintOperator' => $constraint['operator'],
                'constraintValues' => $constraint['values'],
                'orderBySatisfied' => $orderSatisfied,
                'currentRowsMaterialized' => true,
                'matchedRowCount' => count($matchedRows),
                'matchedRowids' => array_map(static fn (array $row): int => $row['rowid'], $matchedRows),
                'expressionKeys' => array_map(static fn (array $row): mixed => $row['key'], $matchedRows),
                'currentNextRows' => self::currentNextRows($matchedRows),
                'estimatedRows' => max(1, count($matchedRows)),
                'estimatedCost' => max(1, count($matchedRows) + ($orderSatisfied ? 0 : 20)),
                'detail' => 'SEARCH ' . self::stringValue($index, 'name', self::indexName($sql)) . ' USING PARTIAL EXPRESSION CURRENT SOURCE',
            ];
        }

        usort($plans, static fn (array $left, array $right): int => [
            $left['estimatedCost'],
            $left['name'],
        ] <=> [
            $right['estimatedCost'],
            $right['name'],
        ]);

        return $plans[0] ?? [
            'usable' => false,
            'partialPredicateImplied' => false,
            'currentRowsMaterialized' => false,
            'orderBySatisfied' => false,
            'detail' => 'SCAN TABLE; NO PARTIAL EXPRESSION CURRENT SOURCE INDEX',
        ];
    }

    private static function firstExpression(string $sql): ?SQLiteIndexColumn
    {
        return SQLiteCreateIndex::firstLowerExpression($sql);
    }

    private static function expressionSql(SQLiteIndexColumn $expression): string
    {
        $column = $expression->columnName;
        if (str_starts_with(strtolower($column), 'cast_integer:')) {
            return 'cast_integer(' . substr($column, strlen('cast_integer:')) . ')';
        }

        return 'lower(' . $column . ')';
    }

    /**
     * @return array{operator:string,values:list<mixed>}|null
     */
    private static function expressionConstraint(array $predicate, string $expression): ?array
    {
        foreach (self::flattenAndTerms($predicate) as $term) {
            if (self::predicateExpression($term['left'] ?? null) !== strtolower($expression)) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if ($operator === '=') {
                return ['operator' => '=', 'values' => [self::literal($term['right'] ?? null)]];
            }
            if ($operator === 'IN' && isset($term['values']) && is_array($term['values'])) {
                return ['operator' => 'IN', 'values' => array_map(self::literal(...), array_values($term['values']))];
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{operator:string,values:list<mixed>} $constraint
     * @param array<string,mixed> $predicate
     * @return list<array{rowid:int,key:mixed,covering:array<string,mixed>}>
     */
    private static function matchedRows(array $rows, SQLiteIndexColumn $expression, array $constraint, array $predicate): array
    {
        $matched = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite partial expression current-source rows must be arrays');
            }
            if (!self::rowSatisfiesPredicate($row, $predicate)) {
                continue;
            }
            $key = self::expressionValue($row, $expression);
            if (!self::constraintMatches($key, $constraint, $expression->collation)) {
                continue;
            }
            $matched[] = [
                'rowid' => (int) ($row['rowid'] ?? $row['_rowid_'] ?? 0),
                'key' => $key,
                'covering' => $row,
            ];
        }
        usort($matched, static function (array $left, array $right) use ($expression): int {
            $comparison = self::compare($left['key'], $right['key'], $expression->collation);
            if ($expression->descending) {
                $comparison *= -1;
            }

            return $comparison ?: ($left['rowid'] <=> $right['rowid']);
        });

        return $matched;
    }

    private static function expressionValue(array $row, SQLiteIndexColumn $expression): mixed
    {
        $column = $expression->columnName;
        $value = $row[$column] ?? null;
        return is_string($value) ? strtolower($value) : $value;
    }

    private static function constraintMatches(mixed $key, array $constraint, string $collation): bool
    {
        foreach ($constraint['values'] as $value) {
            if (self::compare($key, $value, $collation) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function rowSatisfiesPredicate(array $row, array $predicate): bool
    {
        $operator = strtoupper((string) ($predicate['operator'] ?? ''));
        if ($operator === 'AND') {
            foreach (self::listTerms($predicate) as $term) {
                if (!self::rowSatisfiesPredicate($row, $term)) {
                    return false;
                }
            }
            return true;
        }
        if ($operator === 'OR') {
            foreach (self::listTerms($predicate) as $term) {
                if (self::rowSatisfiesPredicate($row, $term)) {
                    return true;
                }
            }
            return false;
        }

        $left = $predicate['left'] ?? null;
        $column = is_array($left) && isset($left['column']) ? (string) $left['column'] : null;
        if ($column === null) {
            return true;
        }
        $value = $row[$column] ?? null;
        $right = self::literal($predicate['right'] ?? null);

        return match ($operator) {
            '=' => self::compare($value, $right, 'BINARY') === 0,
            '!=' , '<>' => self::compare($value, $right, 'BINARY') !== 0,
            'IS NOT NULL' => $value !== null,
            default => true,
        };
    }

    private static function partialPredicateImplied(SQLiteIndexPredicate $partial, array $predicate): bool
    {
        if ($partial->operator === SQLiteIndexPredicate::AND) {
            return is_array($partial->value)
                && $partial->value !== []
                && array_reduce(
                    $partial->value,
                    static fn (bool $carry, mixed $sub): bool => $carry
                        && $sub instanceof SQLiteIndexPredicate
                        && self::partialPredicateImplied($sub, $predicate),
                    true
                );
        }
        if ($partial->operator === SQLiteIndexPredicate::OR) {
            return is_array($partial->value)
                && array_reduce(
                    $partial->value,
                    static fn (bool $carry, mixed $sub): bool => $carry
                        || ($sub instanceof SQLiteIndexPredicate && self::partialPredicateImplied($sub, $predicate)),
                    false
                );
        }

        foreach (self::flattenAndTerms($predicate) as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left) || !isset($left['column']) || strcasecmp((string) $left['column'], $partial->columnName) !== 0) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $value = self::literal($term['right'] ?? null);
            if ($partial->operator === SQLiteIndexPredicate::IS_NOT_NULL && $operator === 'IS NOT NULL') {
                return true;
            }
            if ($partial->operator === SQLiteIndexPredicate::EQUALS && $operator === '=' && self::compare($value, $partial->value, 'BINARY') === 0) {
                return true;
            }
            if ($partial->operator === SQLiteIndexPredicate::IN_LIST && $operator === '=' && is_array($partial->value)) {
                foreach ($partial->value as $candidate) {
                    if (self::compare($value, $candidate, 'BINARY') === 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function flattenAndTerms(array $predicate): array
    {
        if (strtoupper((string) ($predicate['operator'] ?? '')) !== 'AND') {
            return [$predicate];
        }
        $terms = [];
        foreach (self::listTerms($predicate) as $term) {
            array_push($terms, ...self::flattenAndTerms($term));
        }

        return $terms;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function listTerms(array $predicate): array
    {
        $terms = $predicate['terms'] ?? [];
        if (!is_array($terms) || !array_is_list($terms)) {
            throw new \InvalidArgumentException('SQLite partial expression current-source predicates need list terms');
        }

        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite partial expression current-source predicate terms must be arrays');
            }
        }

        return $terms;
    }

    private static function predicateExpression(mixed $left): ?string
    {
        if (!is_array($left) || !isset($left['expression'])) {
            return null;
        }

        return strtolower(preg_replace('/\s+/', '', (string) $left['expression']));
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return $left <=> $right;
        }
        $leftString = (string) $left;
        $rightString = (string) $right;
        if (strcasecmp($collation, 'NOCASE') === 0) {
            return strtolower($leftString) <=> strtolower($rightString);
        }

        return $leftString <=> $rightString;
    }

    private static function literal(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('literal', $value) ? $value['literal'] : $value;
    }

    private static function orderSatisfied(array $orderBy, string $expressionSql, string $collation, bool $descending): bool
    {
        if ($orderBy === []) {
            return true;
        }
        $first = $orderBy[0];
        $direction = strtoupper((string) ($first['direction'] ?? 'ASC'));
        $orderCollation = strtoupper((string) ($first['collation'] ?? $collation));

        return strtolower((string) ($first['expression'] ?? '')) === strtolower($expressionSql)
            && $orderCollation === strtoupper($collation)
            && ($descending ? $direction === 'DESC' : $direction === 'ASC');
    }

    private static function validateOrderBy(array $orderBy): void
    {
        foreach ($orderBy as $term) {
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite partial expression current-source ORDER BY direction must be ASC or DESC');
            }
        }
    }

    private static function cursorTape(array $plan, string $source, array $orderBy): array
    {
        return [
            'source' => $source,
            'indexName' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'expression' => $plan['expression'] ?? null,
            'collation' => $plan['collation'] ?? null,
            'scanDirection' => (($plan['descending'] ?? false) === true) ? 'descending' : 'ascending',
            'orderSignature' => self::orderSignature($orderBy),
            'rowids' => $plan['matchedRowids'] ?? [],
            'expressionKeys' => $plan['expressionKeys'] ?? [],
            'program' => [
                ['opcode' => 'OpenRead', 'target' => 'index', 'rootPage' => $plan['rootPage'] ?? null, 'source' => $source],
                ['opcode' => 'RecheckPartialPredicate', 'implied' => ($plan['partialPredicateImplied'] ?? false) === true],
                ['opcode' => 'ExpressionColumn', 'expression' => $plan['expression'] ?? null],
                ['opcode' => (($plan['orderBySatisfied'] ?? false) === true) ? 'Next' : 'SorterOpen'],
            ],
        ];
    }

    /**
     * @param list<array{rowid:int,key:mixed,covering:array<string,mixed>}> $rows
     * @return list<array{current:array<string,mixed>,next:array<string,mixed>|null}>
     */
    private static function currentNextRows(array $rows): array
    {
        $pairs = [];
        foreach ($rows as $offset => $row) {
            $pairs[] = [
                'current' => $row,
                'next' => $rows[$offset + 1] ?? null,
            ];
        }

        return $pairs;
    }

    private static function predicateSummary(SQLiteIndexPredicate $predicate): array
    {
        return [
            'column' => $predicate->columnName,
            'operator' => $predicate->operator,
            'value' => is_array($predicate->value)
                ? array_map(static fn (mixed $item): mixed => $item instanceof SQLiteIndexPredicate ? self::predicateSummary($item) : $item, $predicate->value)
                : $predicate->value,
        ];
    }

    private static function sourceSummary(array $source, array $plan, string $signature): array
    {
        return [
            'name' => self::stringValue($source, 'name', 'source'),
            'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
            'indexSignature' => $signature,
            'ready' => ($plan['usable'] ?? false) === true,
            'indexName' => $plan['name'] ?? null,
            'matchedRowCount' => $plan['matchedRowCount'] ?? 0,
        ];
    }

    private static function sourceSignature(array $source): string
    {
        $parts = [];
        foreach (self::listValue($source, 'indexes') as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite partial expression current-source indexes must be arrays');
            }
            $parts[] = self::stringValue($index, 'name', self::indexName(self::stringValue($index, 'sql')))
                . '#' . self::nonNegativeInt($index, 'rootPage')
                . '#' . sha1(self::stringValue($index, 'sql'));
        }
        sort($parts);

        return sha1(implode('|', $parts));
    }

    private static function orderSignature(array $orderBy): string
    {
        return implode(', ', array_map(static fn (array $term): string => (string) $term['expression'] . ' ' . strtoupper((string) ($term['direction'] ?? 'ASC')) . ' COLLATE ' . strtoupper((string) ($term['collation'] ?? 'BINARY')), $orderBy));
    }

    /**
     * @return list<mixed>
     */
    private static function listValue(array $source, string $key): array
    {
        $value = $source[$key] ?? [];
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("SQLite partial expression current-source {$key} must be a list");
        }

        return $value;
    }

    private static function stringValue(array $source, string $key, ?string $default = null): string
    {
        $value = $source[$key] ?? $default;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite partial expression current-source {$key} must be a non-empty string");
        }

        return $value;
    }

    private static function nonNegativeInt(array $source, string $key): int
    {
        $value = $source[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite partial expression current-source {$key} must be a non-negative integer");
        }

        return $value;
    }

    private static function indexName(string $sql): string
    {
        return preg_match('/CREATE\s+INDEX\s+([A-Za-z_][A-Za-z0-9_]*)/i', $sql, $matches) === 1 ? $matches[1] : 'expression_index';
    }
}
