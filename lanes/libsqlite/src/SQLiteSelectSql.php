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
        $plan = self::plan($sql, $tables);
        if (isset($plan['compound']) && is_array($plan['compound'])) {
            return self::executeCompoundPlan($plan);
        }

        $rows = SQLiteSelectQuery::execute($plan);

        return self::stripHiddenOrderColumns($rows, $plan);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,mixed>
     */
    public static function plan(string $sql, array $tables): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if (preg_match('/^with\s+/i', $sql) === 1) {
            [$tables, $sql, $cteNames] = self::materializeWithTables($sql, $tables);
        } else {
            $cteNames = [];
        }
        if (!preg_match('/^select\s+/i', $sql)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL must start with SELECT');
        }

        $compound = self::compoundSqlPlan($sql, $tables, $cteNames);
        if ($compound !== null) {
            return $compound;
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
        $where = isset($clauseOffsets['WHERE'])
            ? self::predicate(self::clauseText($tail, $clauseOffsets, 'WHERE'), $tables)
            : null;
        $jsonConstraints = self::jsonTableHiddenConstraints($fromSql, $where);
        $source = self::sourcePlan($fromSql, $tables, $jsonConstraints);

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

        if ($where !== null) {
            $where = self::removeJsonTableHiddenConstraints($fromSql, $where);
            if ($where !== null) {
                $plan['where'] = $where;
            }
        }
        if ($groupBySql !== null) {
            $groupBy = self::groupBy($groupBySql, $select);
            if (isset($clauseOffsets['HAVING'])) {
                $groupBy['having'] = self::rewriteAggregatePredicate(
                    self::predicate(self::clauseText($tail, $clauseOffsets, 'HAVING'), $tables),
                    $groupBy['valueColumn'],
                );
            }
            $plan['groupBy'] = $groupBy;
            $plan['select'] = self::rewriteAggregateSelect($select, $groupBy['valueColumn']);
        }
        if (isset($clauseOffsets['ORDER BY'])) {
            $plan['orderBy'] = self::orderBy(
                self::clauseText($tail, $clauseOffsets, 'ORDER BY'),
                $plan['select'],
                isset($plan['groupBy']) ? $plan['groupBy']['valueColumn'] : null,
            );
        }
        if (isset($clauseOffsets['LIMIT'])) {
            [$limit, $offset] = self::limitOffset(self::clauseText($tail, $clauseOffsets, 'LIMIT'));
            $plan['limit'] = $limit;
            $plan['offset'] = $offset;
        }
        if ($cteNames !== []) {
            $plan['with'] = $cteNames;
        }

        return $plan;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $cteNames
     * @return array<string,mixed>|null
     */
    private static function compoundSqlPlan(string $sql, array $tables, array $cteNames): ?array
    {
        $parts = self::splitCompoundSql($sql);
        if ($parts === null) {
            return null;
        }

        $lastIndex = count($parts['arms']) - 1;
        [$lastSql, $orderSql, $limitSql] = self::stripCompoundTailClauses($parts['arms'][$lastIndex]);
        $parts['arms'][$lastIndex] = $lastSql;

        $arms = [];
        foreach ($parts['arms'] as $armSql) {
            if (self::splitCompoundSql($armSql) !== null) {
                throw new \InvalidArgumentException('SQLite SELECT SQL compound arms cannot contain nested compound SELECT text');
            }
            $arms[] = self::plan($armSql, $tables);
        }

        $plan = [
            'compound' => [
                'operators' => $parts['operators'],
                'arms' => $arms,
            ],
        ];
        if ($orderSql !== null) {
            $plan['compound']['orderBy'] = self::compoundOrderBy($orderSql, $arms[0]['select'] ?? []);
        }
        if ($limitSql !== null) {
            [$limit, $offset] = self::limitOffset($limitSql);
            $plan['compound']['limit'] = $limit;
            $plan['compound']['offset'] = $offset;
        }
        if ($cteNames !== []) {
            $plan['with'] = $cteNames;
        }

        return $plan;
    }

    /**
     * @return array{arms:list<string>,operators:list<string>}|null
     */
    private static function splitCompoundSql(string $sql): ?array
    {
        $arms = [];
        $operators = [];
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
            if ($depth !== 0) {
                continue;
            }

            foreach (['UNION ALL', 'UNION', 'INTERSECT', 'EXCEPT'] as $operator) {
                if (strncasecmp(substr($sql, $i), $operator, strlen($operator)) !== 0 || !self::keywordBounded($sql, $i, strlen($operator))) {
                    continue;
                }
                $arm = trim(substr($sql, $start, $i - $start));
                if ($arm === '') {
                    throw new \InvalidArgumentException('SQLite SELECT SQL compound arm cannot be empty');
                }
                $arms[] = $arm;
                $operators[] = $operator;
                $start = $i + strlen($operator);
                $i = $start - 1;
                continue 2;
            }
        }
        if ($operators === []) {
            return null;
        }

        $finalArm = trim(substr($sql, $start));
        if ($finalArm === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL compound arm cannot be empty');
        }
        $arms[] = $finalArm;

        return ['arms' => $arms, 'operators' => $operators];
    }

    /**
     * @return array{0:string,1:?string,2:?string}
     */
    private static function stripCompoundTailClauses(string $sql): array
    {
        $orderOffset = self::keywordOffset($sql, 'ORDER BY');
        $limitOffset = self::keywordOffset($sql, 'LIMIT');
        $cut = null;
        if ($orderOffset !== null) {
            $cut = $orderOffset;
        }
        if ($limitOffset !== null && ($cut === null || $limitOffset < $cut)) {
            $cut = $limitOffset;
        }
        if ($cut === null) {
            return [$sql, null, null];
        }

        $tail = trim(substr($sql, $cut));
        $arm = trim(substr($sql, 0, $cut));
        if ($arm === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL compound final arm cannot be empty');
        }
        $offsets = [];
        foreach (['ORDER BY', 'LIMIT'] as $keyword) {
            $offset = self::keywordOffset($tail, $keyword);
            if ($offset !== null) {
                $offsets[$keyword] = $offset;
            }
        }
        asort($offsets);

        return [
            $arm,
            isset($offsets['ORDER BY']) ? self::clauseText($tail, $offsets, 'ORDER BY') : null,
            isset($offsets['LIMIT']) ? self::clauseText($tail, $offsets, 'LIMIT') : null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return list<array{column:string,direction?:string}>
     */
    private static function compoundOrderBy(string $sql, array $select): array
    {
        $columns = [];
        foreach ($select as $index => $term) {
            if (isset($term['alias']) && is_string($term['alias'])) {
                $columns[$index + 1] = $term['alias'];
                continue;
            }
            if (($term['type'] ?? null) === 'column' && isset($term['name']) && is_string($term['name'])) {
                $name = $term['name'];
                $columns[$index + 1] = str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name;
            }
        }

        $orderBy = [];
        foreach (self::splitTopLevel($sql, ',') as $term) {
            [$expression, $direction] = self::orderByExpressionDirection($term);
            if (preg_match('/^[1-9][0-9]*$/', $expression) === 1) {
                $ordinal = (int) $expression;
                if (!isset($columns[$ordinal])) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL compound ORDER BY ordinal is out of range');
                }
                $column = $columns[$ordinal];
            } else {
                self::assertIdentifier($expression, 'SQLite SELECT SQL compound ORDER BY column');
                $column = $expression;
            }

            $entry = ['column' => $column];
            if ($direction !== null) {
                $entry['direction'] = $direction;
            }
            $orderBy[] = $entry;
        }

        return $orderBy;
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    private static function executeCompoundPlan(array $plan): array
    {
        $compound = $plan['compound'];
        if (!is_array($compound) || !isset($compound['arms'], $compound['operators']) || !is_array($compound['arms']) || !is_array($compound['operators'])) {
            throw new \InvalidArgumentException('SQLite SELECT SQL compound plan is malformed');
        }
        if (count($compound['arms']) !== count($compound['operators']) + 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL compound plan arm count is malformed');
        }

        $rows = null;
        foreach ($compound['arms'] as $index => $arm) {
            if (!is_array($arm)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL compound arm plan is malformed');
            }
            $armRows = self::stripHiddenOrderColumns(SQLiteSelectQuery::execute($arm), $arm);
            if ($rows === null) {
                $rows = $armRows;
                continue;
            }
            $rows = SQLiteSelectCompound::combine($rows, $armRows, (string) $compound['operators'][$index - 1]);
        }

        return SQLiteSelectResult::execute(
            $rows ?? [],
            null,
            isset($compound['orderBy']) && is_array($compound['orderBy']) ? $compound['orderBy'] : [],
            isset($compound['limit']) && is_int($compound['limit']) ? $compound['limit'] : null,
            isset($compound['offset']) && is_int($compound['offset']) ? $compound['offset'] : 0,
        );
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{0:array<string,list<array<string,mixed>>>,1:string,2:list<string>}
     */
    private static function materializeWithTables(string $sql, array $tables): array
    {
        [$entries, $mainSql, $recursive] = self::withEntries($sql);
        if ($recursive) {
            throw new \InvalidArgumentException('SQLite SELECT SQL recursive CTEs are not supported');
        }

        $cteNames = [];
        foreach ($entries as $entry) {
            $name = $entry['name'];
            if (array_key_exists($name, $tables)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL CTE {$name} shadows an input table");
            }
            $rows = self::execute($entry['sql'], $tables);
            if ($entry['columns'] !== []) {
                $rows = self::renameCteColumns($rows, $entry['columns'], $name);
            }
            $tables[$name] = $rows;
            $cteNames[] = $name;
        }

        return [$tables, $mainSql, $cteNames];
    }

    /**
     * @return array{0:list<array{name:string,columns:list<string>,sql:string}>,1:string,2:bool}
     */
    private static function withEntries(string $sql): array
    {
        if (preg_match('/^with\s+(recursive\s+)?/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL WITH clause is malformed');
        }

        $recursive = isset($match[1]) && trim($match[1]) !== '';
        $offset = strlen($match[0]);
        $entries = [];
        $length = strlen($sql);
        while (true) {
            $offset = self::skipWhitespace($sql, $offset);
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)/', substr($sql, $offset), $nameMatch) !== 1) {
                throw new \InvalidArgumentException('SQLite SELECT SQL CTE name is malformed');
            }
            $name = $nameMatch[1];
            $offset += strlen($name);
            $offset = self::skipWhitespace($sql, $offset);

            $columns = [];
            if (($sql[$offset] ?? null) === '(') {
                [$columnSql, $offset] = self::consumeParenthesized($sql, $offset);
                foreach (self::splitTopLevel($columnSql, ',') as $column) {
                    self::assertBareIdentifier($column, 'SQLite SELECT SQL CTE column');
                    $columns[] = $column;
                }
                if ($columns === []) {
                    throw new \InvalidArgumentException("SQLite SELECT SQL CTE {$name} column list cannot be empty");
                }
                $offset = self::skipWhitespace($sql, $offset);
            }

            if (!self::keywordAt($sql, $offset, 'AS')) {
                throw new \InvalidArgumentException("SQLite SELECT SQL CTE {$name} needs AS");
            }
            $offset += 2;
            $offset = self::skipWhitespace($sql, $offset);
            if (($sql[$offset] ?? null) !== '(') {
                throw new \InvalidArgumentException("SQLite SELECT SQL CTE {$name} needs a parenthesized SELECT");
            }
            [$cteSql, $offset] = self::consumeParenthesized($sql, $offset);
            $cteSql = trim($cteSql);
            if (!preg_match('/^select\s+/i', $cteSql)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL CTE {$name} body must be SELECT");
            }
            $entries[] = ['name' => $name, 'columns' => $columns, 'sql' => $cteSql];

            $offset = self::skipWhitespace($sql, $offset);
            if (($sql[$offset] ?? null) === ',') {
                $offset++;
                continue;
            }

            $mainSql = trim(substr($sql, $offset));
            if (!preg_match('/^select\s+/i', $mainSql)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL WITH clause needs a trailing SELECT');
            }

            return [$entries, $mainSql, $recursive];
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function renameCteColumns(array $rows, array $columns, string $name): array
    {
        $renamed = [];
        foreach ($rows as $row) {
            if (count($row) !== count($columns)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL CTE {$name} column list does not match SELECT width");
            }
            $renamed[] = array_combine($columns, array_values($row));
        }

        return $renamed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $jsonConstraints
     * @return array{from:list<array<string,mixed>>,joins:list<array<string,mixed>>}
     */
    private static function sourcePlan(string $sql, array $tables, array $jsonConstraints = []): array
    {
        $joinOffset = self::firstJoinOffset($sql);
        $baseSql = $joinOffset === null ? $sql : trim(substr($sql, 0, $joinOffset));
        $base = self::tableReference($baseSql, $tables, $jsonConstraints);
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
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $jsonConstraints
     * @return array{name:string,alias:string,rows:list<array<string,mixed>>}
     */
    private static function tableReference(string $sql, array $tables, array $jsonConstraints = []): array
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
        if (strcasecmp($name, 'json_each') === 0 || strcasecmp($name, 'json_tree') === 0) {
            $function = strtolower($name);
            $alias = $function;
            if (isset($parts[1])) {
                if (strcasecmp($parts[1], 'AS') === 0) {
                    $alias = $parts[2];
                } else {
                    $alias = $parts[1];
                }
                self::assertBareIdentifier($alias, 'SQLite SELECT SQL JSON table alias');
            }

            return [
                'name' => $function,
                'alias' => $alias,
                'rows' => SQLiteJsonTablePlan::visibleRows($function, $jsonConstraints),
            ];
        }
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

        $alias = isset($match[3]) && $match[3] !== '' ? $match[3] : $function;
        self::assertBareIdentifier($alias, 'SQLite SELECT SQL JSON table alias');

        $hasDynamicArguments = false;
        foreach ($argumentExpressions as $expression) {
            if (($expression['type'] ?? null) !== 'literal') {
                $hasDynamicArguments = true;
                break;
            }
        }

        if ($hasDynamicArguments) {
            return [
                'name' => $function,
                'alias' => $alias,
                'rows' => [],
                'dynamicRows' => static function (array $row) use ($function, $alias, $argumentExpressions): array {
                    $constraints = [
                        [
                            'column' => 'json',
                            'operator' => '=',
                            'value' => SQLiteSelectExpression::evaluate($row, $argumentExpressions[0]),
                        ],
                    ];
                    if (isset($argumentExpressions[1])) {
                        $constraints[] = [
                            'column' => 'root',
                            'operator' => '=',
                            'value' => SQLiteSelectExpression::evaluate($row, $argumentExpressions[1]),
                        ];
                    }

                    $plan = SQLiteJsonTablePlan::validatedPlan($function, $constraints);
                    if (!$plan['runnable']) {
                        return [];
                    }

                    return self::qualifiedRows(SQLiteJsonTablePlan::visibleRows($function, $constraints), $alias);
                },
            ];
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
     * @return list<array{column:string,operator:string,value:mixed,usable?:bool}>
     */
    private static function jsonTableHiddenConstraints(string $fromSql, ?array $where): array
    {
        if ($where === null || self::bareJsonTableAlias($fromSql) === null) {
            return [];
        }

        $constraints = [];
        foreach (self::flattenAndPredicate($where) as $term) {
            $constraint = self::jsonTableHiddenConstraint($term);
            if ($constraint !== null) {
                $constraints[] = $constraint;
            }
        }

        return $constraints;
    }

    private static function removeJsonTableHiddenConstraints(string $fromSql, array $where): ?array
    {
        if (self::bareJsonTableAlias($fromSql) === null) {
            return $where;
        }

        if (($where['operator'] ?? null) === 'AND' && isset($where['terms']) && is_array($where['terms'])) {
            $terms = [];
            foreach ($where['terms'] as $term) {
                if (is_array($term) && self::jsonTableHiddenConstraint($term) !== null) {
                    continue;
                }
                $terms[] = $term;
            }
            if ($terms === []) {
                return null;
            }
            if (count($terms) === 1 && is_array($terms[0])) {
                return $terms[0];
            }

            return ['operator' => 'AND', 'terms' => $terms];
        }

        return self::jsonTableHiddenConstraint($where) === null ? $where : null;
    }

    private static function bareJsonTableAlias(string $fromSql): ?string
    {
        if (self::firstJoinOffset($fromSql) !== null) {
            return null;
        }
        if (preg_match('/^(json_each|json_tree)(?:\s+(?:AS\s+)?([A-Za-z_][A-Za-z0-9_]*))?$/i', trim($fromSql), $match) !== 1) {
            return null;
        }

        return isset($match[2]) && $match[2] !== '' ? $match[2] : strtolower($match[1]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function flattenAndPredicate(array $predicate): array
    {
        if (($predicate['operator'] ?? null) !== 'AND' || !isset($predicate['terms']) || !is_array($predicate['terms'])) {
            return [$predicate];
        }

        $terms = [];
        foreach ($predicate['terms'] as $term) {
            if (is_array($term)) {
                array_push($terms, ...self::flattenAndPredicate($term));
            }
        }

        return $terms;
    }

    /**
     * @return array{column:string,operator:string,value:mixed,usable?:bool}|null
     */
    private static function jsonTableHiddenConstraint(array $predicate): ?array
    {
        if (($predicate['operator'] ?? null) !== '=') {
            return null;
        }
        if (!isset($predicate['left'], $predicate['right']) || !is_array($predicate['left']) || !is_array($predicate['right'])) {
            return null;
        }
        if (($predicate['right']['type'] ?? null) !== 'literal' || !array_key_exists('value', $predicate['right'])) {
            return null;
        }
        if (($predicate['left']['type'] ?? null) !== 'column' || !isset($predicate['left']['name']) || !is_string($predicate['left']['name'])) {
            return null;
        }

        $column = strtolower($predicate['left']['name']);
        if (str_contains($column, '.')) {
            $column = substr($column, strrpos($column, '.') + 1);
        }
        if ($column !== 'json' && $column !== 'root') {
            return null;
        }

        return [
            'column' => $column,
            'operator' => '=',
            'value' => $predicate['right']['value'],
            'usable' => true,
        ];
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
            $join = [
                'type' => 'CROSS',
                'rows' => self::qualifiedRows($table['rows'], $table['alias']),
            ];
            if (isset($table['dynamicRows']) && is_callable($table['dynamicRows'])) {
                $join['dynamicRows'] = $table['dynamicRows'];
            }

            return [$join, $remaining];
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
        if (isset($table['dynamicRows']) && is_callable($table['dynamicRows'])) {
            $join['dynamicRows'] = $table['dynamicRows'];
            if ($table['name'] === 'json_each' || $table['name'] === 'json_tree') {
                $join['rightColumns'] = self::qualifiedJsonTableColumns($table['alias']);
            }
        }

        if (preg_match('/^using\s*\((.*)\)$/i', $condition, $using) === 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL JOIN USING is not supported for qualified SQL text rows');
        }

        if (preg_match('/^on\s+(.+)$/i', $condition, $on) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL JOIN needs ON or USING');
        }
        if ($type === 'CROSS') {
            throw new \InvalidArgumentException('SQLite SELECT SQL CROSS JOIN does not support ON');
        }

        $predicate = self::predicate($on[1], $tables);
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
    private static function predicate(string $sql, array $tables = []): array
    {
        $orTerms = self::splitKeyword($sql, 'OR');
        if (count($orTerms) > 1) {
            return ['operator' => 'OR', 'terms' => array_map(static fn (string $term): array => self::predicate($term, $tables), $orTerms)];
        }

        $andTerms = self::splitKeyword($sql, 'AND');
        if (count($andTerms) > 1) {
            return ['operator' => 'AND', 'terms' => array_map(static fn (string $term): array => self::predicate($term, $tables), $andTerms)];
        }

        $sql = trim($sql);
        if (preg_match('/^(not\s+)?exists\s*\((select\s+.+)\)$/is', $sql, $match) === 1) {
            $subquerySql = trim($match[2]);

            return [
                'operator' => isset($match[1]) && trim($match[1]) !== '' ? 'NOT EXISTS' : 'EXISTS',
                'subquery' => static fn (array $row): array => self::correlatedSubqueryRows($subquerySql, $tables, $row),
            ];
        }

        if (preg_match('/^(.+?)\s+(not\s+)?between\s+(.+)$/is', $sql, $match) === 1) {
            $bounds = self::splitTopLevelByKeyword(trim($match[3]), 'AND');
            if (count($bounds) !== 2 || $bounds[0] === '' || $bounds[1] === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL BETWEEN predicate needs lower and upper operands');
            }

            return [
                'operator' => isset($match[2]) && trim($match[2]) !== '' ? 'NOT BETWEEN' : 'BETWEEN',
                'left' => self::valueExpression(trim($match[1])),
                'lower' => self::valueExpression($bounds[0]),
                'upper' => self::valueExpression($bounds[1]),
            ];
        }

        foreach (['NOT LIKE', 'LIKE', 'NOT GLOB', 'GLOB', 'IS NOT', 'IS', '>=', '<=', '<>', '!=', '=', '>', '<'] as $operator) {
            $offset = self::operatorOffset($sql, $operator);
            if ($offset === null) {
                continue;
            }
            $left = trim(substr($sql, 0, $offset));
            $right = trim(substr($sql, $offset + strlen($operator)));
            if ($left === '' || $right === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL predicate needs both operands');
            }

            $predicate = [
                'operator' => $operator,
                'left' => self::valueExpression($left),
                'right' => self::valueExpression($right),
            ];
            if ($operator === 'LIKE' || $operator === 'NOT LIKE') {
                $escapeParts = self::splitTopLevelByKeyword($right, 'ESCAPE');
                if (count($escapeParts) > 2) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL LIKE predicate supports one ESCAPE clause');
                }
                if (count($escapeParts) === 2) {
                    if ($escapeParts[0] === '' || $escapeParts[1] === '') {
                        throw new \InvalidArgumentException('SQLite SELECT SQL LIKE ESCAPE predicate needs pattern and escape operands');
                    }
                    $predicate['right'] = self::valueExpression($escapeParts[0]);
                    $predicate['escape'] = self::valueExpression($escapeParts[1]);
                }
            }

            return $predicate;
        }

        if (preg_match('/^(.+?)\s+(not\s+)?in\s*\((.*)\)$/i', $sql, $match) === 1) {
            $valuesSql = trim($match[3]);
            if (preg_match('/^select\s+/i', $valuesSql) === 1) {
                $left = self::valueExpression(trim($match[1]));

                return [
                    'operator' => isset($match[2]) && trim($match[2]) !== '' ? 'NOT IN' : 'IN',
                    'left' => $left,
                    'valuesSubquery' => static function (array $row) use ($valuesSql, $tables): array {
                        $rows = self::correlatedSubqueryRows($valuesSql, $tables, $row);
                        if ($rows === []) {
                            return [];
                        }
                        $columns = array_keys($rows[0]);
                        if (count($columns) !== 1) {
                            throw new \InvalidArgumentException('SQLite SELECT SQL IN subquery must return one column');
                        }
                        $column = $columns[0];

                        return array_map(static fn (array $subqueryRow): mixed => $subqueryRow[$column], $rows);
                    },
                ];
            }

            return [
                'operator' => isset($match[2]) && trim($match[2]) !== '' ? 'NOT IN' : 'IN',
                'left' => self::valueExpression(trim($match[1])),
                'values' => array_map(self::valueExpression(...), self::splitTopLevel($valuesSql, ',')),
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
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<string,mixed> $outerRow
     * @return list<array<string,mixed>>
     */
    private static function correlatedSubqueryRows(string $sql, array $tables, array $outerRow): array
    {
        $plan = self::plan($sql, $tables);
        if (($plan['joins'] ?? []) !== []) {
            throw new \InvalidArgumentException('SQLite SELECT SQL correlated subqueries support one FROM source');
        }
        if (array_key_exists('groupBy', $plan)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL correlated subqueries do not support GROUP BY');
        }

        $rows = $plan['from'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL subquery needs source rows');
        }

        $expanded = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL subquery source rows must be arrays');
            }
            $expanded[] = array_merge($outerRow, $row);
        }
        $plan['from'] = $expanded;

        return SQLiteSelectQuery::execute($plan);
    }

    /**
     * @return array<string,mixed>
     */
    private static function valueExpression(string $sql): array
    {
        $sql = trim($sql);
        $unwrapped = self::unwrapParenthesizedExpression($sql);
        if ($unwrapped !== $sql) {
            return self::valueExpression($unwrapped);
        }

        foreach ([['+', '-'], ['*', '/', '%'], ['||']] as $operators) {
            $operator = self::topLevelExpressionOperator($sql, $operators);
            if ($operator === null) {
                continue;
            }

            [$offset, $token] = $operator;
            $left = trim(substr($sql, 0, $offset));
            $right = trim(substr($sql, $offset + strlen($token)));
            if ($left === '' || $right === '') {
                throw new \InvalidArgumentException("SQLite SELECT SQL expression {$token} needs both operands");
            }

            return [
                'type' => 'binary',
                'operator' => $token,
                'left' => self::valueExpression($left),
                'right' => self::valueExpression($right),
            ];
        }

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

    private static function unwrapParenthesizedExpression(string $sql): string
    {
        if (!str_starts_with($sql, '(') || !str_ends_with($sql, ')')) {
            return $sql;
        }

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
                if ($depth === 0 && $i !== $length - 1) {
                    return $sql;
                }
            }
        }

        return trim(substr($sql, 1, -1));
    }

    /**
     * @param list<string> $operators
     * @return array{0:int,1:string}|null
     */
    private static function topLevelExpressionOperator(string $sql, array $operators): ?array
    {
        $depth = 0;
        $quote = false;
        for ($i = strlen($sql) - 1; $i >= 0; $i--) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($i > 0 && $sql[$i - 1] === "'") {
                    $i--;
                    continue;
                }
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                continue;
            }
            if ($char === ')') {
                $depth++;
                continue;
            }
            if ($char === '(') {
                $depth--;
                continue;
            }
            if ($depth !== 0) {
                continue;
            }

            foreach ($operators as $operator) {
                $offset = $i - strlen($operator) + 1;
                if ($offset < 0 || substr($sql, $offset, strlen($operator)) !== $operator) {
                    continue;
                }
                if (($operator === '+' || $operator === '-') && self::isUnarySign($sql, $offset)) {
                    continue;
                }

                return [$offset, $operator];
            }
        }

        return null;
    }

    private static function isUnarySign(string $sql, int $offset): bool
    {
        $before = rtrim(substr($sql, 0, $offset));
        if ($before === '') {
            return true;
        }

        return str_contains('+-*/%(', substr($before, -1));
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
            if (($expression['type'] ?? null) === 'binary') {
                if (isset($expression['left']) && is_array($expression['left'])) {
                    $expression['left'] = self::rewriteAggregateExpression($expression['left'], $valueColumn);
                }
                if (isset($expression['right']) && is_array($expression['right'])) {
                    $expression['right'] = self::rewriteAggregateExpression($expression['right'], $valueColumn);
                }
            }

            return $expression;
        }

        return ['type' => 'column', 'name' => $aggregate['summaryColumn']];
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return list<array{column:string,direction?:string}>
     */
    private static function orderBy(string $sql, array &$select, ?string $aggregateValueColumn): array
    {
        $terms = [];
        foreach (self::splitTopLevel($sql, ',') as $index => $term) {
            [$expressionSql, $direction] = self::orderByExpressionDirection($term);
            if ($expressionSql === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY term cannot be empty');
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/', $expressionSql) === 1) {
                $order = ['column' => $expressionSql];
            } else {
                $expression = self::valueExpression($expressionSql);
                if ($aggregateValueColumn !== null) {
                    $expression = self::rewriteAggregateExpression($expression, $aggregateValueColumn);
                }
                $alias = '__sqlite_order_expr_' . $index;
                $expression['alias'] = $alias;
                $expression['hiddenOrderColumn'] = true;
                $select[] = $expression;
                $order = ['column' => $alias];
            }

            if ($direction !== null) {
                $order['direction'] = $direction;
            }
            $terms[] = $order;
        }

        return $terms;
    }

    /**
     * @return array{0:string,1:?string}
     */
    private static function orderByExpressionDirection(string $term): array
    {
        $term = trim($term);
        foreach (['ASC', 'DESC'] as $direction) {
            $suffix = ' ' . $direction;
            if (strlen($term) > strlen($suffix) && strcasecmp(substr($term, -strlen($suffix)), $suffix) === 0) {
                return [trim(substr($term, 0, -strlen($suffix))), $direction];
            }
        }

        return [$term, null];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    private static function stripHiddenOrderColumns(array $rows, array $plan): array
    {
        $hidden = [];
        foreach ($plan['select'] ?? [] as $expression) {
            if (is_array($expression) && ($expression['hiddenOrderColumn'] ?? false) === true && isset($expression['alias']) && is_string($expression['alias'])) {
                $hidden[] = $expression['alias'];
            }
        }
        if ($hidden === []) {
            return $rows;
        }

        foreach ($rows as &$row) {
            foreach ($hidden as $column) {
                unset($row[$column]);
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function limitOffset(string $sql): array
    {
        $commaParts = self::splitTopLevel($sql, ',');
        if (count($commaParts) === 2) {
            foreach ($commaParts as $part) {
                if (preg_match('/^[+-]?[0-9]+$/', trim($part)) !== 1) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL LIMIT comma form must be integer offset and limit');
                }
            }

            return [(int) trim($commaParts[1]), (int) trim($commaParts[0])];
        }
        if (count($commaParts) > 2) {
            throw new \InvalidArgumentException('SQLite SELECT SQL LIMIT comma form must have offset and limit');
        }

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
        $skipNextBetweenAnd = false;
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
            if (
                $depth === 0
                && strcasecmp($keyword, 'AND') === 0
                && strncasecmp(substr($sql, $i), 'BETWEEN', 7) === 0
                && self::keywordBounded($sql, $i, 7)
            ) {
                $skipNextBetweenAnd = true;
                $i += 6;
                continue;
            }
            if ($depth === 0 && strncasecmp(substr($sql, $i), $keyword, strlen($keyword)) === 0 && self::keywordBounded($sql, $i, strlen($keyword))) {
                if ($skipNextBetweenAnd && strcasecmp($keyword, 'AND') === 0) {
                    $skipNextBetweenAnd = false;
                    $i += strlen($keyword) - 1;
                    continue;
                }
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

    private static function skipWhitespace(string $sql, int $offset): int
    {
        $length = strlen($sql);
        while ($offset < $length && ctype_space($sql[$offset])) {
            $offset++;
        }

        return $offset;
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function consumeParenthesized(string $sql, int $offset): array
    {
        if (($sql[$offset] ?? null) !== '(') {
            throw new \InvalidArgumentException('SQLite SELECT SQL expected parenthesized expression');
        }

        $depth = 0;
        $quote = false;
        $length = strlen($sql);
        for ($i = $offset; $i < $length; $i++) {
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
                if ($depth === 0) {
                    return [substr($sql, $offset + 1, $i - $offset - 1), $i + 1];
                }
                if ($depth < 0) {
                    break;
                }
            }
        }

        throw new \InvalidArgumentException('SQLite SELECT SQL parenthesized expression is incomplete');
    }

    private static function keywordAt(string $sql, int $offset, string $keyword): bool
    {
        return strncasecmp(substr($sql, $offset), $keyword, strlen($keyword)) === 0
            && self::keywordBounded($sql, $offset, strlen($keyword));
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
