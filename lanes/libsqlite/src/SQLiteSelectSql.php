<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectSql
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array<string,mixed>>
     */
    public static function execute(string $sql, array $tables): array
    {
        return SQLiteSelectQuery::execute(self::plan($sql, $tables));
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,mixed>
     */
    public static function plan(string $sql, array $tables): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if (!preg_match('/^select\s+/i', $sql)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL must start with SELECT');
        }

        $fromOffset = self::keywordOffset($sql, 'FROM');
        if ($fromOffset === null) {
            throw new \InvalidArgumentException('SQLite SELECT SQL needs FROM');
        }

        $selectSql = trim(substr($sql, 6, $fromOffset - 6));
        $tail = trim(substr($sql, $fromOffset + 4));
        if ($selectSql === '' || $tail === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL needs select list and table');
        }

        $clauseOffsets = self::tailClauseOffsets($tail);
        $tableEnd = self::firstOffset($clauseOffsets) ?? strlen($tail);
        $fromSql = trim(substr($tail, 0, $tableEnd));
        $source = self::sourcePlan($fromSql, $tables);

        $groupBySql = isset($clauseOffsets['GROUP BY'])
            ? self::clauseText($tail, $clauseOffsets, 'GROUP BY')
            : null;
        $select = self::selectList($selectSql);
        $plan = [
            'from' => $source['from'],
            'select' => $select,
        ];
        if ($source['joins'] !== []) {
            $plan['joins'] = $source['joins'];
        }

        if (isset($clauseOffsets['WHERE'])) {
            $plan['where'] = self::predicate(self::clauseText($tail, $clauseOffsets, 'WHERE'));
        }
        if ($groupBySql !== null) {
            $groupBy = self::groupBy($groupBySql, $select);
            if (isset($clauseOffsets['HAVING'])) {
                $groupBy['having'] = self::rewriteAggregatePredicate(
                    self::predicate(self::clauseText($tail, $clauseOffsets, 'HAVING')),
                    $groupBy['valueColumn'],
                );
            }
            $plan['groupBy'] = $groupBy;
            $plan['select'] = self::rewriteAggregateSelect($select, $groupBy['valueColumn']);
        }
        if (isset($clauseOffsets['ORDER BY'])) {
            $plan['orderBy'] = self::orderBy(self::clauseText($tail, $clauseOffsets, 'ORDER BY'));
        }
        if (isset($clauseOffsets['LIMIT'])) {
            [$limit, $offset] = self::limitOffset(self::clauseText($tail, $clauseOffsets, 'LIMIT'));
            $plan['limit'] = $limit;
            $plan['offset'] = $offset;
        }

        return $plan;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{from:list<array<string,mixed>>,joins:list<array<string,mixed>>}
     */
    private static function sourcePlan(string $sql, array $tables): array
    {
        $joinOffset = self::firstJoinOffset($sql);
        $baseSql = $joinOffset === null ? $sql : trim(substr($sql, 0, $joinOffset));
        $base = self::tableReference($baseSql, $tables);
        $joins = [];

        if ($joinOffset !== null) {
            $joinSql = trim(substr($sql, $joinOffset));
            while ($joinSql !== '') {
                [$join, $joinSql] = self::consumeJoin($joinSql, $tables);
                $joins[] = $join;
            }
        }

        return [
            'from' => $joins === [] ? $base['rows'] : self::qualifiedRows($base['rows'], $base['alias']),
            'joins' => $joins,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{name:string,alias:string,rows:list<array<string,mixed>>}
     */
    private static function tableReference(string $sql, array $tables): array
    {
        $jsonTable = self::jsonTableReference($sql);
        if ($jsonTable !== null) {
            return $jsonTable;
        }

        $parts = preg_split('/\s+/', trim($sql));
        if ($parts === false || $parts === [] || $parts[0] === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL table name cannot be empty');
        }
        if (count($parts) > 3 || (isset($parts[1]) && strcasecmp($parts[1], 'AS') === 0 && !isset($parts[2]))) {
            throw new \InvalidArgumentException('SQLite SELECT SQL table reference must be a simple table with optional alias');
        }

        $name = $parts[0];
        self::assertBareIdentifier($name, 'SQLite SELECT SQL table name');
        if (!array_key_exists($name, $tables) || !is_array($tables[$name]) || !array_is_list($tables[$name])) {
            throw new \InvalidArgumentException("SQLite SELECT SQL table {$name} is not available");
        }

        $alias = $name;
        if (isset($parts[1])) {
            if (strcasecmp($parts[1], 'AS') === 0) {
                $alias = $parts[2];
            } else {
                $alias = $parts[1];
            }
            self::assertBareIdentifier($alias, 'SQLite SELECT SQL table alias');
        }

        return ['name' => $name, 'alias' => $alias, 'rows' => $tables[$name]];
    }

    /**
     * @return array{name:string,alias:string,rows:list<array<string,mixed>>}|null
     */
    private static function jsonTableReference(string $sql): ?array
    {
        if (preg_match('/^(json_each|json_tree)\s*\((.*)\)(?:\s+(?:AS\s+)?([A-Za-z_][A-Za-z0-9_]*))?$/i', trim($sql), $match) !== 1) {
            return null;
        }

        $function = strtolower($match[1]);
        $argumentSql = trim($match[2]);
        if ($argumentSql === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL JSON table source needs a JSON argument');
        }

        $argumentExpressions = array_map(self::valueExpression(...), self::splitTopLevel($argumentSql, ','));
        if (count($argumentExpressions) < 1 || count($argumentExpressions) > 2) {
            throw new \InvalidArgumentException('SQLite SELECT SQL JSON table source supports one or two arguments');
        }

        $constraints = [
            ['column' => 'json', 'operator' => '=', 'value' => self::literalExpressionValue($argumentExpressions[0], 'JSON table source')],
        ];
        if (isset($argumentExpressions[1])) {
            $constraints[] = [
                'column' => 'root',
                'operator' => '=',
                'value' => self::literalExpressionValue($argumentExpressions[1], 'JSON table root'),
            ];
        }

        $alias = isset($match[3]) && $match[3] !== '' ? $match[3] : $function;
        self::assertBareIdentifier($alias, 'SQLite SELECT SQL JSON table alias');

        return [
            'name' => $function,
            'alias' => $alias,
            'rows' => SQLiteJsonTablePlan::visibleRows($function, $constraints),
        ];
    }

    private static function literalExpressionValue(array $expression, string $context): mixed
    {
        if (($expression['type'] ?? null) !== 'literal' || !array_key_exists('value', $expression)) {
            throw new \InvalidArgumentException("SQLite SELECT SQL {$context} argument must be a literal");
        }

        return $expression['value'];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function qualifiedRows(array $rows, string $prefix): array
    {
        $qualified = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL table rows must be arrays');
            }
            $qualifiedRow = [];
            foreach ($row as $column => $value) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite SELECT SQL table rows must have named columns');
                }
                $qualifiedRow[str_contains($column, '.') ? $column : $prefix . '.' . $column] = $value;
            }
            $qualified[] = $qualifiedRow;
        }

        return $qualified;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{0:array<string,mixed>,1:string}
     */
    private static function consumeJoin(string $sql, array $tables): array
    {
        if (preg_match('/^(left\s+join|inner\s+join|cross\s+join|join)\s+/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL JOIN clause is not supported');
        }

        $keyword = strtoupper(preg_replace('/\s+/', ' ', $match[1]));
        $type = match ($keyword) {
            'JOIN', 'INNER JOIN' => 'INNER',
            'LEFT JOIN' => 'LEFT',
            'CROSS JOIN' => 'CROSS',
            default => throw new \InvalidArgumentException('SQLite SELECT SQL JOIN type is not supported'),
        };

        $rest = trim(substr($sql, strlen($match[0])));
        $boundary = self::nextJoinConditionOffset($rest);
        if ($boundary === null && $type === 'CROSS') {
            $nextJoin = self::firstJoinOffset($rest);
            $tableSql = $nextJoin === null ? $rest : trim(substr($rest, 0, $nextJoin));
            $remaining = $nextJoin === null ? '' : trim(substr($rest, $nextJoin));
            $table = self::tableReference($tableSql, $tables);

            return [[
                'type' => 'CROSS',
                'rows' => self::qualifiedRows($table['rows'], $table['alias']),
            ], $remaining];
        }
        if ($boundary === null) {
            throw new \InvalidArgumentException('SQLite SELECT SQL JOIN needs ON or USING');
        }

        $table = self::tableReference(trim(substr($rest, 0, $boundary)), $tables);
        $conditionSql = trim(substr($rest, $boundary));
        $nextJoin = self::nextJoinAfterCondition($conditionSql);
        $condition = $nextJoin === null ? $conditionSql : trim(substr($conditionSql, 0, $nextJoin));
        $remaining = $nextJoin === null ? '' : trim(substr($conditionSql, $nextJoin));

        $join = [
            'type' => $type,
            'rows' => self::qualifiedRows($table['rows'], $table['alias']),
        ];

        if (preg_match('/^using\s*\((.*)\)$/i', $condition, $using) === 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL JOIN USING is not supported for qualified SQL text rows');
        }

        if (preg_match('/^on\s+(.+)$/i', $condition, $on) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL JOIN needs ON or USING');
        }
        if ($type === 'CROSS') {
            throw new \InvalidArgumentException('SQLite SELECT SQL CROSS JOIN does not support ON');
        }

        $predicate = self::predicate($on[1]);
        $join['predicate'] = static function (array $left, array $right) use ($predicate): bool {
            return SQLiteSelectPredicate::filter([array_merge($left, $right)], $predicate) !== [];
        };
        if ($type === 'LEFT') {
            $join['rightColumns'] = self::collectColumns($join['rows']);
            if ($join['rightColumns'] === [] && ($table['name'] === 'json_each' || $table['name'] === 'json_tree')) {
                $join['rightColumns'] = self::qualifiedJsonTableColumns($table['alias']);
            }
        }

        return [$join, $remaining];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function collectColumns(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach ($row as $column => $unused) {
                if (!in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    private static function qualifiedJsonTableColumns(string $alias): array
    {
        return array_map(
            static fn (string $column): string => $alias . '.' . $column,
            ['key', 'value', 'type', 'atom', 'id', 'parent', 'fullkey', 'path'],
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function selectList(string $sql): array
    {
        $items = self::splitTopLevel($sql, ',');
        if ($items === []) {
            throw new \InvalidArgumentException('SQLite SELECT SQL projection needs at least one expression');
        }

        $expressions = [];
        foreach ($items as $item) {
            [$expression, $alias] = self::expressionAlias($item);
            if ($expression === '*') {
                $term = ['type' => 'wildcard'];
            } elseif (str_ends_with($expression, '.*')) {
                $prefix = substr($expression, 0, -2);
                self::assertIdentifier($prefix, 'SQLite SELECT SQL wildcard prefix');
                $term = ['type' => 'wildcard', 'prefix' => $prefix];
            } else {
                $term = self::valueExpression($expression);
                if ($alias !== null) {
                    $term['alias'] = $alias;
                }
            }
            $expressions[] = $term;
        }

        return $expressions;
    }

    /**
     * @return array{0:string,1:?string}
     */
    private static function expressionAlias(string $item): array
    {
        $item = trim($item);
        if ($item === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL projection expression cannot be empty');
        }
        $as = self::keywordOffset($item, 'AS');
        if ($as === null) {
            return [$item, null];
        }

        $expression = trim(substr($item, 0, $as));
        $alias = trim(substr($item, $as + 2));
        self::assertIdentifier($alias, 'SQLite SELECT SQL projection alias');

        return [$expression, $alias];
    }

    /**
     * @return array<string,mixed>
     */
    private static function predicate(string $sql): array
    {
        $orTerms = self::splitKeyword($sql, 'OR');
        if (count($orTerms) > 1) {
            return ['operator' => 'OR', 'terms' => array_map(self::predicate(...), $orTerms)];
        }

        $andTerms = self::splitKeyword($sql, 'AND');
        if (count($andTerms) > 1) {
            return ['operator' => 'AND', 'terms' => array_map(self::predicate(...), $andTerms)];
        }

        $sql = trim($sql);
        foreach (['NOT LIKE', 'LIKE', '>=', '<=', '<>', '!=', '=', '>', '<'] as $operator) {
            $offset = self::operatorOffset($sql, $operator);
            if ($offset === null) {
                continue;
            }
            $left = trim(substr($sql, 0, $offset));
            $right = trim(substr($sql, $offset + strlen($operator)));
            if ($left === '' || $right === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL predicate needs both operands');
            }

            return [
                'operator' => $operator,
                'left' => self::valueExpression($left),
                'right' => self::valueExpression($right),
            ];
        }

        if (preg_match('/^(.+?)\s+(not\s+)?in\s*\((.*)\)$/i', $sql, $match) === 1) {
            return [
                'operator' => isset($match[2]) && trim($match[2]) !== '' ? 'NOT IN' : 'IN',
                'left' => self::valueExpression(trim($match[1])),
                'values' => array_map(self::valueExpression(...), self::splitTopLevel($match[3], ',')),
            ];
        }

        if (preg_match('/^(.+?)\s+is\s+(not\s+)?null$/i', $sql, $match) === 1) {
            return [
                'operator' => isset($match[2]) && trim($match[2]) !== '' ? 'IS NOT NULL' : 'IS NULL',
                'left' => self::valueExpression(trim($match[1])),
            ];
        }

        throw new \InvalidArgumentException('SQLite SELECT SQL predicate is not supported');
    }

    /**
     * @return array<string,mixed>
     */
    private static function valueExpression(string $sql): array
    {
        $sql = trim($sql);
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\((.*)\)$/', $sql, $match) === 1) {
            if (trim($match[2]) === '*') {
                $arguments = [['type' => 'wildcard']];
            } else {
                $arguments = trim($match[2]) === '' ? [] : array_map(self::valueExpression(...), self::splitTopLevel($match[2], ','));
            }

            return ['type' => 'function', 'name' => $match[1], 'arguments' => $arguments];
        }
        if (preg_match('/^[+-]?[0-9]+$/', $sql) === 1) {
            return ['type' => 'literal', 'value' => (int) $sql];
        }
        if (preg_match('/^[+-]?(?:[0-9]+\.[0-9]*|\.[0-9]+)$/', $sql) === 1) {
            return ['type' => 'literal', 'value' => (float) $sql];
        }
        if (strcasecmp($sql, 'NULL') === 0) {
            return ['type' => 'literal', 'value' => null];
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/', $sql) === 1) {
            return ['type' => 'column', 'name' => $sql];
        }
        if (str_starts_with($sql, "'") && str_ends_with($sql, "'")) {
            return ['type' => 'literal', 'value' => str_replace("''", "'", substr($sql, 1, -1))];
        }

        throw new \InvalidArgumentException("SQLite SELECT SQL expression {$sql} is not supported");
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return array<string,mixed>
     */
    private static function groupBy(string $sql, array $select): array
    {
        $columns = [];
        foreach (self::splitTopLevel($sql, ',') as $term) {
            self::assertIdentifier($term, 'SQLite SELECT SQL GROUP BY column');
            $columns[] = $term;
        }
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite SELECT SQL GROUP BY needs at least one column');
        }

        return [
            'columns' => $columns,
            'valueColumn' => self::aggregateValueColumn($select),
        ];
    }

    /**
     * @param list<array<string,mixed>> $select
     */
    private static function aggregateValueColumn(array $select): string
    {
        $valueColumn = null;
        foreach ($select as $term) {
            $aggregate = self::aggregateSummaryColumn($term, null);
            if ($aggregate === null || $aggregate['valueColumn'] === null) {
                continue;
            }
            if ($valueColumn !== null && $valueColumn !== $aggregate['valueColumn']) {
                throw new \InvalidArgumentException('SQLite SELECT SQL GROUP BY supports one aggregate value column');
            }
            $valueColumn = $aggregate['valueColumn'];
        }
        if ($valueColumn === null) {
            throw new \InvalidArgumentException('SQLite SELECT SQL GROUP BY needs an aggregate value column');
        }

        return $valueColumn;
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return list<array<string,mixed>>
     */
    private static function rewriteAggregateSelect(array $select, string $valueColumn): array
    {
        $rewritten = [];
        foreach ($select as $term) {
            $aggregate = self::aggregateSummaryColumn($term, $valueColumn);
            if ($aggregate !== null) {
                $rewritten[] = [
                    'type' => 'column',
                    'name' => $aggregate['summaryColumn'],
                    'alias' => $term['alias'] ?? $aggregate['summaryColumn'],
                ];
                continue;
            }
            $rewritten[] = $term;
        }

        return $rewritten;
    }

    /**
     * @return array{summaryColumn:string,valueColumn:?string}|null
     */
    private static function aggregateSummaryColumn(array $term, ?string $requiredValueColumn): ?array
    {
        if (($term['type'] ?? null) !== 'function' || !isset($term['name']) || !is_string($term['name'])) {
            return null;
        }
        $name = strtolower($term['name']);
        $arguments = $term['arguments'] ?? [];
        if (!is_array($arguments) || !array_is_list($arguments)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL aggregate arguments must be a list');
        }

        if ($name === 'count' && count($arguments) === 1 && (($arguments[0]['type'] ?? null) === 'wildcard')) {
            return ['summaryColumn' => 'countAll', 'valueColumn' => null];
        }

        $summaryColumn = match ($name) {
            'count' => 'countValue',
            'sum' => 'sum',
            'total' => 'total',
            'avg' => 'avg',
            'min' => 'min',
            'max' => 'max',
            'group_concat' => 'groupConcat',
            default => null,
        };
        if ($summaryColumn === null) {
            return null;
        }
        if (count($arguments) !== 1 || (($arguments[0]['type'] ?? null) !== 'column') || !isset($arguments[0]['name']) || !is_string($arguments[0]['name'])) {
            throw new \InvalidArgumentException("SQLite SELECT SQL aggregate {$name} needs one column argument");
        }
        if ($requiredValueColumn !== null && $arguments[0]['name'] !== $requiredValueColumn) {
            throw new \InvalidArgumentException('SQLite SELECT SQL GROUP BY aggregate column does not match value column');
        }

        return ['summaryColumn' => $summaryColumn, 'valueColumn' => $arguments[0]['name']];
    }

    /**
     * @param array<string,mixed> $predicate
     * @return array<string,mixed>
     */
    private static function rewriteAggregatePredicate(array $predicate, string $valueColumn): array
    {
        if (isset($predicate['terms']) && is_array($predicate['terms']) && array_is_list($predicate['terms'])) {
            $predicate['terms'] = array_map(
                static fn (array $term): array => self::rewriteAggregatePredicate($term, $valueColumn),
                $predicate['terms'],
            );

            return $predicate;
        }
        foreach (['left', 'right'] as $side) {
            if (isset($predicate[$side]) && is_array($predicate[$side])) {
                $predicate[$side] = self::rewriteAggregateExpression($predicate[$side], $valueColumn);
            }
        }

        return $predicate;
    }

    /**
     * @param array<string,mixed> $expression
     * @return array<string,mixed>
     */
    private static function rewriteAggregateExpression(array $expression, string $valueColumn): array
    {
        $aggregate = self::aggregateSummaryColumn($expression, $valueColumn);
        if ($aggregate === null) {
            return $expression;
        }

        return ['type' => 'column', 'name' => $aggregate['summaryColumn']];
    }

    /**
     * @return list<array{column:string,direction?:string}>
     */
    private static function orderBy(string $sql): array
    {
        $terms = [];
        foreach (self::splitTopLevel($sql, ',') as $term) {
            $parts = preg_split('/\s+/', trim($term));
            if ($parts === false || $parts === [] || $parts[0] === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY term cannot be empty');
            }
            self::assertIdentifier($parts[0], 'SQLite SELECT SQL ORDER BY column');
            $order = ['column' => $parts[0]];
            if (isset($parts[1])) {
                $direction = strtoupper($parts[1]);
                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY direction must be ASC or DESC');
                }
                $order['direction'] = $direction;
            }
            if (isset($parts[2])) {
                throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY supports one direction token');
            }
            $terms[] = $order;
        }

        return $terms;
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function limitOffset(string $sql): array
    {
        if (preg_match('/^([+-]?[0-9]+)(?:\s+offset\s+([+-]?[0-9]+))?$/i', trim($sql), $match) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL LIMIT must be integer with optional OFFSET');
        }

        return [(int) $match[1], isset($match[2]) ? (int) $match[2] : 0];
    }

    /**
     * @return array<string,int>
     */
    private static function tailClauseOffsets(string $sql): array
    {
        $offsets = [];
        foreach (['WHERE', 'GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT'] as $keyword) {
            $offset = self::keywordOffset($sql, $keyword);
            if ($offset !== null) {
                $offsets[$keyword] = $offset;
            }
        }
        asort($offsets);

        return $offsets;
    }

    /**
     * @param array<string,int> $offsets
     */
    private static function clauseText(string $tail, array $offsets, string $keyword): string
    {
        $start = $offsets[$keyword] + strlen($keyword);
        $end = strlen($tail);
        foreach ($offsets as $other => $offset) {
            if ($offset > $offsets[$keyword]) {
                $end = $offset;
                break;
            }
        }

        $text = trim(substr($tail, $start, $end - $start));
        if ($text === '') {
            throw new \InvalidArgumentException("SQLite SELECT SQL {$keyword} clause cannot be empty");
        }

        return $text;
    }

    /**
     * @param array<string,int> $offsets
     */
    private static function firstOffset(array $offsets): ?int
    {
        return $offsets === [] ? null : min($offsets);
    }

    /**
     * @return list<string>
     */
    private static function splitKeyword(string $sql, string $keyword): array
    {
        $parts = self::splitTopLevelByKeyword($sql, $keyword);
        return count($parts) === 1 ? [trim($sql)] : $parts;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevelByKeyword(string $sql, string $keyword): array
    {
        $parts = [];
        $start = 0;
        $length = strlen($sql);
        $depth = 0;
        $quote = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($quote && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && strncasecmp(substr($sql, $i), $keyword, strlen($keyword)) === 0 && self::keywordBounded($sql, $i, strlen($keyword))) {
                $parts[] = trim(substr($sql, $start, $i - $start));
                $start = $i + strlen($keyword);
                $i = $start - 1;
            }
        }
        $parts[] = trim(substr($sql, $start));

        return $parts;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $sql, string $separator): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($quote && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && $char === $separator) {
                $parts[] = trim(substr($sql, $start, $i - $start));
                $start = $i + 1;
            }
        }
        $parts[] = trim(substr($sql, $start));
        if (in_array('', $parts, true)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL list contains an empty item');
        }

        return $parts;
    }

    private static function keywordOffset(string $sql, string $keyword): ?int
    {
        $length = strlen($sql);
        $depth = 0;
        $quote = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($quote && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && strncasecmp(substr($sql, $i), $keyword, strlen($keyword)) === 0 && self::keywordBounded($sql, $i, strlen($keyword))) {
                return $i;
            }
        }

        return null;
    }

    private static function operatorOffset(string $sql, string $operator): ?int
    {
        $length = strlen($sql);
        $depth = 0;
        $quote = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($quote && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && strncasecmp(substr($sql, $i), $operator, strlen($operator)) === 0) {
                if (ctype_alpha($operator[0]) && !self::keywordBounded($sql, $i, strlen($operator))) {
                    continue;
                }

                return $i;
            }
        }

        return null;
    }

    private static function keywordBounded(string $sql, int $offset, int $length): bool
    {
        $before = $offset === 0 ? ' ' : $sql[$offset - 1];
        $after = $sql[$offset + $length] ?? ' ';

        return !preg_match('/[A-Za-z0-9_]/', $before) && !preg_match('/[A-Za-z0-9_]/', $after);
    }

    private static function assertIdentifier(string $value, string $context): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/', $value) !== 1) {
            throw new \InvalidArgumentException("{$context} must be a simple identifier");
        }
    }

    private static function assertBareIdentifier(string $value, string $context): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("{$context} must be a simple identifier");
        }
    }

    private static function firstJoinOffset(string $sql): ?int
    {
        $offsets = [];
        foreach (['LEFT JOIN', 'INNER JOIN', 'CROSS JOIN', 'JOIN'] as $keyword) {
            $offset = self::keywordOffset($sql, $keyword);
            if ($offset !== null) {
                $offsets[] = $offset;
            }
        }

        return $offsets === [] ? null : min($offsets);
    }

    private static function nextJoinConditionOffset(string $sql): ?int
    {
        $offsets = [];
        foreach (['ON', 'USING'] as $keyword) {
            $offset = self::keywordOffset($sql, $keyword);
            if ($offset !== null) {
                $offsets[] = $offset;
            }
        }

        return $offsets === [] ? null : min($offsets);
    }

    private static function nextJoinAfterCondition(string $sql): ?int
    {
        return self::firstJoinOffset($sql);
    }
}
