<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectSql
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array<string,mixed>>
     */
    public static function execute(string $sql, array $tables, array $parameters = []): array
    {
        $plan = self::plan($sql, $tables, $parameters);
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
    public static function plan(string $sql, array $tables, array $parameters = []): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if ($parameters !== []) {
            $sql = self::bindParameters($sql, $parameters);
        }
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
            return self::constantSelectPlan(trim(substr($sql, 6)), $tables, $cteNames);
        }

        $selectSql = trim(substr($sql, 6, $fromOffset - 6));
        $distinct = false;
        if (preg_match('/^distinct(?:\s+|$)/i', $selectSql) === 1) {
            $distinct = true;
            $selectSql = trim(substr($selectSql, 8));
        } elseif (preg_match('/^all(?:\s+|$)/i', $selectSql) === 1) {
            $selectSql = trim(substr($selectSql, 3));
        }
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
        $select = self::selectList($selectSql, $tables);
        $plan = [
            'from' => $source['from'],
            'select' => $select,
        ];
        if ($distinct) {
            $plan['distinct'] = true;
        }
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
                $tables,
            );
        }
        if (isset($clauseOffsets['LIMIT'])) {
            [$limit, $offset] = self::limitOffset(self::clauseText($tail, $clauseOffsets, 'LIMIT'), $tables);
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
     * @return array<string,mixed>
     */
    private static function constantSelectPlan(string $sql, array $tables, array $cteNames): array
    {
        if ($sql === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL needs select list');
        }

        $clauseOffsets = self::tailClauseOffsets($sql);
        $selectEnd = self::firstOffset($clauseOffsets) ?? strlen($sql);
        $selectSql = trim(substr($sql, 0, $selectEnd));
        if ($selectSql === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL needs select list');
        }

        $distinct = false;
        if (preg_match('/^distinct(?:\s+|$)/i', $selectSql) === 1) {
            $distinct = true;
            $selectSql = trim(substr($selectSql, 8));
        } elseif (preg_match('/^all(?:\s+|$)/i', $selectSql) === 1) {
            $selectSql = trim(substr($selectSql, 3));
        }
        if ($selectSql === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL needs select list');
        }

        if (isset($clauseOffsets['GROUP BY']) || isset($clauseOffsets['HAVING'])) {
            throw new \InvalidArgumentException('SQLite SELECT SQL constant SELECT does not support GROUP BY or HAVING');
        }

        $select = self::selectList($selectSql, $tables);
        foreach ($select as $term) {
            if (($term['type'] ?? null) === 'wildcard') {
                throw new \InvalidArgumentException('SQLite SELECT SQL wildcard projection needs FROM');
            }
        }

        $plan = [
            'from' => [[]],
            'select' => $select,
        ];
        if ($distinct) {
            $plan['distinct'] = true;
        }
        if (isset($clauseOffsets['WHERE'])) {
            $plan['where'] = self::predicate(self::clauseText($sql, $clauseOffsets, 'WHERE'), $tables);
        }
        if (isset($clauseOffsets['ORDER BY'])) {
            $plan['orderBy'] = self::orderBy(
                self::clauseText($sql, $clauseOffsets, 'ORDER BY'),
                $plan['select'],
                null,
                $tables,
            );
        }
        if (isset($clauseOffsets['LIMIT'])) {
            [$limit, $offset] = self::limitOffset(self::clauseText($sql, $clauseOffsets, 'LIMIT'), $tables);
            $plan['limit'] = $limit;
            $plan['offset'] = $offset;
        }
        if ($cteNames !== []) {
            $plan['with'] = $cteNames;
        }

        return $plan;
    }

    /**
     * @param array<int|string,mixed> $parameters
     */
    private static function bindParameters(string $sql, array $parameters): string
    {
        $result = '';
        $length = strlen($sql);
        $quote = false;
        $positionalIndex = 1;
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                $result .= $char;
                if ($quote && ($sql[$i + 1] ?? null) === "'") {
                    $result .= "'";
                    $i++;
                    continue;
                }
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                $result .= $char;
                continue;
            }

            if ($char === '?') {
                $start = $i + 1;
                while ($start < $length && ctype_digit($sql[$start])) {
                    $start++;
                }
                $token = substr($sql, $i, $start - $i);
                if ($token === '?') {
                    $index = $positionalIndex++;
                    $explicit = false;
                } else {
                    $index = (int) substr($token, 1);
                    $positionalIndex = max($positionalIndex, $index + 1);
                    $explicit = true;
                }
                if ($index < 1) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL positional bind parameter index must be positive');
                }
                $result .= self::parameterLiteral(self::parameterValue($parameters, $index, $token, $explicit));
                $i = $start - 1;
                continue;
            }

            if (($char === ':' || $char === '@' || $char === '$') && preg_match('/[A-Za-z_]/', $sql[$i + 1] ?? '') === 1) {
                $start = $i + 2;
                while ($start < $length && preg_match('/[A-Za-z0-9_]/', $sql[$start]) === 1) {
                    $start++;
                }
                $token = substr($sql, $i, $start - $i);
                $result .= self::parameterLiteral(self::parameterValue($parameters, substr($token, 1), $token));
                $i = $start - 1;
                continue;
            }

            $result .= $char;
        }
        if ($quote) {
            throw new \InvalidArgumentException('SQLite SELECT SQL has unterminated string literal');
        }

        return $result;
    }

    /**
     * @param array<int|string,mixed> $parameters
     */
    private static function parameterValue(array $parameters, int|string $key, string $token, bool $explicit = false): mixed
    {
        if (is_int($key)) {
            $zeroBased = $key - 1;
            if (!$explicit && $key === 1 && array_key_exists($zeroBased, $parameters)) {
                return $parameters[$zeroBased];
            }
            if (array_key_exists($key, $parameters)) {
                return $parameters[$key];
            }
            if (array_key_exists($zeroBased, $parameters)) {
                return $parameters[$zeroBased];
            }
        } else {
            foreach ([$key, ':' . $key, '@' . $key, '$' . $key] as $candidate) {
                if (array_key_exists($candidate, $parameters)) {
                    return $parameters[$candidate];
                }
            }
        }

        throw new \InvalidArgumentException("SQLite SELECT SQL bind parameter {$token} is missing");
    }

    private static function parameterLiteral(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if ($value instanceof SQLiteBlobValue) {
            return "X'" . bin2hex($value->bytes) . "'";
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            if (!is_finite((float) $value)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL bind parameter must be finite');
            }

            return (string) $value;
        }
        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }

        throw new \InvalidArgumentException('SQLite SELECT SQL bind parameters must be scalar, BLOB, or NULL');
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
            [$limit, $offset] = self::limitOffset($limitSql, $tables);
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
     * @return list<array{column:string,direction?:string,collation?:string,nulls?:string}>
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
            [$expression, $direction, $collation, $nulls] = self::orderByTermParts($term);
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
            if ($collation !== null) {
                $entry['collation'] = $collation;
            }
            if ($nulls !== null) {
                $entry['nulls'] = $nulls;
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
            $rows = SQLiteSelectCompound::combine($rows, $armRows, (string) $compound['operators'][$index - 1], self::compoundSelectCollations($compound['arms'][0]));
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
     * @param array<string,mixed> $arm
     * @return array<string,string>
     */
    private static function compoundSelectCollations(array $arm): array
    {
        $select = $arm['select'] ?? null;
        if (!is_array($select) || !array_is_list($select)) {
            return [];
        }

        $collations = [];
        foreach ($select as $term) {
            if (!is_array($term) || ($term['type'] ?? null) !== 'collate' || !isset($term['collation']) || !is_string($term['collation'])) {
                continue;
            }
            $column = $term['alias'] ?? null;
            if (is_string($column) && $column !== '') {
                $collations[$column] = strtoupper($term['collation']);
            }
        }

        return $collations;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{0:array<string,list<array<string,mixed>>>,1:string,2:list<string>}
     */
    private static function materializeWithTables(string $sql, array $tables): array
    {
        [$entries, $mainSql, $recursive] = self::withEntries($sql);

        $cteNames = [];
        foreach ($entries as $entry) {
            $name = $entry['name'];
            if (array_key_exists($name, $tables)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL CTE {$name} shadows an input table");
            }
            if ($recursive && self::cteSqlReferencesName($entry['sql'], $name)) {
                $rows = self::executeRecursiveCte($entry, $tables);
            } elseif (preg_match('/^values\s+/i', $entry['sql']) === 1) {
                $rows = self::executeValuesClause($entry['sql']);
            } else {
                $rows = self::execute($entry['sql'], $tables);
            }
            if ($entry['columns'] !== []) {
                $rows = self::renameCteColumns($rows, $entry['columns'], $name);
            }
            $tables[$name] = $rows;
            $cteNames[] = $name;
        }

        return [$tables, $mainSql, $cteNames];
    }

    /**
     * @param array{name:string,columns:list<string>,sql:string} $entry
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array<string,mixed>>
     */
    private static function executeRecursiveCte(array $entry, array $tables): array
    {
        $name = $entry['name'];
        $compound = self::splitCompoundSql($entry['sql']);
        if ($compound === null || count($compound['arms']) !== 2 || count($compound['operators']) !== 1) {
            throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} needs one anchor and one recursive arm");
        }
        $operator = strtoupper($compound['operators'][0]);
        if ($operator !== 'UNION ALL' && $operator !== 'UNION') {
            throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} supports UNION ALL or UNION");
        }

        [$anchorSql, $recursiveSql] = $compound['arms'];
        if (!self::cteSqlReferencesName($recursiveSql, $name)) {
            throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} recursive arm must reference itself");
        }
        if (self::cteSqlReferencesName($anchorSql, $name)) {
            throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} anchor arm cannot reference itself");
        }

        $anchorRows = self::executeCteArm($anchorSql, $tables);
        $columns = $entry['columns'] !== []
            ? $entry['columns']
            : ($anchorRows === [] ? [] : array_keys($anchorRows[0]));
        if ($columns === []) {
            throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} needs anchor columns");
        }
        $rows = self::normalizeRecursiveRows($anchorRows, $columns, $name);
        if ($operator === 'UNION') {
            $rows = self::deduplicateRecursiveRows($rows);
        }
        $frontier = $rows;
        $seen = [];
        foreach ($rows as $row) {
            $seen[self::recursiveRowKey($row)] = true;
        }

        for ($iteration = 0; $iteration < 1000 && $frontier !== []; $iteration++) {
            $stepTables = $tables;
            $stepTables[$name] = $frontier;
            $stepRows = self::normalizeRecursiveRows(self::executeCteArm($recursiveSql, $stepTables), $columns, $name);
            $nextFrontier = [];
            foreach ($stepRows as $row) {
                $key = self::recursiveRowKey($row);
                if ($operator === 'UNION' && isset($seen[$key])) {
                    continue;
                }
                $rows[] = $row;
                $nextFrontier[] = $row;
                $seen[$key] = true;
            }
            $frontier = $nextFrontier;
        }
        if ($frontier !== []) {
            throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} exceeded iteration limit");
        }

        return $rows;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array<string,mixed>>
     */
    private static function executeCteArm(string $sql, array $tables): array
    {
        return preg_match('/^values\s+/i', $sql) === 1
            ? self::executeValuesClause($sql)
            : self::execute($sql, $tables);
    }

    private static function cteSqlReferencesName(string $sql, string $name): bool
    {
        return preg_match('/(^|[^A-Za-z0-9_])' . preg_quote($name, '/') . '([^A-Za-z0-9_]|$)/i', $sql) === 1;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function normalizeRecursiveRows(array $rows, array $columns, string $name): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (count($row) !== count($columns)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} row width does not match anchor");
            }
            $combined = array_combine($columns, array_values($row));
            if (!is_array($combined)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} row width does not match anchor");
            }
            $normalized[] = $combined;
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function recursiveRowKey(array $row): string
    {
        return serialize(array_values($row));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function deduplicateRecursiveRows(array $rows): array
    {
        $deduplicated = [];
        $seen = [];
        foreach ($rows as $row) {
            $key = self::recursiveRowKey($row);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduplicated[] = $row;
        }

        return $deduplicated;
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
            foreach (['MATERIALIZED', 'NOT MATERIALIZED'] as $hint) {
                if (self::keywordAt($sql, $offset, $hint)) {
                    $offset += strlen($hint);
                    $offset = self::skipWhitespace($sql, $offset);
                    break;
                }
            }
            if (($sql[$offset] ?? null) !== '(') {
                throw new \InvalidArgumentException("SQLite SELECT SQL CTE {$name} needs a parenthesized SELECT");
            }
            [$cteSql, $offset] = self::consumeParenthesized($sql, $offset);
            $cteSql = trim($cteSql);
            if (!preg_match('/^(select|values)\s+/i', $cteSql)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL CTE {$name} body must be SELECT or VALUES");
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
     * @return list<array<string,mixed>>
     */
    private static function executeValuesClause(string $sql): array
    {
        $sql = trim($sql);
        if (preg_match('/^values\s+/i', $sql) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL VALUES clause must start with VALUES');
        }

        $offset = strlen('values');
        $length = strlen($sql);
        $rows = [];
        $width = null;
        while (true) {
            $offset = self::skipWhitespace($sql, $offset);
            if (($sql[$offset] ?? null) !== '(') {
                throw new \InvalidArgumentException('SQLite SELECT SQL VALUES row must be parenthesized');
            }
            [$rowSql, $offset] = self::consumeParenthesized($sql, $offset);
            $values = self::splitTopLevel($rowSql, ',');
            if ($values === []) {
                throw new \InvalidArgumentException('SQLite SELECT SQL VALUES row cannot be empty');
            }
            if ($width === null) {
                $width = count($values);
            } elseif ($width !== count($values)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL VALUES rows must have the same width');
            }

            $row = [];
            foreach ($values as $index => $valueSql) {
                $expression = self::valueExpression($valueSql, []);
                if (($expression['type'] ?? null) === 'subquery') {
                    throw new \InvalidArgumentException('SQLite SELECT SQL VALUES row scalar subqueries are not supported');
                }
                $row['column' . ($index + 1)] = SQLiteSelectExpression::evaluate([], $expression);
            }
            $rows[] = $row;

            $offset = self::skipWhitespace($sql, $offset);
            if ($offset >= $length) {
                return $rows;
            }
            if (($sql[$offset] ?? null) !== ',') {
                throw new \InvalidArgumentException('SQLite SELECT SQL VALUES rows must be comma separated');
            }
            $offset++;
        }
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $jsonConstraints
     * @return array{from:list<array<string,mixed>>,joins:list<array<string,mixed>>}
     */
    private static function sourcePlan(string $sql, array $tables, array $jsonConstraints = []): array
    {
        $commaSources = self::splitTopLevel($sql, ',');
        if (count($commaSources) > 1) {
            $sql = trim(array_shift($commaSources));
            foreach ($commaSources as $source) {
                $sql .= ' CROSS JOIN ' . $source;
            }
        }

        $joinOffset = self::firstJoinOffset($sql);
        $baseSql = $joinOffset === null ? $sql : trim(substr($sql, 0, $joinOffset));
        $base = self::tableReference($baseSql, $tables, $jsonConstraints);
        $joins = [];

        if ($joinOffset !== null) {
            $joinSql = trim(substr($sql, $joinOffset));
            $currentRows = self::qualifiedRows($base['rows'], $base['alias']);
            while ($joinSql !== '') {
                [$join, $joinSql] = self::consumeJoin($joinSql, $tables, $currentRows);
                $joins[] = $join;
                $currentRows = [array_fill_keys(array_merge(self::collectColumns($currentRows), self::collectColumns($join['rows'])), null)];
            }
        }

        if ($joins === [] && isset($base['dynamicRows']) && is_callable($base['dynamicRows'])) {
            return [
                'from' => self::unqualifiedRows($base['dynamicRows']([]), $base['alias']),
                'joins' => [],
            ];
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
        $valuesTable = self::valuesTableReference($sql);
        if ($valuesTable !== null) {
            return $valuesTable;
        }

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
                'rows' => self::jsonTableRowsForSql($function, $jsonConstraints),
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
    private static function valuesTableReference(string $sql): ?array
    {
        $sql = trim($sql);
        if (!str_starts_with($sql, '(')) {
            return null;
        }

        [$body, $offset] = self::consumeParenthesized($sql, 0);
        if (preg_match('/^values\s+/i', trim($body)) !== 1) {
            return null;
        }

        $aliasSql = trim(substr($sql, $offset));
        $alias = 'values';
        if ($aliasSql !== '') {
            $parts = preg_split('/\s+/', $aliasSql);
            if ($parts === false || $parts === [] || count($parts) > 2) {
                throw new \InvalidArgumentException('SQLite SELECT SQL VALUES source alias is malformed');
            }
            if (count($parts) === 2) {
                if (strcasecmp($parts[0], 'AS') !== 0) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL VALUES source alias is malformed');
                }
                $alias = $parts[1];
            } else {
                $alias = $parts[0];
            }
            self::assertBareIdentifier($alias, 'SQLite SELECT SQL VALUES source alias');
        }

        return [
            'name' => 'values',
            'alias' => $alias,
            'rows' => self::executeValuesClause(trim($body)),
        ];
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
                    if (!$plan['runnable'] && ($plan['jsonInputKind'] === 'jsonb' || $plan['jsonInputKind'] === 'sql-null')) {
                        return [];
                    }

                    return self::qualifiedJsonRows(self::jsonTableRowsForSql($function, $constraints), $alias);
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
            'rows' => self::jsonTableRowsForSql($function, $constraints),
        ];
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return list<array<string,mixed>>
     */
    private static function jsonTableRowsForSql(string $function, array $constraints): array
    {
        $plan = SQLiteJsonTablePlan::validatedPlan($function, $constraints);
        if (!$plan['runnable'] && ($plan['jsonInputKind'] === 'jsonb' || $plan['jsonInputKind'] === 'sql-null')) {
            return [];
        }

        return SQLiteJsonTablePlan::visibleRows($function, $constraints);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function qualifiedJsonRows(array $rows, string $prefix): array
    {
        $qualified = self::qualifiedRows($rows, $prefix);
        foreach ($rows as $index => $row) {
            if (!array_key_exists('id', $row)) {
                continue;
            }
            $qualified[$index][$prefix . '.rowid'] = $row['id'];
            $qualified[$index][$prefix . '._rowid_'] = $row['id'];
            $qualified[$index][$prefix . '.oid'] = $row['id'];
        }

        return $qualified;
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

        $columnExpression = $predicate['left'];
        $literalExpression = $predicate['right'];
        if (($columnExpression['type'] ?? null) !== 'column' || !isset($columnExpression['name']) || !is_string($columnExpression['name'])) {
            $columnExpression = $predicate['right'];
            $literalExpression = $predicate['left'];
        }
        if (($columnExpression['type'] ?? null) !== 'column' || !isset($columnExpression['name']) || !is_string($columnExpression['name'])) {
            return null;
        }
        if (($literalExpression['type'] ?? null) !== 'literal' || !array_key_exists('value', $literalExpression)) {
            return null;
        }

        $column = strtolower($columnExpression['name']);
        if (str_contains($column, '.')) {
            $column = substr($column, strrpos($column, '.') + 1);
        }
        if ($column !== 'json' && $column !== 'root') {
            return null;
        }

        return [
            'column' => $column,
            'operator' => '=',
            'value' => $literalExpression['value'],
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
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function unqualifiedRows(array $rows, string $prefix): array
    {
        $unqualified = [];
        $prefix .= '.';
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL table rows must be arrays');
            }
            $unqualifiedRow = [];
            foreach ($row as $column => $value) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite SELECT SQL table rows must have named columns');
                }
                $unqualifiedRow[str_starts_with($column, $prefix) ? substr($column, strlen($prefix)) : $column] = $value;
            }
            $unqualified[] = $unqualifiedRow;
        }

        return $unqualified;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{0:array<string,mixed>,1:string}
     */
    private static function consumeJoin(string $sql, array $tables, array $leftRows = []): array
    {
        if (preg_match('/^((?:natural\s+)?(?:(?:left|right|full)(?:\s+outer)?\s+join|inner\s+join|cross\s+join|join))\s+/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL JOIN clause is not supported');
        }

        $keyword = strtoupper(preg_replace('/\s+/', ' ', $match[1]));
        $natural = str_starts_with($keyword, 'NATURAL ');
        if ($natural) {
            $keyword = substr($keyword, 8);
        }
        $type = match ($keyword) {
            'JOIN', 'INNER JOIN' => 'INNER',
            'LEFT JOIN', 'LEFT OUTER JOIN' => 'LEFT',
            'RIGHT JOIN', 'RIGHT OUTER JOIN' => 'RIGHT',
            'FULL JOIN', 'FULL OUTER JOIN' => 'FULL',
            'CROSS JOIN' => 'CROSS',
            default => throw new \InvalidArgumentException('SQLite SELECT SQL JOIN type is not supported'),
        };
        if ($natural && $type === 'CROSS') {
            throw new \InvalidArgumentException('SQLite SELECT SQL NATURAL CROSS JOIN is not supported');
        }

        $rest = trim(substr($sql, strlen($match[0])));
        $boundary = self::nextJoinConditionOffset($rest);
        if ($natural) {
            $boundary = null;
        }
        if ($boundary === null && ($type === 'CROSS' || $natural)) {
            $nextJoin = self::firstJoinOffset($rest);
            $tableSql = $nextJoin === null ? $rest : trim(substr($rest, 0, $nextJoin));
            $remaining = $nextJoin === null ? '' : trim(substr($rest, $nextJoin));
            $table = self::tableReference($tableSql, $tables);
            $rightRows = ($table['name'] === 'json_each' || $table['name'] === 'json_tree')
                ? self::qualifiedJsonRows($table['rows'], $table['alias'])
                : self::qualifiedRows($table['rows'], $table['alias']);
            if ($natural) {
                $columns = self::naturalJoinColumns($leftRows, $rightRows);
                $join = [
                    'type' => $type,
                    'rows' => $rightRows,
                    'predicate' => self::usingPredicate($columns, $leftRows, $rightRows),
                ];
                if ($type === 'LEFT' || $type === 'FULL') {
                    $join['rightColumns'] = self::collectColumns($rightRows);
                }

                return [$join, $remaining];
            }
            $join = [
                'type' => 'CROSS',
                'rows' => $rightRows,
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
            'rows' => ($table['name'] === 'json_each' || $table['name'] === 'json_tree')
                ? self::qualifiedJsonRows($table['rows'], $table['alias'])
                : self::qualifiedRows($table['rows'], $table['alias']),
        ];
        if (isset($table['dynamicRows']) && is_callable($table['dynamicRows'])) {
            $join['dynamicRows'] = $table['dynamicRows'];
            if ($table['name'] === 'json_each' || $table['name'] === 'json_tree') {
                $join['rightColumns'] = self::qualifiedJsonTableColumns($table['alias']);
            }
        }

        if (preg_match('/^using\s*\((.*)\)$/i', $condition, $using) === 1) {
            $columns = array_map('trim', self::splitTopLevel($using[1], ','));
            foreach ($columns as $column) {
                self::assertBareIdentifier($column, 'SQLite SELECT SQL JOIN USING column');
            }
            if ($type === 'CROSS') {
                throw new \InvalidArgumentException('SQLite SELECT SQL CROSS JOIN does not support USING');
            }
            $join['predicate'] = self::usingPredicate($columns, $leftRows, $join['rows']);
            if ($type === 'LEFT' || $type === 'FULL') {
                $join['rightColumns'] = self::collectColumns($join['rows']);
            }

            return [$join, $remaining];
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
        if ($type === 'LEFT' || $type === 'FULL') {
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
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @return list<string>
     */
    private static function naturalJoinColumns(array $leftRows, array $rightRows): array
    {
        $left = array_map(self::unqualifiedColumn(...), self::collectColumns($leftRows));
        $right = array_map(self::unqualifiedColumn(...), self::collectColumns($rightRows));

        return array_values(array_intersect($left, $right));
    }

    /**
     * @param list<string> $columns
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @return callable(array<string,mixed>,array<string,mixed>):bool
     */
    private static function usingPredicate(array $columns, array $leftRows, array $rightRows): callable
    {
        if ($columns === []) {
            return static fn (): bool => true;
        }
        $leftColumns = self::resolveJoinColumns($columns, self::collectColumns($leftRows), 'left');
        $rightColumns = self::resolveJoinColumns($columns, self::collectColumns($rightRows), 'right');

        return static function (array $left, array $right) use ($leftColumns, $rightColumns): bool {
            foreach ($leftColumns as $index => $leftColumn) {
                $rightColumn = $rightColumns[$index];
                if (!array_key_exists($leftColumn, $left) || !array_key_exists($rightColumn, $right)) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL JOIN USING row is missing a comparison column');
                }
                if ($left[$leftColumn] === null || $right[$rightColumn] === null) {
                    return false;
                }
                if (self::joinValueKey($left[$leftColumn]) !== self::joinValueKey($right[$rightColumn])) {
                    return false;
                }
            }

            return true;
        };
    }

    /**
     * @param list<string> $columns
     * @param list<string> $available
     * @return list<string>
     */
    private static function resolveJoinColumns(array $columns, array $available, string $side): array
    {
        $resolved = [];
        foreach ($columns as $column) {
            $matches = array_values(array_filter(
                $available,
                static fn (string $candidate): bool => self::unqualifiedColumn($candidate) === $column
            ));
            if ($matches === []) {
                throw new \InvalidArgumentException("SQLite SELECT SQL JOIN USING {$side} side is missing column {$column}");
            }
            if ($side !== 'left' && count($matches) > 1) {
                throw new \InvalidArgumentException("SQLite SELECT SQL JOIN USING {$side} side column {$column} is ambiguous");
            }
            $resolved[] = $matches[0];
        }

        return $resolved;
    }

    private static function unqualifiedColumn(string $column): string
    {
        return str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;
    }

    private static function joinValueKey(mixed $value): string
    {
        if ($value === null) {
            return 'null:';
        }
        if ($value instanceof SQLiteBlobValue) {
            return 'blob:' . $value->bytes;
        }
        if (is_bool($value) || is_int($value)) {
            return 'integer:' . (int) $value;
        }
        if (is_float($value)) {
            return 'real:' . sprintf('%.17G', $value);
        }
        if (is_string($value)) {
            return 'text:' . $value;
        }

        throw new \InvalidArgumentException('SQLite SELECT SQL JOIN USING values must be scalar, BLOB, or NULL');
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
    private static function selectList(string $sql, array $tables = []): array
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
                $term = self::valueExpression($expression, $tables);
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
                'left' => self::valueExpression(trim($match[1]), $tables),
                'lower' => self::valueExpression($bounds[0], $tables),
                'upper' => self::valueExpression($bounds[1], $tables),
            ];
        }

        foreach (['IS NOT DISTINCT FROM', 'IS DISTINCT FROM', 'NOT LIKE', 'LIKE', 'NOT GLOB', 'GLOB', 'IS NOT', 'IS', '>=', '<=', '<>', '!=', '=', '>', '<'] as $operator) {
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
                'left' => self::valueExpression($left, $tables),
                'right' => self::valueExpression($right, $tables),
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
                    $predicate['right'] = self::valueExpression($escapeParts[0], $tables);
                    $predicate['escape'] = self::valueExpression($escapeParts[1], $tables);
                }
            }

            return $predicate;
        }

        if (preg_match('/^(.+?)\s+(not\s+)?in\s*\((.*)\)$/i', $sql, $match) === 1) {
            $valuesSql = trim($match[3]);
            if (preg_match('/^select\s+/i', $valuesSql) === 1) {
                $left = self::valueExpression(trim($match[1]), $tables);

                return [
                    'operator' => isset($match[2]) && trim($match[2]) !== '' ? 'NOT IN' : 'IN',
                    'left' => $left,
                    'valuesSubquery' => static function (array $row) use ($valuesSql, $tables, $left): array {
                        $rows = self::correlatedSubqueryRows($valuesSql, $tables, $row);
                        if ($rows === []) {
                            return [];
                        }
                        $columns = array_keys($rows[0]);
                        if (($left['type'] ?? null) === 'row') {
                            return array_map(static fn (array $subqueryRow): array => array_values($subqueryRow), $rows);
                        }
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
                'left' => self::valueExpression(trim($match[1]), $tables),
                'values' => array_map(static fn (string $value): array => self::valueExpression($value, $tables), self::splitTopLevel($valuesSql, ',')),
            ];
        }

        if (preg_match('/^(.+?)\s+is\s+(not\s+)?null$/i', $sql, $match) === 1) {
            return [
                'operator' => isset($match[2]) && trim($match[2]) !== '' ? 'IS NOT NULL' : 'IS NULL',
                'left' => self::valueExpression(trim($match[1]), $tables),
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

        return self::stripHiddenOrderColumns(SQLiteSelectQuery::execute($plan), $plan);
    }

    /**
     * @return array<string,mixed>
     */
    private static function valueExpression(string $sql, array $tables = []): array
    {
        $sql = trim($sql);
        $window = self::windowExpression($sql, $tables);
        if ($window !== null) {
            return $window;
        }

        if (str_starts_with($sql, '(') && str_ends_with($sql, ')')) {
            $subquerySql = trim(substr($sql, 1, -1));
            if (preg_match('/^select\s+/i', $subquerySql) === 1 && self::unwrapParenthesizedExpression($sql) === $subquerySql) {
                return [
                    'type' => 'subquery',
                    'subquery' => static fn (array $row): array => self::correlatedSubqueryRows($subquerySql, $tables, $row),
                ];
            }
        }
        $unwrapped = self::unwrapParenthesizedExpression($sql);
        if ($unwrapped !== $sql) {
            $rowTerms = self::splitTopLevel($unwrapped, ',');
            if (count($rowTerms) > 1) {
                return [
                    'type' => 'row',
                    'values' => array_map(static fn (string $term): array => self::valueExpression($term, $tables), $rowTerms),
                ];
            }

            return self::valueExpression($unwrapped, $tables);
        }

        $case = self::caseExpression($sql, $tables);
        if ($case !== null) {
            return $case;
        }

        if (preg_match('/^(.+)\s+COLLATE\s+([A-Za-z_][A-Za-z0-9_]*)$/is', $sql, $match) === 1) {
            $collation = strtoupper($match[2]);
            if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
                throw new \InvalidArgumentException("Unsupported SQLite SELECT SQL collation: {$match[2]}");
            }

            return [
                'type' => 'collate',
                'operand' => self::valueExpression($match[1], $tables),
                'collation' => $collation,
            ];
        }

        foreach ([['&', '|', '<<', '>>'], ['||'], ['->>', '->'], ['+', '-'], ['*', '/', '%']] as $operators) {
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
                'left' => self::valueExpression($left, $tables),
                'right' => self::valueExpression($right, $tables),
            ];
        }
        if (preg_match('/^[+\-~]\s*(.+)$/s', $sql, $match) === 1) {
            return [
                'type' => 'unary',
                'operator' => $sql[0],
                'operand' => self::valueExpression($match[1], $tables),
            ];
        }

        if (preg_match('/^cast\s*\((.+)\s+as\s+([A-Za-z][A-Za-z0-9]*)\)$/is', $sql, $match) === 1) {
            return [
                'type' => 'cast',
                'operand' => self::valueExpression($match[1], $tables),
                'target' => $match[2],
            ];
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\((.*)\)$/', $sql, $match) === 1) {
            if (trim($match[2]) === '*') {
                $arguments = [['type' => 'wildcard']];
            } else {
                $arguments = trim($match[2]) === '' ? [] : array_map(static fn (string $argument): array => self::valueExpression($argument, $tables), self::splitTopLevel($match[2], ','));
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
        if (preg_match("/^[xX]'([0-9A-Fa-f]*)'$/", $sql, $match) === 1) {
            if (strlen($match[1]) % 2 !== 0) {
                throw new \InvalidArgumentException('SQLite SELECT SQL BLOB literal must have an even number of hex digits');
            }

            $bytes = hex2bin($match[1]);
            if (!is_string($bytes)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL BLOB literal is malformed');
            }

            return ['type' => 'literal', 'value' => new SQLiteBlobValue($bytes)];
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
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,mixed>|null
     */
    private static function caseExpression(string $sql, array $tables): ?array
    {
        if (preg_match('/^case(?:\s|$)/i', $sql) !== 1) {
            return null;
        }

        $tokens = self::caseKeywordTokens($sql);
        if ($tokens === [] || strtoupper($tokens[array_key_last($tokens)]['keyword']) !== 'END') {
            throw new \InvalidArgumentException('SQLite SELECT SQL CASE expression must end with END');
        }
        if (trim(substr($sql, $tokens[array_key_last($tokens)]['offset'] + 3)) !== '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL CASE expression has trailing content after END');
        }

        $firstWhen = null;
        foreach ($tokens as $index => $token) {
            if ($token['keyword'] === 'WHEN') {
                $firstWhen = $index;
                break;
            }
        }
        if ($firstWhen === null) {
            throw new \InvalidArgumentException('SQLite SELECT SQL CASE expression needs WHEN branches');
        }

        $baseSql = trim(substr($sql, 4, $tokens[$firstWhen]['offset'] - 4));
        $case = [
            'type' => 'case',
            'branches' => [],
        ];
        if ($baseSql !== '') {
            $case['base'] = self::valueExpression($baseSql, $tables);
        }

        $index = $firstWhen;
        while ($index < count($tokens)) {
            $when = $tokens[$index];
            if ($when['keyword'] === 'ELSE' || $when['keyword'] === 'END') {
                break;
            }
            if ($when['keyword'] !== 'WHEN') {
                throw new \InvalidArgumentException('SQLite SELECT SQL CASE expression expected WHEN');
            }

            $then = $tokens[$index + 1] ?? null;
            if ($then === null || $then['keyword'] !== 'THEN') {
                throw new \InvalidArgumentException('SQLite SELECT SQL CASE expression WHEN needs THEN');
            }
            $next = $tokens[$index + 2] ?? null;
            if ($next === null || !in_array($next['keyword'], ['WHEN', 'ELSE', 'END'], true)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL CASE expression THEN needs a terminator');
            }

            $whenSql = trim(substr($sql, $when['offset'] + 4, $then['offset'] - ($when['offset'] + 4)));
            $thenSql = trim(substr($sql, $then['offset'] + 4, $next['offset'] - ($then['offset'] + 4)));
            if ($whenSql === '' || $thenSql === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL CASE expression WHEN and THEN cannot be empty');
            }

            $case['branches'][] = [
                'when' => self::valueExpression($whenSql, $tables),
                'then' => self::valueExpression($thenSql, $tables),
            ];
            $index += 2;
        }

        $else = $tokens[$index] ?? null;
        if ($else !== null && $else['keyword'] === 'ELSE') {
            $end = $tokens[$index + 1] ?? null;
            if ($end === null || $end['keyword'] !== 'END') {
                throw new \InvalidArgumentException('SQLite SELECT SQL CASE expression ELSE must be followed by END');
            }
            $elseSql = trim(substr($sql, $else['offset'] + 4, $end['offset'] - ($else['offset'] + 4)));
            if ($elseSql === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL CASE expression ELSE cannot be empty');
            }
            $case['else'] = self::valueExpression($elseSql, $tables);
            $index++;
        }

        if (($tokens[$index] ?? null)['keyword'] !== 'END') {
            throw new \InvalidArgumentException('SQLite SELECT SQL CASE expression must terminate with END');
        }

        return $case;
    }

    /**
     * @return list<array{keyword:string,offset:int}>
     */
    private static function caseKeywordTokens(string $sql): array
    {
        $tokens = [];
        $depth = 0;
        $caseDepth = 0;
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
            if ($depth !== 0) {
                continue;
            }

            foreach (['CASE', 'WHEN', 'THEN', 'ELSE', 'END'] as $keyword) {
                if (strncasecmp(substr($sql, $i), $keyword, strlen($keyword)) !== 0 || !self::keywordBounded($sql, $i, strlen($keyword))) {
                    continue;
                }
                if ($keyword === 'CASE') {
                    $caseDepth++;
                    $i += strlen($keyword) - 1;
                    continue 2;
                }
                if ($caseDepth === 1) {
                    $tokens[] = ['keyword' => $keyword, 'offset' => $i];
                }
                if ($keyword === 'END') {
                    $caseDepth--;
                }
                $i += strlen($keyword) - 1;
                continue 2;
            }
        }
        if ($quote || $caseDepth !== 0) {
            throw new \InvalidArgumentException('SQLite SELECT SQL CASE expression is unterminated');
        }

        return $tokens;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,mixed>|null
     */
    private static function windowExpression(string $sql, array $tables): ?array
    {
        $overOffset = self::keywordOffset($sql, 'OVER');
        if ($overOffset === null) {
            return null;
        }

        $functionSql = trim(substr($sql, 0, $overOffset));
        $overSql = trim(substr($sql, $overOffset + 4));
        if (!str_starts_with($overSql, '(') || !str_ends_with($overSql, ')')) {
            throw new \InvalidArgumentException('SQLite SELECT SQL window OVER clause must be parenthesized');
        }
        $windowSql = self::unwrapParenthesizedExpression($overSql);

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\((.*)\)$/', $functionSql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL window expression needs a function call');
        }

        $name = strtolower($match[1]);
        $argumentSql = trim($match[2]);
        $arguments = [];
        if ($argumentSql !== '') {
            foreach (self::splitTopLevel($argumentSql, ',') as $argument) {
                $arguments[] = trim($argument) === '*'
                    ? ['type' => 'wildcard']
                    : self::valueExpression($argument, $tables);
            }
        }

        $partitionBy = [];
        $orderBy = [];
        $frame = null;
        $partitionOffset = self::keywordOffset($windowSql, 'PARTITION BY');
        $orderOffset = self::keywordOffset($windowSql, 'ORDER BY');
        $frameOffset = self::windowFrameOffset($windowSql);
        if ($partitionOffset !== null) {
            $partitionEnd = strlen($windowSql);
            foreach ([$orderOffset, $frameOffset] as $offset) {
                if ($offset !== null && $offset > $partitionOffset && $offset < $partitionEnd) {
                    $partitionEnd = $offset;
                }
            }
            $partitionSql = trim(substr($windowSql, $partitionOffset + strlen('PARTITION BY'), $partitionEnd - ($partitionOffset + strlen('PARTITION BY'))));
            if ($partitionSql === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL window PARTITION BY needs expressions');
            }
            $partitionBy = array_map(static fn (string $term): array => self::valueExpression($term, $tables), self::splitTopLevel($partitionSql, ','));
        }
        if ($orderOffset !== null) {
            $orderEnd = $frameOffset !== null && $frameOffset > $orderOffset ? $frameOffset : strlen($windowSql);
            $orderSql = trim(substr($windowSql, $orderOffset + strlen('ORDER BY'), $orderEnd - ($orderOffset + strlen('ORDER BY'))));
            if ($orderSql === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL window ORDER BY needs expressions');
            }
            foreach (self::splitTopLevel($orderSql, ',') as $term) {
                [$expression, $direction] = self::windowOrderTerm($term, $tables);
                $orderBy[] = ['expression' => $expression, 'direction' => $direction];
            }
        }
        if ($frameOffset !== null) {
            $frame = self::windowFrameClause(trim(substr($windowSql, $frameOffset)));
        }

        $supported = ['row_number', 'rank', 'dense_rank', 'percent_rank', 'cume_dist', 'ntile', 'lag', 'lead', 'first_value', 'last_value', 'nth_value', 'count', 'sum', 'group_concat'];
        if (!in_array($name, $supported, true)) {
            throw new \InvalidArgumentException("SQLite SELECT SQL window function {$name} is not supported");
        }

        $expression = [
            'type' => 'window',
            'function' => $name,
            'arguments' => $arguments,
            'partitionBy' => $partitionBy,
            'orderBy' => $orderBy,
        ];
        if ($frame !== null) {
            $expression['frame'] = $frame;
        }

        return $expression;
    }

    private static function windowFrameOffset(string $sql): ?int
    {
        $offsets = [];
        foreach (['ROWS', 'RANGE', 'GROUPS'] as $keyword) {
            $offset = self::keywordOffset($sql, $keyword);
            if ($offset !== null) {
                $offsets[] = $offset;
            }
        }
        if ($offsets === []) {
            return null;
        }

        return min($offsets);
    }

    /**
     * @return array{unit:string,preceding:int|float,following:int|float,exclude:string}
     */
    private static function windowFrameClause(string $sql): array
    {
        $exclude = 'NO OTHERS';
        $excludeOffset = self::keywordOffset($sql, 'EXCLUDE');
        if ($excludeOffset !== null) {
            $excludeSql = trim(substr($sql, $excludeOffset + strlen('EXCLUDE')));
            $sql = trim(substr($sql, 0, $excludeOffset));
            $exclude = match (strtoupper(preg_replace('/\s+/', ' ', $excludeSql))) {
                'NO OTHERS' => 'NO OTHERS',
                'CURRENT ROW' => 'CURRENT ROW',
                'GROUP' => 'GROUP',
                'TIES' => 'TIES',
                default => throw new \InvalidArgumentException('SQLite SELECT SQL window EXCLUDE mode is not supported'),
            };
        }

        if (preg_match('/^(ROWS|RANGE|GROUPS)\s+BETWEEN\s+CURRENT\s+ROW\s+AND\s+(.+?)\s+FOLLOWING$/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL window frame supports BETWEEN CURRENT ROW AND N FOLLOWING');
        }

        return [
            'unit' => strtoupper($match[1]),
            'preceding' => 0,
            'following' => self::windowFrameOffsetValue(trim($match[2])),
            'exclude' => $exclude,
        ];
    }

    private static function windowFrameOffsetValue(string $sql): int|float
    {
        if (strcasecmp($sql, 'CURRENT ROW') === 0) {
            return 0;
        }
        if (preg_match('/^[+]?[0-9]+$/', $sql) === 1) {
            return (int) $sql;
        }
        if (preg_match('/^[+]?(?:[0-9]+\.[0-9]*|\.[0-9]+)$/', $sql) === 1) {
            return (float) $sql;
        }

        throw new \InvalidArgumentException('SQLite SELECT SQL window frame offset must be numeric');
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{0:array<string,mixed>,1:string}
     */
    private static function windowOrderTerm(string $term, array $tables): array
    {
        $term = trim($term);
        $direction = 'ASC';
        if (preg_match('/^(.+?)\s+(ASC|DESC)$/i', $term, $match) === 1) {
            $term = trim($match[1]);
            $direction = strtoupper($match[2]);
        }
        if ($term === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL window ORDER BY term cannot be empty');
        }

        return [self::valueExpression($term, $tables), $direction];
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
                if ($operator === '>>' && ($sql[$offset - 1] ?? null) === '-') {
                    continue;
                }
                if ($operator === '|' && (($sql[$offset - 1] ?? null) === '|' || ($sql[$offset + 1] ?? null) === '|')) {
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

        return str_contains('+-*/%&|~(<', substr($before, -1));
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
    private static function orderBy(string $sql, array &$select, ?string $aggregateValueColumn, array $tables = []): array
    {
        $terms = [];
        foreach (self::splitTopLevel($sql, ',') as $index => $term) {
            [$expressionSql, $direction, $collation, $nulls] = self::orderByTermParts($term);
            if ($expressionSql === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY term cannot be empty');
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/', $expressionSql) === 1) {
                if (self::selectProvidesColumn($select, $expressionSql)) {
                    $order = ['column' => $expressionSql];
                } else {
                    $alias = '__sqlite_order_column_' . $index;
                    $select[] = [
                        'type' => 'column',
                        'name' => $expressionSql,
                        'alias' => $alias,
                        'hiddenOrderColumn' => true,
                    ];
                    $order = ['column' => $alias];
                }
            } else {
                $expression = self::valueExpression($expressionSql, $tables);
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
            if ($collation !== null) {
                $order['collation'] = $collation;
            }
            if ($nulls !== null) {
                $order['nulls'] = $nulls;
            }
            $terms[] = $order;
        }

        return $terms;
    }

    /**
     * @param list<array<string,mixed>> $select
     */
    private static function selectProvidesColumn(array $select, string $column): bool
    {
        foreach ($select as $term) {
            if (($term['type'] ?? null) === 'wildcard') {
                return true;
            }
            if (($term['alias'] ?? null) === $column) {
                return true;
            }
            if (($term['type'] ?? null) === 'column' && ($term['name'] ?? null) === $column && !isset($term['alias'])) {
                return true;
            }
        }

        return false;
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
     * @return array{0:string,1:?string,2:?string,3:?string}
     */
    private static function orderByTermParts(string $term): array
    {
        $term = trim($term);
        $nulls = null;
        if (preg_match('/\s+NULLS\s+(FIRST|LAST)\s*$/i', $term, $match) === 1) {
            $nulls = strtoupper($match[1]);
            $term = trim(substr($term, 0, -strlen($match[0])));
        }

        [$term, $direction] = self::orderByExpressionDirection($term);

        $collation = null;
        if (preg_match('/\s+COLLATE\s+([A-Za-z_][A-Za-z0-9_]*)\s*$/i', $term, $match) === 1) {
            $collation = strtoupper($match[1]);
            $term = trim(substr($term, 0, -strlen($match[0])));
        }

        return [$term, $direction, $collation, $nulls];
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
    private static function limitOffset(string $sql, array $tables = []): array
    {
        $commaParts = self::splitTopLevel($sql, ',');
        if (count($commaParts) === 2) {
            return [self::limitInteger($commaParts[1], $tables), self::limitInteger($commaParts[0], $tables)];
        }
        if (count($commaParts) > 2) {
            throw new \InvalidArgumentException('SQLite SELECT SQL LIMIT comma form must have offset and limit');
        }

        $offsetParts = self::splitTopLevelByKeyword(trim($sql), 'OFFSET');
        if (count($offsetParts) > 2 || $offsetParts[0] === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL LIMIT must be integer with optional OFFSET');
        }

        return [
            self::limitInteger($offsetParts[0], $tables),
            isset($offsetParts[1]) && $offsetParts[1] !== '' ? self::limitInteger($offsetParts[1], $tables) : 0,
        ];
    }

    private static function limitInteger(string $sql, array $tables = []): int
    {
        $expression = self::valueExpression($sql, $tables);
        $value = SQLiteSelectExpression::evaluate([], $expression);
        if (!is_int($value) && !is_float($value) && !is_bool($value)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL LIMIT expression must evaluate to numeric');
        }

        return (int) $value;
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
                if (
                    $operator === '>'
                    && (
                        ($sql[$i - 1] ?? null) === '-'
                        || (($sql[$i - 1] ?? null) === '>' && ($sql[$i - 2] ?? null) === '-')
                    )
                ) {
                    continue;
                }
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
        foreach (['NATURAL LEFT OUTER JOIN', 'NATURAL RIGHT OUTER JOIN', 'NATURAL FULL OUTER JOIN', 'NATURAL LEFT JOIN', 'NATURAL RIGHT JOIN', 'NATURAL FULL JOIN', 'NATURAL INNER JOIN', 'LEFT OUTER JOIN', 'RIGHT OUTER JOIN', 'FULL OUTER JOIN', 'INNER JOIN', 'CROSS JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'FULL JOIN', 'NATURAL JOIN', 'JOIN'] as $keyword) {
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
