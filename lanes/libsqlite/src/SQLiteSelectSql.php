<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectSql
{
    private const MAX_VARIABLE_NUMBER = 32766;
    private const HIDDEN_WILDCARD_METADATA_PREFIX = '__sqlite_hidden_wildcard_columns';
    private const JSON_TABLE_SOURCE_COLUMNS = ['key', 'value', 'type', 'atom', 'id', 'parent', 'fullkey', 'path', 'json', 'root'];
    private const JSON_TABLE_HIDDEN_WILDCARD_COLUMNS = ['json', 'root'];

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

        return self::stripInternalMetadata(self::stripHiddenOrderColumns($rows, $plan));
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{name:string,columns:list<string>,operator:string,rows:list<array<string,mixed>>,trace:list<array<string,mixed>>,skipped:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function recursiveCteCycleTrace(string $sql, array $tables, array $parameters = []): array
    {
        $sql = trim(rtrim(self::stripSqlComments(trim($sql)), ';'));
        $sql = self::bindParameters($sql, $parameters);
        [$entries, $mainSql, $recursive] = self::withEntries($sql);
        if (!$recursive) {
            throw new \InvalidArgumentException('SQLite SELECT SQL recursive CTE trace needs WITH RECURSIVE');
        }

        foreach ($entries as $entry) {
            $name = $entry['name'];
            if (array_key_exists($name, $tables)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL CTE {$name} shadows an input table");
            }
            if (self::cteSqlReferencesName($entry['sql'], $name)) {
                $trace = self::executeRecursiveCteWithTrace($entry, $tables, true);
                $tables[$name] = $trace['rows'];
                if (!self::cteSqlReferencesName($mainSql, $name)) {
                    throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} trace must be selected by trailing SELECT");
                }

                return $trace;
            }

            $rows = preg_match('/^values\s+/i', $entry['sql']) === 1
                ? self::executeValuesClause($entry['sql'])
                : self::execute($entry['sql'], $tables);
            if ($entry['columns'] !== []) {
                $rows = self::renameCteColumns($rows, $entry['columns'], $name);
            }
            $tables[$name] = $rows;
        }

        throw new \InvalidArgumentException('SQLite SELECT SQL recursive CTE trace did not find a recursive entry');
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function stripInternalMetadata(array $rows): array
    {
        foreach ($rows as $index => $row) {
            foreach (array_keys($row) as $column) {
                if (is_string($column) && self::isInternalMetadataColumn($column)) {
                    unset($row[$column]);
                }
            }
            $rows[$index] = $row;
        }

        return $rows;
    }

    private static function isInternalMetadataColumn(string $column): bool
    {
        return $column === '__sqlite_column_affinities'
            || $column === '__sqlite_column_collations'
            || str_starts_with($column, self::HIDDEN_WILDCARD_METADATA_PREFIX)
            || str_ends_with($column, '.__sqlite_column_affinities')
            || str_ends_with($column, '.__sqlite_column_collations');
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,mixed>
     */
    public static function plan(string $sql, array $tables, array $parameters = [], ?array $outerRow = null): array
    {
        $sql = trim(rtrim(self::stripSqlComments(trim($sql)), ';'));
        $sql = self::bindParameters($sql, $parameters);
        if (preg_match('/^with\s+/i', $sql) === 1) {
            [$tables, $sql, $cteNames] = self::materializeWithTables($sql, $tables);
        } else {
            $cteNames = [];
        }
        if (preg_match('/^values(?:\s+|\()/i', $sql) === 1) {
            $compound = self::compoundSqlPlan($sql, $tables, $cteNames, $outerRow);
            if ($compound !== null) {
                return $compound;
            }

            $plan = self::valuesSelectPlan($sql);
            if ($cteNames !== []) {
                $plan['with'] = $cteNames;
            }

            return $plan;
        }

        if (!preg_match('/^select\s+/i', $sql)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL must start with SELECT or VALUES');
        }

        $compound = self::compoundSqlPlan($sql, $tables, $cteNames, $outerRow);
        if ($compound !== null) {
            return $compound;
        }

        $fromOffset = self::keywordOffset($sql, 'FROM');
        if ($fromOffset === null) {
            return self::constantSelectPlan(trim(substr($sql, 6)), $tables, $cteNames);
        }

        $selectSql = trim(substr($sql, 6, $fromOffset - 6));
        [$selectSql, $distinct] = self::selectModifier($selectSql);
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
        $groupBySql = isset($clauseOffsets['GROUP BY'])
            ? self::clauseText($tail, $clauseOffsets, 'GROUP BY')
            : null;
        $namedWindows = isset($clauseOffsets['WINDOW'])
            ? self::namedWindowDefinitions(self::clauseText($tail, $clauseOffsets, 'WINDOW'))
            : [];
        if ($namedWindows !== []) {
            $selectSql = self::expandNamedWindowReferences($selectSql, $namedWindows);
        }
        $orderBySql = isset($clauseOffsets['ORDER BY'])
            ? self::clauseText($tail, $clauseOffsets, 'ORDER BY')
            : null;
        if ($namedWindows !== [] && $orderBySql !== null) {
            $orderBySql = self::expandNamedWindowReferences($orderBySql, $namedWindows);
        }
        $jsonConstraints = self::jsonTableHiddenConstraints($fromSql, $where);
        $requiredSourceColumns = self::requiredSourceColumns($selectSql, $groupBySql ?? '', $orderBySql ?? '', isset($clauseOffsets['HAVING']) ? self::clauseText($tail, $clauseOffsets, 'HAVING') : '');
        if (isset($clauseOffsets['WHERE'])) {
            $requiredSourceColumns = array_values(array_unique(array_merge(
                $requiredSourceColumns,
                self::requiredSourceColumns(self::clauseText($tail, $clauseOffsets, 'WHERE')),
            )));
        }
        $source = self::sourcePlan(
            $fromSql,
            $tables,
            $jsonConstraints,
            self::jsonTableErrorBoundaryColumns($where),
            $outerRow,
            $where,
            $requiredSourceColumns,
            self::requiredWildcardPrefixes($selectSql),
        );
        $select = self::selectList($selectSql, $tables);
        $select = self::annotateWildcardColumns($select, self::wildcardAnnotationRows($source));
        $select = self::liftOuterAggregateScalarSubqueries($select, $source['from']);
        $plan = [
            'from' => $source['from'],
            'select' => $select,
        ];
        if (isset($source['sourceAlias']) && is_string($source['sourceAlias']) && $source['sourceAlias'] !== '') {
            $plan['sourceAlias'] = $source['sourceAlias'];
        }
        if ($distinct) {
            $plan['distinct'] = true;
            $distinctCollations = self::selectListCollations($select);
            if ($distinctCollations !== []) {
                $plan['distinctCollations'] = $distinctCollations;
            }
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
        $having = isset($clauseOffsets['HAVING'])
            ? self::predicate(self::clauseText($tail, $clauseOffsets, 'HAVING'), $tables)
            : null;
        $aggregateExpressions = [];
        if ($having !== null) {
            array_push($aggregateExpressions, ...self::predicateExpressions($having));
        }
        if ($orderBySql !== null) {
            array_push($aggregateExpressions, ...self::orderByExpressions($orderBySql, $tables));
        }
        $specificAggregates = self::needsSpecificAggregateSummaries($select, $aggregateExpressions);

        $implicitAggregate = false;
        if ($groupBySql !== null) {
            $groupBy = self::groupBy($groupBySql, $select, $aggregateExpressions, $specificAggregates);
            if ($having !== null) {
                $groupBy['having'] = self::rewriteAggregatePredicate(
                    $having,
                    $groupBy['valueColumn'],
                    $specificAggregates,
                );
            }
            $plan['groupBy'] = $groupBy;
            $plan['select'] = self::rewriteAggregateSelect($select, $groupBy['valueColumn'], $specificAggregates);
        } elseif (self::selectHasAggregate($select) || $having !== null) {
            $implicitAggregate = true;
            $groupBy = self::implicitAggregateGroup($select, $aggregateExpressions, $specificAggregates);
            if ($having !== null) {
                $groupBy['having'] = self::rewriteAggregatePredicate(
                    $having,
                    $groupBy['valueColumn'],
                    $specificAggregates,
                );
            }
            $plan['groupBy'] = $groupBy;
            $plan['select'] = self::rewriteAggregateSelect($select, $groupBy['valueColumn'], $specificAggregates);
        }
        if ($orderBySql !== null && !$implicitAggregate) {
            $plan['orderBy'] = self::orderBy(
                $orderBySql,
                $plan['select'],
                isset($plan['groupBy']) ? $plan['groupBy']['valueColumn'] : null,
                isset($plan['groupBy']),
                $tables,
                $specificAggregates,
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

    private static function stripSqlComments(string $sql): string
    {
        $result = '';
        $length = strlen($sql);
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote !== null) {
                $result .= $char;
                if ($char === $quote) {
                    if (($quote === "'" || $quote === '"') && $next === $quote) {
                        $result .= $next;
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
                $result .= $char;
                continue;
            }

            if ($char === ':' || $char === '@' || $char === '$') {
                $token = self::namedParameterToken($sql, $i);
                if ($token !== null) {
                    $result .= $token;
                    $i += strlen($token) - 1;
                    continue;
                }
            }

            if ($char === '-' && $next === '-') {
                $i += 2;
                while ($i < $length && !in_array($sql[$i], ["\n", "\r"], true)) {
                    $i++;
                }
                $result .= ' ';
                if ($i < $length) {
                    $result .= $sql[$i];
                }
                continue;
            }

            if ($char === '/' && $next === '*') {
                $i += 2;
                while ($i < $length - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                if ($i >= $length - 1) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL block comment is unterminated');
                }
                $i++;
                $result .= ' ';
                continue;
            }

            $result .= $char;
        }

        if ($quote !== null) {
            throw new \InvalidArgumentException('SQLite SELECT SQL string literal is unterminated');
        }

        return $result;
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

        [$selectSql, $distinct] = self::selectModifier($selectSql);
        if ($selectSql === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL needs select list');
        }
        $namedWindows = isset($clauseOffsets['WINDOW'])
            ? self::namedWindowDefinitions(self::clauseText($sql, $clauseOffsets, 'WINDOW'))
            : [];
        if ($namedWindows !== []) {
            $selectSql = self::expandNamedWindowReferences($selectSql, $namedWindows);
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

        $specificAggregates = self::needsSpecificAggregateSummaries($select);
        $implicitAggregate = self::selectHasAggregate($select);

        $plan = [
            'from' => [[]],
            'select' => $select,
        ];
        if ($implicitAggregate) {
            $groupBy = self::implicitAggregateGroup($select, [], $specificAggregates);
            $plan['groupBy'] = $groupBy;
            $plan['select'] = self::rewriteAggregateSelect($select, $groupBy['valueColumn'], $specificAggregates);
        }
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
                false,
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
     * @return array{0:string,1:bool}
     */
    private static function selectModifier(string $selectSql): array
    {
        $selectSql = trim($selectSql);
        if (preg_match('/^distinct(?:\s+|\(|$)/i', $selectSql) === 1) {
            return [trim(substr($selectSql, 8)), true];
        }
        if (preg_match('/^all(?:\s+|$)/i', $selectSql) === 1) {
            return [trim(substr($selectSql, 3)), false];
        }

        return [$selectSql, false];
    }

    /**
     * @return array{from:list<array<string,mixed>>,select:list<array<string,string>>}
     */
    private static function valuesSelectPlan(string $sql): array
    {
        $rows = self::executeValuesClause($sql);
        $first = $rows[0] ?? null;
        if ($first === null) {
            throw new \InvalidArgumentException('SQLite SELECT SQL VALUES clause needs at least one row');
        }

        $select = [];
        foreach (array_keys($first) as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL VALUES column name is malformed');
            }
            $select[] = ['type' => 'column', 'name' => $column];
        }

        return [
            'from' => $rows,
            'select' => $select,
        ];
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
        $namedParameterIndexes = [];
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
                    $index = self::explicitParameterIndex($token);
                    $positionalIndex = max($positionalIndex, $index + 1);
                    $explicit = true;
                }
                if (!$explicit && $index > self::MAX_VARIABLE_NUMBER) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL has too many SQL variables');
                }
                $result .= self::parameterLiteral(self::parameterValue($parameters, $index, $token, $explicit));
                $i = $start - 1;
                continue;
            }

            if ($char === ':' || $char === '@' || $char === '$') {
                $token = self::namedParameterToken($sql, $i);
                if ($token !== null) {
                    if (!array_key_exists($token, $namedParameterIndexes)) {
                        if ($positionalIndex > self::MAX_VARIABLE_NUMBER) {
                            throw new \InvalidArgumentException('SQLite SELECT SQL has too many SQL variables');
                        }
                        $namedParameterIndexes[$token] = $positionalIndex++;
                    }
                    $result .= self::parameterLiteral(self::parameterValue(
                        $parameters,
                        $token,
                        $token,
                        false,
                        $namedParameterIndexes[$token],
                    ));
                    $i += strlen($token) - 1;
                    continue;
                }
            }

            $result .= $char;
        }
        if ($quote) {
            throw new \InvalidArgumentException('SQLite SELECT SQL has unterminated string literal');
        }

        return $result;
    }

    private static function explicitParameterIndex(string $token): int
    {
        $digits = substr($token, 1);
        $normalized = ltrim($digits, '0');
        if ($normalized === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL variable number must be between ?1 and ?' . self::MAX_VARIABLE_NUMBER);
        }

        $max = (string) self::MAX_VARIABLE_NUMBER;
        if (strlen($normalized) > strlen($max) || (strlen($normalized) === strlen($max) && strcmp($normalized, $max) > 0)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL variable number must be between ?1 and ?' . self::MAX_VARIABLE_NUMBER);
        }

        return (int) $normalized;
    }

    private static function namedParameterToken(string $sql, int $offset): ?string
    {
        $prefix = $sql[$offset] ?? '';
        if ($prefix === '$') {
            return self::dollarParameterToken($sql, $offset);
        }
        if ($prefix !== ':' && $prefix !== '@') {
            return null;
        }

        $length = strlen($sql);
        $end = $offset + 1;
        if ($end >= $length || !self::isParameterNameByte($sql[$end])) {
            return null;
        }

        while ($end < $length && self::isParameterNameByte($sql[$end])) {
            $end++;
        }

        return substr($sql, $offset, $end - $offset);
    }

    private static function dollarParameterToken(string $sql, int $offset): ?string
    {
        $length = strlen($sql);
        $end = $offset + 1;
        if ($end >= $length) {
            return null;
        }

        $hasName = false;
        while ($end < $length) {
            $char = $sql[$end];
            if (self::isParameterNameByte($char)) {
                $hasName = true;
                $end++;
                continue;
            }
            if ($char === ':' && ($sql[$end + 1] ?? null) === ':') {
                $hasName = true;
                $end += 2;
                continue;
            }

            break;
        }
        if (!$hasName) {
            return null;
        }

        if (($sql[$end] ?? null) === '(') {
            $suffixEnd = self::parameterSuffixEnd($sql, $end);
            if ($suffixEnd === null) {
                return null;
            }
            $end = $suffixEnd;
        }

        return substr($sql, $offset, $end - $offset);
    }

    private static function isParameterNameByte(string $char): bool
    {
        $byte = ord($char);

        return ($byte >= 48 && $byte <= 57)
            || ($byte >= 65 && $byte <= 90)
            || ($byte >= 97 && $byte <= 122)
            || $char === '_'
            || $char === '$'
            || $byte >= 0x80;
    }

    private static function parameterSuffixEnd(string $sql, int $offset): ?int
    {
        $length = strlen($sql);
        $depth = 0;
        $quote = false;
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
                    return $i + 1;
                }
            }
        }

        return null;
    }

    /**
     * @param array<int|string,mixed> $parameters
     */
    private static function parameterValue(array $parameters, int|string $key, string $token, bool $explicit = false, ?int $assignedIndex = null): mixed
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
            $bare = substr($token, 1);
            foreach (array_unique([$token, $bare, ':' . $bare, '@' . $bare, '$' . $bare]) as $candidate) {
                if (array_key_exists($candidate, $parameters)) {
                    return $parameters[$candidate];
                }
            }
            if ($assignedIndex !== null) {
                if (array_key_exists($assignedIndex, $parameters)) {
                    return $parameters[$assignedIndex];
                }
                $zeroBased = $assignedIndex - 1;
                if (array_key_exists($zeroBased, $parameters)) {
                    return $parameters[$zeroBased];
                }
            }
        }

        return null;
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
    private static function compoundSqlPlan(string $sql, array $tables, array $cteNames, ?array $outerRow = null): ?array
    {
        $parts = self::splitCompoundSql($sql);
        if ($parts === null) {
            return null;
        }

        $lastIndex = count($parts['arms']) - 1;
        $finalArmWasValues = preg_match('/^values(?:\s+|\()/i', trim($parts['arms'][$lastIndex])) === 1;
        [$lastSql, $orderSql, $limitSql] = self::stripCompoundTailClauses($parts['arms'][$lastIndex]);
        if ($finalArmWasValues && ($orderSql !== null || $limitSql !== null)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL compound ORDER BY/LIMIT is not supported after a final VALUES arm');
        }
        $parts['arms'][$lastIndex] = $lastSql;

        $arms = [];
        foreach ($parts['arms'] as $index => $armSql) {
            if ($index !== $lastIndex) {
                [, $armOrderSql, $armLimitSql] = self::stripCompoundTailClauses($armSql);
                if ($armOrderSql !== null) {
                    $operator = strtoupper((string) ($parts['operators'][$index] ?? 'compound'));
                    throw new \InvalidArgumentException("ORDER BY clause should come after {$operator} not before");
                }
                if ($armLimitSql !== null) {
                    $operator = strtoupper((string) ($parts['operators'][$index] ?? 'compound'));
                    throw new \InvalidArgumentException("LIMIT clause should come after {$operator} not before");
                }
            }
            if (self::splitCompoundSql($armSql) !== null) {
                throw new \InvalidArgumentException('SQLite SELECT SQL compound arms cannot contain nested compound SELECT text');
            }
            $arm = preg_match('/^values\s+/i', trim($armSql)) === 1
                ? self::valuesSelectPlan($armSql)
                : self::plan($armSql, $tables, [], $outerRow);
            if ($outerRow !== null) {
                $arm = self::expandCorrelatedPlan($arm, $tables, $outerRow);
            }
            $arms[] = $arm;
        }
        $expectedColumnCount = null;
        foreach ($arms as $index => $arm) {
            $columnCount = count(self::compoundOutputColumns($arm));
            if ($expectedColumnCount === null) {
                $expectedColumnCount = $columnCount;
                continue;
            }
            if ($columnCount !== $expectedColumnCount) {
                $operator = strtoupper((string) ($parts['operators'][$index - 1] ?? 'compound'));
                throw new \InvalidArgumentException("SELECTs to the left and right of {$operator} do not have the same number of result columns");
            }
        }

        $plan = [
            'compound' => [
                'operators' => $parts['operators'],
                'arms' => $arms,
            ],
        ];
        if ($orderSql !== null) {
            $plan['compound']['orderBy'] = self::compoundOrderBy($orderSql, array_map(
                static fn (array $arm): array => isset($arm['select']) && is_array($arm['select']) ? $arm['select'] : [],
                $arms,
            ));
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
     * @param list<list<array<string,mixed>>> $selectArms
     * @return list<array{column:string,direction?:string,collation?:string,nulls?:string}>
     */
    private static function compoundOrderBy(string $sql, array $selectArms): array
    {
        $select = $selectArms[0] ?? [];
        $columns = [];
        $ordinal = 1;
        foreach ($select as $term) {
            if (!is_array($term)) {
                continue;
            }
            foreach (self::compoundProjectionColumns($term, $ordinal) as $column) {
                $columns[$ordinal] = $column;
                $ordinal++;
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
                $matched = self::compoundOrderByMatchedColumn($expression, $columns, $selectArms);
                if ($matched === null) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL compound ORDER BY term does not match a result column');
                } else {
                    $column = $matched;
                }
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
     * @param array<int,string> $columns
     * @param list<list<array<string,mixed>>> $selectArms
     */
    private static function compoundOrderByMatchedColumn(string $sql, array $columns, array $selectArms): ?string
    {
        $sql = self::unquoteIdentifier($sql) ?? $sql;
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/', $sql) === 1) {
            foreach ($selectArms as $select) {
                $ordinal = 1;
                foreach ($select as $term) {
                    if (!is_array($term)) {
                        continue;
                    }
                    $termColumns = self::compoundProjectionColumns($term, $ordinal);
                    $outputColumn = self::compoundArmOutputColumn($term, $ordinal);
                    foreach ($termColumns as $column) {
                        if ($column === $sql && in_array($column, $columns, true)) {
                            return $column;
                        }
                        if (str_contains($sql, '.') && substr($sql, strrpos($sql, '.') + 1) === $column && in_array($column, $columns, true)) {
                            return $column;
                        }
                        if (str_contains($column, '.') && substr($column, strrpos($column, '.') + 1) === $sql && in_array($column, $columns, true)) {
                            return $column;
                        }
                    }
                    if ($outputColumn === $sql && isset($columns[$ordinal])) {
                        return $columns[$ordinal];
                    }
                    if (str_contains($sql, '.') && substr($sql, strrpos($sql, '.') + 1) === $outputColumn && isset($columns[$ordinal])) {
                        return $columns[$ordinal];
                    }
                    if (str_contains($outputColumn, '.') && substr($outputColumn, strrpos($outputColumn, '.') + 1) === $sql && isset($columns[$ordinal])) {
                        return $columns[$ordinal];
                    }
                    $ordinal += count($termColumns);
                }
            }

            return null;
        }

        $expression = self::valueExpression($sql);
        foreach ($selectArms as $select) {
            foreach ($select as $index => $term) {
                if (!is_array($term) || !isset($columns[$index + 1])) {
                    continue;
                }
                if (self::compoundOrderByExpressionsMatch($expression, self::projectionExpressionForComparison($term))) {
                    return $columns[$index + 1];
                }
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $term
     */
    private static function compoundArmOutputColumn(array $term, int $ordinal): string
    {
        if (isset($term['alias']) && is_string($term['alias']) && $term['alias'] !== '') {
            return $term['alias'];
        }
        if (($term['type'] ?? null) === 'collate' && isset($term['operand']) && is_array($term['operand'])) {
            return self::compoundArmOutputColumn($term['operand'], $ordinal);
        }
        if (($term['type'] ?? null) === 'column' && isset($term['name']) && is_string($term['name']) && $term['name'] !== '') {
            return $term['name'];
        }

        return 'expr' . $ordinal;
    }

    /**
     * @param array<string,mixed> $expression
     * @return array<string,mixed>
     */
    private static function projectionExpressionForComparison(array $expression): array
    {
        if (isset($expression['sourceExpression']) && is_array($expression['sourceExpression'])) {
            $expression = $expression['sourceExpression'];
        }
        unset($expression['alias'], $expression['hiddenOrderColumn']);

        return $expression;
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     */
    private static function compoundOrderByExpressionsMatch(array $left, array $right): bool
    {
        return self::normalizedExpressionForComparison($left) === self::normalizedExpressionForComparison($right);
    }

    /**
     * @param array<string,mixed> $expression
     * @return array<string,mixed>
     */
    private static function normalizedExpressionForComparison(array $expression): array
    {
        unset($expression['alias'], $expression['hiddenOrderColumn']);
        ksort($expression);
        foreach ($expression as $key => $value) {
            if (is_array($value)) {
                $expression[$key] = self::normalizeExpressionArray($value);
            }
        }

        return $expression;
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private static function normalizeExpressionArray(array $value): array
    {
        if (!array_is_list($value)) {
            unset($value['alias'], $value['hiddenOrderColumn']);
            ksort($value);
        }
        foreach ($value as $key => $nested) {
            if (is_array($nested)) {
                $value[$key] = self::normalizeExpressionArray($nested);
            }
        }

        return $value;
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
        $columns = null;
        $setCollations = self::compoundSetCollations($compound['arms']);
        foreach ($compound['arms'] as $index => $arm) {
            if (!is_array($arm)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL compound arm plan is malformed');
            }
            $armRows = self::stripHiddenOrderColumns(SQLiteSelectQuery::execute($arm), $arm);
            if ($columns === null) {
                $columns = self::compoundOutputColumns($arm);
            }
            $armRows = self::renameCompoundRows($armRows, $columns);
            if ($rows === null) {
                $rows = $armRows;
                continue;
            }
            if ($rows === [] && $armRows === []) {
                continue;
            }
            $rows = SQLiteSelectCompound::combine($rows, $armRows, (string) $compound['operators'][$index - 1], $setCollations);
        }

        $rows ??= [];
        if (self::compoundUsesDistinctSetOrder($compound['operators'])) {
            $setOrderBy = [];
            $collations = $setCollations;
            foreach ($columns ?? [] as $column) {
                $term = ['column' => $column];
                if (isset($collations[$column]) && is_string($collations[$column])) {
                    $term['collation'] = $collations[$column];
                }
                $setOrderBy[] = $term;
            }
            $rows = SQLiteSelectResult::execute($rows, null, $setOrderBy);
        }

        return SQLiteSelectResult::execute(
            $rows,
            null,
            isset($compound['orderBy']) && is_array($compound['orderBy']) ? $compound['orderBy'] : [],
            isset($compound['limit']) && is_int($compound['limit']) ? $compound['limit'] : null,
            isset($compound['offset']) && is_int($compound['offset']) ? $compound['offset'] : 0,
        );
    }

    /**
     * @param list<mixed> $operators
     */
    private static function compoundUsesDistinctSetOrder(array $operators): bool
    {
        foreach ($operators as $operator) {
            if (strtoupper((string) $operator) !== 'UNION ALL') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    public static function executeCompoundPlanForDiagnostics(array $plan): array
    {
        return self::executeCompoundPlan($plan);
    }

    /**
     * @param array<string,mixed> $arm
     * @return list<string>
     */
    private static function compoundOutputColumns(array $arm): array
    {
        $select = $arm['select'] ?? null;
        if (!is_array($select) || !array_is_list($select)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL compound arm select list is malformed');
        }

        $columns = [];
        foreach ($select as $index => $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL compound arm select term is malformed');
            }
            foreach (self::compoundProjectionColumns($term, $index + 1) as $column) {
                $columns[] = $column;
            }
        }
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite SELECT SQL compound SELECT needs output columns');
        }

        return $columns;
    }

    /**
     * @param array<string,mixed> $term
     * @return list<string>
     */
    private static function compoundProjectionColumns(array $term, int $ordinal): array
    {
        if (($term['type'] ?? null) === 'wildcard') {
            $columns = $term['columns'] ?? null;
            if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
                throw new \InvalidArgumentException('SQLite SELECT SQL compound wildcard projection needs source columns');
            }

            $expanded = [];
            foreach ($columns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite SELECT SQL compound wildcard projection column is malformed');
                }
                $expanded[] = $column;
            }

            return $expanded;
        }

        if (isset($term['alias']) && is_string($term['alias']) && $term['alias'] !== '') {
            return [$term['alias']];
        }
        if (($term['type'] ?? null) === 'column' && isset($term['name']) && is_string($term['name']) && $term['name'] !== '') {
            $name = $term['name'];

            return [str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name];
        }

        return ['expr' . $ordinal];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function renameCompoundRows(array $rows, array $columns): array
    {
        $renamed = [];
        foreach ($rows as $row) {
            if (count($row) !== count($columns)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL compound arm row width does not match the first SELECT result width');
            }
            $combined = array_combine($columns, array_values($row));
            if (!is_array($combined)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL compound arm row width does not match the first SELECT result width');
            }
            $renamed[] = $combined;
        }

        return $renamed;
    }

    /**
     * @param list<mixed> $arms
     * @return array<string,string>
     */
    private static function compoundSetCollations(array $arms): array
    {
        $firstArm = $arms[0] ?? null;
        if (!is_array($firstArm)) {
            return [];
        }

        $columns = self::compoundOutputColumns($firstArm);
        $collations = [];
        foreach ($arms as $arm) {
            if (!is_array($arm)) {
                continue;
            }
            foreach (self::compoundSelectCollationsByOrdinal($arm) as $ordinal => $collation) {
                $column = $columns[$ordinal - 1] ?? null;
                if ($column === null || isset($collations[$column])) {
                    continue;
                }
                $collations[$column] = $collation;
            }
        }

        return $collations;
    }

    /**
     * @param array<string,mixed> $arm
     * @return array<int,string>
     */
    private static function compoundSelectCollationsByOrdinal(array $arm): array
    {
        $select = $arm['select'] ?? null;
        if (!is_array($select) || !array_is_list($select)) {
            return [];
        }

        $collations = [];
        $ordinal = 1;
        foreach ($select as $term) {
            if (!is_array($term)) {
                continue;
            }
            $columns = self::compoundProjectionColumns($term, $ordinal);
            $collation = self::compoundTermCollation($term);
            foreach ($columns as $_column) {
                if ($collation !== null) {
                    $collations[$ordinal] = $collation;
                }
                $ordinal++;
            }
        }

        return $collations;
    }

    /**
     * @param array<string,mixed> $term
     */
    private static function compoundTermCollation(array $term): ?string
    {
        if (($term['type'] ?? null) === 'collate' && isset($term['collation']) && is_string($term['collation']) && $term['collation'] !== '') {
            return strtoupper($term['collation']);
        }
        if (isset($term['sourceExpression']) && is_array($term['sourceExpression'])) {
            return self::compoundTermCollation($term['sourceExpression']);
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return array<string,string>
     */
    private static function selectListCollations(array $select): array
    {
        $collations = [];
        foreach ($select as $index => $term) {
            if (!is_array($term) || ($term['type'] ?? null) !== 'collate' || !isset($term['collation']) || !is_string($term['collation'])) {
                continue;
            }
            $column = self::compoundArmOutputColumn($term, $index + 1);
            $collations[$column] = strtoupper($term['collation']);
            $collations['expr' . ($index + 1)] = strtoupper($term['collation']);
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
        return self::executeRecursiveCteWithTrace($entry, $tables, false)['rows'];
    }

    /**
     * @param array{name:string,columns:list<string>,sql:string} $entry
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{name:string,columns:list<string>,operator:string,rows:list<array<string,mixed>>,trace:list<array<string,mixed>>,skipped:list<array<string,mixed>>,dependencies:list<string>}
     */
    private static function executeRecursiveCteWithTrace(array $entry, array $tables, bool $collectTrace): array
    {
        $name = $entry['name'];
        $compound = self::splitCompoundSql($entry['sql']);
        if ($compound === null || count($compound['arms']) < 2 || count($compound['operators']) < 1) {
            throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} needs anchor and recursive arms");
        }

        $recursiveIndex = null;
        foreach ($compound['arms'] as $index => $armSql) {
            if (self::cteSqlReferencesName($armSql, $name)) {
                $recursiveIndex = $index;
                break;
            }
        }
        if ($recursiveIndex === null || $recursiveIndex === 0) {
            throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} needs at least one non-recursive anchor arm");
        }

        $operator = strtoupper($compound['operators'][$recursiveIndex - 1]);
        if ($operator !== 'UNION ALL' && $operator !== 'UNION') {
            throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} supports UNION ALL or UNION");
        }

        $anchorArms = array_slice($compound['arms'], 0, $recursiveIndex);
        $anchorOperators = array_slice($compound['operators'], 0, max(0, $recursiveIndex - 1));
        $recursiveArms = array_slice($compound['arms'], $recursiveIndex);
        foreach ($anchorArms as $anchorSql) {
            if (self::cteSqlReferencesName($anchorSql, $name)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} anchor arm cannot reference itself");
            }
        }
        foreach ($recursiveArms as $index => $recursiveArm) {
            if (!self::cteSqlReferencesName($recursiveArm, $name)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} recursive arms must be contiguous");
            }
            $operatorIndex = $recursiveIndex + $index - 1;
            if (isset($compound['operators'][$operatorIndex])) {
                $armOperator = strtoupper($compound['operators'][$operatorIndex]);
                if ($armOperator !== $operator) {
                    throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} recursive arms must use the same UNION operator");
                }
            }
        }

        $lastRecursiveIndex = count($recursiveArms) - 1;
        [$recursiveArms[$lastRecursiveIndex], $orderSql, $limitSql] = self::stripCompoundTailClauses($recursiveArms[$lastRecursiveIndex]);

        $anchorRows = self::executeCteCompoundArms($anchorArms, $anchorOperators, $tables);
        $columns = $entry['columns'] !== []
            ? $entry['columns']
            : ($anchorRows === [] ? [] : array_keys($anchorRows[0]));
        if ($columns === []) {
            throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} needs anchor columns");
        }
        $queueOrder = $orderSql !== null ? self::recursiveQueueOrder('SELECT 1 ORDER BY ' . $orderSql, $columns) : [];
        [$queueLimit, $queueOffset] = $limitSql !== null ? self::limitOffset($limitSql, $tables) : [null, 0];
        if ($queueLimit < 0) {
            $queueLimit = null;
        }
        if ($queueOffset < 0) {
            throw new \InvalidArgumentException('SQLite SELECT SQL recursive CTE OFFSET must be non-negative');
        }
        $queue = self::normalizeRecursiveRows($anchorRows, $columns, $name);
        if ($operator === 'UNION') {
            $queue = self::deduplicateRecursiveRows($queue);
        }
        if ($queueOrder !== []) {
            $queue = self::sortRecursiveQueue($queue, $queueOrder);
        }
        $rows = [];
        $seen = [];
        foreach ($queue as $row) {
            $seen[self::recursiveRowKey($row)] = true;
        }
        $trace = [];
        $skipped = [];

        for ($iteration = 0; $iteration < 1000 && $queue !== []; $iteration++) {
            $queueBefore = $queue;
            $current = array_shift($queue);
            if (!is_array($current)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} queue row is malformed");
            }
            $emitted = false;
            if ($queueOffset > 0) {
                $queueOffset--;
            } elseif ($queueLimit !== 0) {
                $rows[] = $current;
                $emitted = true;
                if ($queueLimit !== null) {
                    $queueLimit--;
                }
            }
            if ($queueLimit === 0) {
                if ($collectTrace) {
                    $trace[] = [
                        'iteration' => $iteration,
                        'current' => $current,
                        'emitted' => $emitted,
                        'queue_before' => $queueBefore,
                        'generated' => [],
                        'accepted_next' => [],
                        'skipped_duplicates' => [],
                        'queue_after' => [],
                        'limit_remaining' => $queueLimit,
                        'offset_remaining' => $queueOffset,
                    ];
                }
                $queue = [];
                break;
            }
            $stepTables = $tables;
            $stepTables[$name] = [$current];
            $stepRows = [];
            foreach ($recursiveArms as $recursiveSql) {
                foreach (self::normalizeRecursiveRows(self::executeCteArm($recursiveSql, $stepTables), $columns, $name) as $row) {
                    $stepRows[] = $row;
                }
            }
            $acceptedNext = [];
            $skippedDuplicates = [];
            foreach ($stepRows as $row) {
                $key = self::recursiveRowKey($row);
                if ($operator === 'UNION' && isset($seen[$key])) {
                    if ($collectTrace) {
                        $skippedRow = [
                            'iteration' => $iteration,
                            'current' => $current,
                            'row' => $row,
                            'reason' => 'union-duplicate-cycle',
                        ];
                        $skipped[] = $skippedRow;
                        $skippedDuplicates[] = $row;
                    }
                    continue;
                }
                $queue[] = $row;
                $acceptedNext[] = $row;
                $seen[$key] = true;
            }
            if ($queueOrder !== []) {
                $queue = self::sortRecursiveQueue($queue, $queueOrder);
            }
            if ($collectTrace) {
                $trace[] = [
                    'iteration' => $iteration,
                    'current' => $current,
                    'emitted' => $emitted,
                    'queue_before' => $queueBefore,
                    'generated' => $stepRows,
                    'accepted_next' => $acceptedNext,
                    'skipped_duplicates' => $skippedDuplicates,
                    'queue_after' => $queue,
                    'limit_remaining' => $queueLimit,
                    'offset_remaining' => $queueOffset,
                ];
            }
        }
        if ($queue !== []) {
            throw new \InvalidArgumentException("SQLite SELECT SQL recursive CTE {$name} exceeded iteration limit");
        }

        return [
            'name' => $name,
            'columns' => $columns,
            'operator' => $operator,
            'rows' => $rows,
            'trace' => $trace,
            'skipped' => $skipped,
            'dependencies' => ['sqlite-recursive-cte-current-row', 'sqlite-recursive-union-cycle-dedup'],
        ];
    }

    /**
     * @param list<string> $arms
     * @param list<string> $operators
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array<string,mixed>>
     */
    private static function executeCteCompoundArms(array $arms, array $operators, array $tables): array
    {
        $rows = null;
        foreach ($arms as $index => $armSql) {
            $armRows = self::executeCteArm($armSql, $tables);
            if ($rows === null) {
                $rows = $armRows;
                continue;
            }
            $operator = strtoupper($operators[$index - 1] ?? '');
            if ($operator !== 'UNION ALL' && $operator !== 'UNION' && $operator !== 'INTERSECT' && $operator !== 'EXCEPT') {
                throw new \InvalidArgumentException('SQLite SELECT SQL recursive CTE anchor compound operator is malformed');
            }
            $rows = SQLiteSelectCompound::combine($rows, $armRows, $operator);
        }

        return $rows ?? [];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{0:string,1:?int,2:int}
     */
    private static function recursiveQueueLimitOffset(string $recursiveSql, array $tables): array
    {
        $limitOffset = self::keywordOffset($recursiveSql, 'LIMIT');
        if ($limitOffset === null) {
            return [$recursiveSql, null, 0];
        }

        [$limit, $offset] = self::limitOffset(trim(substr($recursiveSql, $limitOffset + strlen('LIMIT'))), $tables);
        if ($limit < 0) {
            $limit = null;
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite SELECT SQL recursive CTE OFFSET must be non-negative');
        }

        return [trim(substr($recursiveSql, 0, $limitOffset)), $limit, $offset];
    }

    /**
     * @param list<string> $columns
     * @return list<array{column:string,direction:string}>
     */
    private static function recursiveQueueOrder(string $recursiveSql, array $columns): array
    {
        $orderOffset = self::keywordOffset($recursiveSql, 'ORDER BY');
        if ($orderOffset === null) {
            return [];
        }

        $limitOffset = self::keywordOffset($recursiveSql, 'LIMIT');
        $orderSql = $limitOffset !== null && $limitOffset > $orderOffset
            ? trim(substr($recursiveSql, $orderOffset + strlen('ORDER BY'), $limitOffset - ($orderOffset + strlen('ORDER BY'))))
            : trim(substr($recursiveSql, $orderOffset + strlen('ORDER BY')));
        if ($orderSql === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL recursive CTE ORDER BY term cannot be empty');
        }

        $order = [];
        foreach (self::splitTopLevel($orderSql, ',') as $term) {
            [$expression, $direction] = self::orderByExpressionDirection($term);
            if (preg_match('/^[1-9][0-9]*$/', $expression) === 1) {
                $ordinal = (int) $expression;
                if (!isset($columns[$ordinal - 1])) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL recursive CTE ORDER BY ordinal is out of range');
                }
                $column = $columns[$ordinal - 1];
            } else {
                self::assertIdentifier($expression, 'SQLite SELECT SQL recursive CTE ORDER BY column');
                $column = str_contains($expression, '.') ? substr($expression, strrpos($expression, '.') + 1) : $expression;
                if (!in_array($column, $columns, true)) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL recursive CTE ORDER BY column is not in the result set');
                }
            }
            $order[] = ['column' => $column, 'direction' => $direction ?? 'ASC'];
        }

        return $order;
    }

    /**
     * @param list<array<string,mixed>> $queue
     * @param list<array{column:string,direction:string}> $order
     * @return list<array<string,mixed>>
     */
    private static function sortRecursiveQueue(array $queue, array $order): array
    {
        $indexed = [];
        foreach ($queue as $index => $row) {
            $indexed[] = ['index' => $index, 'row' => $row];
        }
        usort($indexed, static function (array $left, array $right) use ($order): int {
            foreach ($order as $term) {
                $comparison = self::compareRecursiveQueueValues($left['row'][$term['column']] ?? null, $right['row'][$term['column']] ?? null);
                if ($comparison === 0) {
                    continue;
                }

                return $term['direction'] === 'DESC' ? -$comparison : $comparison;
            }

            return $left['index'] <=> $right['index'];
        });

        return array_map(static fn (array $entry): array => $entry['row'], $indexed);
    }

    private static function compareRecursiveQueueValues(mixed $left, mixed $right): int
    {
        $leftRank = self::recursiveQueueSortRank($left);
        $rightRank = self::recursiveQueueSortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($left === null || $right === null) {
            return 0;
        }
        if ((is_int($left) || is_float($left) || is_bool($left)) && (is_int($right) || is_float($right) || is_bool($right))) {
            return $left <=> $right;
        }
        if ($left instanceof SQLiteBlobValue && $right instanceof SQLiteBlobValue) {
            return strcmp($left->bytes, $right->bytes);
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function recursiveQueueSortRank(mixed $value): int
    {
        return match (true) {
            $value === null => 0,
            is_int($value) || is_float($value) || is_bool($value) => 1,
            is_string($value) => 2,
            $value instanceof SQLiteBlobValue => 3,
            default => throw new \InvalidArgumentException('SQLite SELECT SQL recursive CTE ORDER BY values must be scalar, BLOB, or NULL'),
        };
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
        return SQLiteSelectCompound::rowValueKey(array_values($row));
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
            if (!preg_match('/^(select|values)\s+/i', $mainSql)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL WITH clause needs a trailing SELECT or VALUES');
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
     * @return list<string>
     */
    private static function requiredSourceColumns(string ...$sqlParts): array
    {
        $keywords = array_fill_keys([
            'as', 'asc', 'by', 'case', 'collate', 'count', 'desc', 'distinct', 'else', 'end',
            'from', 'group', 'having', 'limit', 'max', 'min', 'null', 'offset', 'order',
            'select', 'sum', 'then', 'where', 'when',
        ], true);
        $columns = [];
        foreach ($sqlParts as $sql) {
            if ($sql === '') {
                continue;
            }
            preg_match_all('/[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?/', $sql, $matches);
            foreach ($matches[0] as $identifier) {
                $column = str_contains($identifier, '.') ? substr($identifier, strrpos($identifier, '.') + 1) : $identifier;
                $normalized = strtolower($column);
                if (isset($keywords[$normalized])) {
                    continue;
                }
                $columns[$normalized] = $column;
            }
        }

        return array_values($columns);
    }

    /**
     * @return list<string>
     */
    private static function requiredWildcardPrefixes(string $selectSql): array
    {
        preg_match_all('/(?:^|,)\s*([A-Za-z_][A-Za-z0-9_]*)\s*\.\s*\*/', $selectSql, $matches);
        $prefixes = [];
        foreach ($matches[1] as $prefix) {
            $prefixes[strtolower($prefix)] = $prefix;
        }

        return array_values($prefixes);
    }

    /**
     * @param list<string> $requiredWildcardPrefixes
     */
    private static function wildcardRequiresAlias(array $requiredWildcardPrefixes, string $alias): bool
    {
        return in_array(strtolower($alias), array_map('strtolower', $requiredWildcardPrefixes), true);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function executeValuesClause(string $sql): array
    {
        $sql = trim($sql);
        if (preg_match('/^values(?:\s+|\()/i', $sql) !== 1) {
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
     * @param list<string> $jsonErrorBoundaryColumns
     * @return array{from:list<array<string,mixed>>,joins:list<array<string,mixed>>}
     */
    private static function sourcePlan(string $sql, array $tables, array $jsonConstraints = [], array $jsonErrorBoundaryColumns = [], ?array $outerRow = null, ?array $wherePredicate = null, array $requiredColumns = [], array $requiredWildcardPrefixes = []): array
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
        $base = self::tableReference($baseSql, $tables, $jsonConstraints, $jsonErrorBoundaryColumns, $outerRow, $requiredColumns);
        $joins = [];

        if ($joinOffset !== null) {
            $joinSql = trim(substr($sql, $joinOffset));
            $currentRows = self::qualifiedSourceRows($base);
            while ($joinSql !== '') {
                [$join, $joinSql] = self::consumeJoin($joinSql, $tables, $currentRows, $jsonErrorBoundaryColumns, $outerRow, $wherePredicate);
                $joins[] = $join;
                $currentRows = [array_fill_keys(array_merge(self::collectColumns($currentRows), self::collectColumns($join['rows'])), null)];
            }
        }

        if ($joins === [] && isset($base['dynamicRows']) && is_callable($base['dynamicRows'])) {
            $dynamicRow = $outerRow === null ? [] : self::qualifyOuterRowForCorrelation($outerRow, $tables);
            $source = [
                'from' => self::unqualifiedRows($base['dynamicRows']($dynamicRow), $base['alias']),
                'joins' => [],
            ];
            if ($outerRow !== null || ($base['name'] ?? null) === 'subquery') {
                $source['sourceAlias'] = $base['alias'];
            }

            return $source;
        }

        $source = [
            'from' => $joins === []
                ? (((($base['qualifyRows'] ?? false) === true) || self::predicateReferencesAlias($wherePredicate, $base['alias']) || self::wildcardRequiresAlias($requiredWildcardPrefixes, $base['alias']))
                    ? self::qualifiedSourceRows($base)
                    : self::unqualifiedSourceRows($base))
                : self::qualifiedSourceRows($base),
            'joins' => $joins,
        ];
        if ($outerRow !== null || ($base['name'] ?? null) === 'subquery') {
            $source['sourceAlias'] = $base['alias'];
        }

        return $source;
    }

    /**
     * @param mixed $predicate
     */
    private static function predicateReferencesAlias(mixed $predicate, string $alias): bool
    {
        if (!is_array($predicate) || $alias === '') {
            return false;
        }
        if (($predicate['type'] ?? null) === 'column' && isset($predicate['name']) && is_string($predicate['name'])) {
            return str_starts_with(strtolower($predicate['name']), strtolower($alias) . '.');
        }
        if (isset($predicate['column']) && is_string($predicate['column'])) {
            return str_starts_with(strtolower($predicate['column']), strtolower($alias) . '.');
        }
        foreach ($predicate as $value) {
            if (self::predicateReferencesAlias($value, $alias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{name:string,alias:string,rows:list<array<string,mixed>>} $source
     * @return list<array<string,mixed>>
     */
    private static function qualifiedSourceRows(array $source): array
    {
        return ($source['name'] === 'json_each' || $source['name'] === 'json_tree')
            ? self::qualifiedJsonRows($source['rows'], $source['alias'])
            : self::qualifiedRows($source['rows'], $source['alias']);
    }

    /**
     * @param array{name:string,alias:string,rows:list<array<string,mixed>>} $source
     * @return list<array<string,mixed>>
     */
    private static function unqualifiedSourceRows(array $source): array
    {
        return ($source['name'] === 'json_each' || $source['name'] === 'json_tree')
            ? self::unqualifiedJsonRows($source['rows'])
            : $source['rows'];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $jsonConstraints
     * @param list<string> $jsonErrorBoundaryColumns
     * @return array{name:string,alias:string,rows:list<array<string,mixed>>}
     */
    private static function tableReference(string $sql, array $tables, array $jsonConstraints = [], array $jsonErrorBoundaryColumns = [], ?array $outerRow = null, array $requiredColumns = []): array
    {
        $valuesTable = self::valuesTableReference($sql);
        if ($valuesTable !== null) {
            return $valuesTable;
        }

        $derivedTable = self::derivedTableReference($sql, $tables, $outerRow, $requiredColumns);
        if ($derivedTable !== null) {
            return $derivedTable;
        }

        $joinGroup = self::parenthesizedJoinReference($sql, $tables, $jsonErrorBoundaryColumns, $outerRow);
        if ($joinGroup !== null) {
            return $joinGroup;
        }

        $parenthesizedTable = self::parenthesizedTableReference($sql, $tables, $jsonConstraints, $jsonErrorBoundaryColumns, $outerRow);
        if ($parenthesizedTable !== null) {
            return $parenthesizedTable;
        }

        $jsonTable = self::jsonTableReference($sql, $jsonErrorBoundaryColumns, $jsonConstraints);
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
        self::assertIdentifier($name, 'SQLite SELECT SQL table name');
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
        $resolvedTable = self::resolveTableRows($name, $tables);
        if ($resolvedTable === null) {
            throw new \InvalidArgumentException("SQLite SELECT SQL table {$name} is not available");
        }

        $alias = $resolvedTable['alias'];
        if (isset($parts[1])) {
            if (strcasecmp($parts[1], 'AS') === 0) {
                $alias = $parts[2];
            } else {
                $alias = $parts[1];
            }
            self::assertBareIdentifier($alias, 'SQLite SELECT SQL table alias');
        }

        return ['name' => $resolvedTable['name'], 'alias' => $alias, 'rows' => $resolvedTable['rows']];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{name:string,alias:string,rows:list<array<string,mixed>>}|null
     */
    private static function resolveTableRows(string $name, array $tables): ?array
    {
        $normalized = strtolower($name);
        if (str_contains($normalized, '.')) {
            if (array_key_exists($name, $tables) && is_array($tables[$name]) && array_is_list($tables[$name])) {
                return ['name' => $name, 'alias' => substr($name, strrpos($name, '.') + 1), 'rows' => $tables[$name]];
            }

            foreach ($tables as $tableName => $rows) {
                if (strtolower((string) $tableName) === $normalized && is_array($rows) && array_is_list($rows)) {
                    return ['name' => (string) $tableName, 'alias' => substr((string) $tableName, strrpos((string) $tableName, '.') + 1), 'rows' => $rows];
                }
            }

            return null;
        }

        foreach (['temp.' . $name, 'main.' . $name] as $qualifiedName) {
            foreach ($tables as $tableName => $rows) {
                if (strtolower((string) $tableName) === strtolower($qualifiedName) && is_array($rows) && array_is_list($rows)) {
                    return ['name' => (string) $tableName, 'alias' => $name, 'rows' => $rows];
                }
            }
        }

        if (array_key_exists($name, $tables) && is_array($tables[$name]) && array_is_list($tables[$name])) {
            return ['name' => $name, 'alias' => $name, 'rows' => $tables[$name]];
        }

        foreach ($tables as $tableName => $rows) {
            $tableName = (string) $tableName;
            if (!is_array($rows) || !array_is_list($rows) || !str_contains($tableName, '.')) {
                continue;
            }
            if (strcasecmp(substr($tableName, strrpos($tableName, '.') + 1), $name) === 0) {
                return ['name' => $tableName, 'alias' => $name, 'rows' => $rows];
            }
        }

        return null;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $jsonConstraints
     * @param list<string> $jsonErrorBoundaryColumns
     * @return array{name:string,alias:string,rows:list<array<string,mixed>>}|null
     */
    private static function parenthesizedTableReference(string $sql, array $tables, array $jsonConstraints = [], array $jsonErrorBoundaryColumns = [], ?array $outerRow = null): ?array
    {
        $sql = trim($sql);
        if (!str_starts_with($sql, '(')) {
            return null;
        }

        [$body, $offset] = self::consumeParenthesized($sql, 0);
        $body = trim($body);
        $tail = trim(substr($sql, $offset));
        if (
            $body === ''
            || preg_match('/^(?:select|with|values)\s+/i', $body) === 1
            || self::firstJoinOffset($body) !== null
            || count(self::splitTopLevel($body, ',')) > 1
        ) {
            return null;
        }

        $reference = self::tableReference($body, $tables, $jsonConstraints, $jsonErrorBoundaryColumns, $outerRow);
        if ($tail === '') {
            return $reference;
        }

        [$alias, $columns] = self::parenthesizedJoinAlias($tail);
        if ($columns !== []) {
            throw new \InvalidArgumentException('SQLite SELECT SQL parenthesized table column aliases are not supported');
        }

        $reference['alias'] = $alias;

        return $reference;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $jsonErrorBoundaryColumns
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $jsonConstraints
     * @return array{name:string,alias:string,rows:list<array<string,mixed>>}|null
     */
    private static function parenthesizedJoinReference(string $sql, array $tables, array $jsonErrorBoundaryColumns = [], ?array $outerRow = null): ?array
    {
        $sql = trim($sql);
        if (!str_starts_with($sql, '(')) {
            return null;
        }

        [$body, $offset] = self::consumeParenthesized($sql, 0);
        $body = trim($body);
        if (
            $body === ''
            || preg_match('/^(?:select|with|values)\s+/i', $body) === 1
            || (self::firstJoinOffset($body) === null && count(self::splitTopLevel($body, ',')) < 2)
        ) {
            return null;
        }

        [$alias, $columns] = self::parenthesizedJoinAlias(trim(substr($sql, $offset)));
        if ($columns !== []) {
            throw new \InvalidArgumentException('SQLite SELECT SQL parenthesized join column aliases are not supported');
        }

        $source = self::sourcePlan($body, $tables, [], $jsonErrorBoundaryColumns, $outerRow);
        $rows = SQLiteSelectQuery::execute([
            'from' => $source['from'],
            'joins' => $source['joins'],
        ]);
        $hasExplicitAlias = trim(substr($sql, $offset)) !== '';
        $columns = self::collectColumns($rows);
        if ($columns === []) {
            $columns = self::collectColumns(self::wildcardAnnotationRows($source));
        }
        if ($hasExplicitAlias) {
            $columns = self::unqualifiedDerivedColumnNames($columns);
        }

        return [
            'name' => 'join-group',
            'alias' => $alias,
            'rows' => $hasExplicitAlias ? self::unqualifiedDerivedRows($rows) : $rows,
            'columns' => $columns,
            'qualifyRows' => $hasExplicitAlias,
        ];
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private static function unqualifiedDerivedColumnNames(array $columns): array
    {
        $unqualified = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL derived rows must have named columns');
            }
            $name = str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;
            if (in_array($name, $unqualified, true)) {
                $name = $column;
            }
            $unqualified[] = $name;
        }

        return $unqualified;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function unqualifiedDerivedRows(array $rows): array
    {
        $unqualified = [];
        foreach ($rows as $row) {
            $unqualifiedRow = [];
            foreach ($row as $column => $value) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite SELECT SQL derived rows must have named columns');
                }
                $name = str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;
                if (array_key_exists($name, $unqualifiedRow)) {
                    $name = $column;
                }
                $unqualifiedRow[$name] = $value;
            }
            $unqualified[] = $unqualifiedRow;
        }

        return $unqualified;
    }

    /**
     * @return array{0:string,1:list<string>}
     */
    private static function parenthesizedJoinAlias(string $sql): array
    {
        if ($sql === '') {
            return ['join_group', []];
        }

        if (preg_match('/^(?:AS\s+)?([A-Za-z_][A-Za-z0-9_]*)(.*)$/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL parenthesized join alias is malformed');
        }

        $alias = $match[1];
        self::assertBareIdentifier($alias, 'SQLite SELECT SQL parenthesized join alias');
        $tail = trim($match[2]);
        if ($tail === '') {
            return [$alias, []];
        }
        if (!str_starts_with($tail, '(')) {
            throw new \InvalidArgumentException('SQLite SELECT SQL parenthesized join alias is malformed');
        }

        [$columnSql, $offset] = self::consumeParenthesized($tail, 0);
        if (trim(substr($tail, $offset)) !== '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL parenthesized join alias is malformed');
        }

        $columns = [];
        foreach (self::splitTopLevel($columnSql, ',') as $column) {
            $column = trim($column);
            self::assertBareIdentifier($column, 'SQLite SELECT SQL parenthesized join column alias');
            $columns[] = $column;
        }
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite SELECT SQL parenthesized join column list cannot be empty');
        }

        return [$alias, $columns];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{name:string,alias:string,rows:list<array<string,mixed>>}|null
     */
    private static function derivedTableReference(string $sql, array $tables, ?array $outerRow = null, array $requiredColumns = []): ?array
    {
        $sql = trim($sql);
        if (!str_starts_with($sql, '(')) {
            return null;
        }

        [$body, $offset] = self::consumeParenthesized($sql, 0);
        $body = trim($body);
        if (preg_match('/^(?:select|with)\s+/i', $body) !== 1) {
            return null;
        }

        [$alias, $columns] = self::derivedTableAlias(trim(substr($sql, $offset)));
        $rows = $outerRow === null
            ? self::executeDerivedTableBody($body, $tables, $requiredColumns)
            : self::correlatedSubqueryRows($body, $tables, $outerRow);
        if ($columns !== []) {
            $rows = self::renameDerivedTableColumns($rows, $columns, $alias);
        } else {
            $rows = self::unqualifiedDerivedRows($rows);
        }

        return [
            'name' => 'subquery',
            'alias' => $alias,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $requiredColumns
     * @return list<array<string,mixed>>
     */
    private static function executeDerivedTableBody(string $body, array $tables, array $requiredColumns): array
    {
        $plan = self::plan($body, $tables);
        if ($requiredColumns !== [] && isset($plan['compound']) && is_array($plan['compound'])) {
            $plan = self::pruneUnusedDerivedCompoundCounters($plan, $requiredColumns);
        }

        return isset($plan['compound']) && is_array($plan['compound'])
            ? self::executeCompoundPlan($plan)
            : self::stripHiddenOrderColumns(SQLiteSelectQuery::execute($plan), $plan);
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<string> $requiredColumns
     * @return array<string,mixed>
     */
    private static function pruneUnusedDerivedCompoundCounters(array $plan, array $requiredColumns): array
    {
        $required = array_fill_keys(array_map('strtolower', $requiredColumns), true);
        if (!isset($plan['compound']['arms']) || !is_array($plan['compound']['arms'])) {
            return $plan;
        }

        foreach ($plan['compound']['arms'] as $armIndex => $arm) {
            if (!is_array($arm) || !isset($arm['select']) || !is_array($arm['select'])) {
                continue;
            }
            $select = [];
            foreach ($arm['select'] as $termIndex => $term) {
                if (
                    is_array($term)
                    && self::isCounterProjection($term)
                    && !isset($required[strtolower(self::compoundArmOutputColumn($term, $termIndex + 1))])
                ) {
                    continue;
                }
                $select[] = $term;
            }
            if ($select !== []) {
                $plan['compound']['arms'][$armIndex]['select'] = $select;
            }
        }

        return $plan;
    }

    /**
     * @param array<string,mixed> $term
     */
    private static function isCounterProjection(array $term): bool
    {
        return ($term['type'] ?? null) === 'function'
            && isset($term['name'])
            && is_string($term['name'])
            && strcasecmp($term['name'], 'counter') === 0;
    }

    /**
     * @return array{0:string,1:list<string>}
     */
    private static function derivedTableAlias(string $sql): array
    {
        if ($sql === '') {
            return ['subquery', []];
        }

        if (preg_match('/^(?:AS\s+)?([A-Za-z_][A-Za-z0-9_]*)(.*)$/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL derived table alias is malformed');
        }

        $alias = $match[1];
        self::assertBareIdentifier($alias, 'SQLite SELECT SQL derived table alias');
        $tail = trim($match[2]);
        if ($tail === '') {
            return [$alias, []];
        }
        if (!str_starts_with($tail, '(')) {
            throw new \InvalidArgumentException('SQLite SELECT SQL derived table alias is malformed');
        }

        [$columnSql, $offset] = self::consumeParenthesized($tail, 0);
        if (trim(substr($tail, $offset)) !== '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL derived table alias is malformed');
        }

        $columns = [];
        foreach (self::splitTopLevel($columnSql, ',') as $column) {
            $column = trim($column);
            self::assertBareIdentifier($column, 'SQLite SELECT SQL derived table column alias');
            $columns[] = $column;
        }
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite SELECT SQL derived table column list cannot be empty');
        }

        return [$alias, $columns];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function renameDerivedTableColumns(array $rows, array $columns, string $alias): array
    {
        $renamed = [];
        foreach ($rows as $row) {
            if (count($row) !== count($columns)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL derived table {$alias} column list does not match SELECT width");
            }
            $renamed[] = array_combine($columns, array_values($row));
        }

        return $renamed;
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
        if (preg_match('/^values(?:\s+|\()/i', trim($body)) !== 1) {
            return null;
        }

        [$alias, $columns, $explicitAlias] = self::valuesTableAlias(trim(substr($sql, $offset)));
        $rows = self::executeValuesClause(trim($body));
        if ($columns !== []) {
            $rows = self::renameValuesTableColumns($rows, $columns, $alias);
        }

        return [
            'name' => 'values',
            'alias' => $alias,
            'rows' => $rows,
            'qualifyRows' => $explicitAlias,
        ];
    }

    /**
     * @return array{0:string,1:list<string>,2:bool}
     */
    private static function valuesTableAlias(string $sql): array
    {
        if ($sql === '') {
            return ['values', [], false];
        }

        if (preg_match('/^(?:AS\s+)?([A-Za-z_][A-Za-z0-9_]*)(.*)$/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL VALUES source alias is malformed');
        }

        $alias = $match[1];
        self::assertBareIdentifier($alias, 'SQLite SELECT SQL VALUES source alias');
        $tail = trim($match[2]);
        if ($tail === '') {
            return [$alias, [], true];
        }
        if (!str_starts_with($tail, '(')) {
            throw new \InvalidArgumentException('SQLite SELECT SQL VALUES source alias is malformed');
        }

        [$columnSql, $offset] = self::consumeParenthesized($tail, 0);
        if (trim(substr($tail, $offset)) !== '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL VALUES source alias is malformed');
        }

        $columns = [];
        foreach (self::splitTopLevel($columnSql, ',') as $column) {
            $column = trim($column);
            self::assertBareIdentifier($column, 'SQLite SELECT SQL VALUES source column alias');
            $columns[] = $column;
        }
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite SELECT SQL VALUES source column list cannot be empty');
        }

        return [$alias, $columns, true];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function renameValuesTableColumns(array $rows, array $columns, string $alias): array
    {
        $renamed = [];
        foreach ($rows as $row) {
            if (count($row) !== count($columns)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL VALUES source {$alias} column list does not match row width");
            }
            $combined = array_combine($columns, array_values($row));
            if (!is_array($combined)) {
                throw new \InvalidArgumentException("SQLite SELECT SQL VALUES source {$alias} column list does not match row width");
            }
            $renamed[] = $combined;
        }

        return $renamed;
    }

    /**
     * @param list<string> $jsonErrorBoundaryColumns
     * @return array{name:string,alias:string,rows:list<array<string,mixed>>}|null
     */
    private static function jsonTableReference(string $sql, array $jsonErrorBoundaryColumns = [], array $jsonConstraints = []): ?array
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
            $guardedJsonArgument = self::jsonTableArgumentGuardedByErrorBoundary($argumentExpressions[0], $jsonErrorBoundaryColumns);

            return [
                'name' => $function,
                'alias' => $alias,
                'rows' => [],
                'dynamicRows' => static function (array $row) use ($function, $alias, $argumentExpressions, $guardedJsonArgument, $jsonConstraints): array {
                    $jsonValue = SQLiteSelectExpression::evaluate($row, $argumentExpressions[0]);
                    if ($guardedJsonArgument && SQLiteJsonTablePlan::invalidInputCanBeSkipped($jsonValue)) {
                        return [];
                    }

                    $constraints = [
                        [
                            'column' => 'json',
                            'operator' => '=',
                            'value' => $jsonValue,
                        ],
                    ];
                    if (isset($argumentExpressions[1])) {
                        $rootValue = SQLiteSelectExpression::evaluate($row, $argumentExpressions[1]);
                        if ($rootValue === null) {
                            return [];
                        }
                        $constraints[] = [
                            'column' => 'root',
                            'operator' => '=',
                            'value' => $rootValue,
                        ];
                    }
                    foreach ($jsonConstraints as $constraint) {
                        $constraints[] = $constraint;
                    }
                    $extraConstraints = $row['__sqlite_json_table_constraints'][$alias] ?? [];
                    if (is_array($extraConstraints) && array_is_list($extraConstraints)) {
                        foreach ($extraConstraints as $constraint) {
                            if (is_array($constraint)) {
                                $constraints[] = $constraint;
                            }
                        }
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
        foreach ($jsonConstraints as $constraint) {
            $constraints[] = $constraint;
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

        return SQLiteJsonTablePlan::projectedRows($function, $constraints, self::JSON_TABLE_SOURCE_COLUMNS);
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
            $qualified[$index][self::HIDDEN_WILDCARD_METADATA_PREFIX . '.' . $prefix] = array_map(
                static fn (string $column): string => $prefix . '.' . $column,
                self::JSON_TABLE_HIDDEN_WILDCARD_COLUMNS,
            );
        }

        return $qualified;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function unqualifiedJsonRows(array $rows): array
    {
        foreach ($rows as $index => $row) {
            if (!array_key_exists('id', $row)) {
                continue;
            }
            $rows[$index]['rowid'] = $row['id'];
            $rows[$index]['_rowid_'] = $row['id'];
            $rows[$index]['oid'] = $row['id'];
            $rows[$index][self::HIDDEN_WILDCARD_METADATA_PREFIX] = self::JSON_TABLE_HIDDEN_WILDCARD_COLUMNS;
        }

        return $rows;
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

    /**
     * @return list<string>
     */
    private static function jsonTableErrorBoundaryColumns(?array $where): array
    {
        if ($where === null) {
            return [];
        }

        $columns = [];
        foreach (self::flattenAndPredicate($where) as $term) {
            $column = self::jsonTableErrorBoundaryColumn($term);
            if ($column !== null && !in_array($column, $columns, true)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    private static function jsonTableErrorBoundaryColumn(array $predicate): ?string
    {
        if (
            ($predicate['operator'] ?? null) === 'TRUTH'
            && isset($predicate['left'])
            && is_array($predicate['left'])
            && ($predicate['left']['type'] ?? null) === 'function'
            && strtolower((string) ($predicate['left']['name'] ?? '')) === 'json_valid'
        ) {
            $arguments = $predicate['left']['arguments'] ?? null;
            if (is_array($arguments) && count($arguments) === 1 && is_array($arguments[0]) && ($arguments[0]['type'] ?? null) === 'column') {
                return strtolower((string) $arguments[0]['name']);
            }
        }

        if (!isset($predicate['operator'], $predicate['left'], $predicate['right']) || !is_array($predicate['left']) || !is_array($predicate['right'])) {
            return null;
        }
        if (!in_array($predicate['operator'], ['=', 'IS', 'IS NOT DISTINCT FROM'], true)) {
            return null;
        }

        $functionExpression = $predicate['left'];
        $literalExpression = $predicate['right'];
        if (($functionExpression['type'] ?? null) !== 'function') {
            $functionExpression = $predicate['right'];
            $literalExpression = $predicate['left'];
        }
        if (($functionExpression['type'] ?? null) !== 'function' || ($literalExpression['type'] ?? null) !== 'literal') {
            return null;
        }

        $function = strtolower((string) ($functionExpression['name'] ?? ''));
        $expected = $literalExpression['value'] ?? null;
        if (($function === 'json_valid' && $expected !== 1) || ($function === 'json_error_position' && $expected !== 0)) {
            return null;
        }
        if ($function !== 'json_valid' && $function !== 'json_error_position') {
            return null;
        }

        $arguments = $functionExpression['arguments'] ?? null;
        if (!is_array($arguments) || count($arguments) !== 1 || !is_array($arguments[0]) || ($arguments[0]['type'] ?? null) !== 'column') {
            return null;
        }

        return strtolower((string) $arguments[0]['name']);
    }

    /**
     * @param array<string,mixed> $argument
     * @param list<string> $jsonErrorBoundaryColumns
     */
    private static function jsonTableArgumentGuardedByErrorBoundary(array $argument, array $jsonErrorBoundaryColumns): bool
    {
        if (($argument['type'] ?? null) !== 'column' || !isset($argument['name']) || !is_string($argument['name'])) {
            return false;
        }

        return in_array(strtolower($argument['name']), $jsonErrorBoundaryColumns, true);
    }

    private static function removeJsonTableHiddenConstraints(string $fromSql, array $where): ?array
    {
        $alias = self::bareJsonTableAlias($fromSql);
        if ($alias === null) {
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
                return self::unqualifyBareJsonTablePredicate($terms[0], $alias);
            }

            return self::unqualifyBareJsonTablePredicate(['operator' => 'AND', 'terms' => $terms], $alias);
        }

        return self::jsonTableHiddenConstraint($where) === null
            ? self::unqualifyBareJsonTablePredicate($where, $alias)
            : null;
    }

    private static function bareJsonTableAlias(string $fromSql): ?string
    {
        if (self::firstJoinOffset($fromSql) !== null) {
            return null;
        }
        if (preg_match('/^(json_each|json_tree)(?:\s*\(.*\))?(?:\s+(?:AS\s+)?([A-Za-z_][A-Za-z0-9_]*))?$/is', trim($fromSql), $match) !== 1) {
            return null;
        }

        return isset($match[2]) && $match[2] !== '' ? $match[2] : strtolower($match[1]);
    }

    /**
     * @param array<string,mixed> $predicate
     * @return array<string,mixed>
     */
    private static function unqualifyBareJsonTablePredicate(array $predicate, string $alias): array
    {
        $normalizedAlias = strtolower($alias);
        foreach ($predicate as $key => $value) {
            if (is_array($value)) {
                $predicate[$key] = self::unqualifyBareJsonTablePredicate($value, $alias);
                continue;
            }
            if ($key !== 'name' || !is_string($value)) {
                continue;
            }

            $name = strtolower($value);
            if (!str_starts_with($name, $normalizedAlias . '.')) {
                continue;
            }

            $column = substr($name, strlen($normalizedAlias) + 1);
            if (in_array($column, ['key', 'value', 'type', 'atom', 'id', 'parent', 'fullkey', 'path', 'rowid', '_rowid_', 'oid'], true)) {
                $predicate[$key] = $column;
            }
        }

        return $predicate;
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
        $originalColumn = $column;
        if ($column === 'rowid' || $column === '_rowid_' || $column === 'oid') {
            $column = 'id';
        }
        if ($column !== 'json' && $column !== 'root' && $column !== 'id') {
            return null;
        }

        return [
            'column' => $column,
            'originalColumn' => $originalColumn,
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
    private static function consumeJoin(string $sql, array $tables, array $leftRows = [], array $jsonErrorBoundaryColumns = [], ?array $outerRow = null, ?array $wherePredicate = null): array
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
            $type = 'INNER';
        }

        $rest = trim(substr($sql, strlen($match[0])));
        $boundary = self::nextJoinConditionOffset($rest);
        $nextJoin = self::firstJoinOffset($rest);
        if ($natural && $boundary !== null && ($nextJoin === null || $boundary < $nextJoin)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL NATURAL join may not have an ON or USING clause');
        }
        if ($natural) {
            $boundary = null;
        }
        if ($boundary === null && ($type === 'CROSS' || $natural)) {
            $tableSql = $nextJoin === null ? $rest : trim(substr($rest, 0, $nextJoin));
            $remaining = $nextJoin === null ? '' : trim(substr($rest, $nextJoin));
            $table = self::tableReference($tableSql, $tables, [], $jsonErrorBoundaryColumns, $outerRow);
            $rightRows = ($table['name'] === 'json_each' || $table['name'] === 'json_tree')
                ? self::qualifiedJsonRows($table['rows'], $table['alias'])
                : self::qualifiedRows($table['rows'], $table['alias']);
            $rightColumns = self::tableReferenceResultColumns($table, $rightRows);
            if ($natural) {
                $columns = self::naturalJoinColumnsFromColumnNames(self::collectColumns($leftRows), $rightColumns);
                $join = [
                    'type' => $type,
                    'rows' => $rightRows,
                    'predicate' => self::usingPredicate($columns, $leftRows, $rightRows, self::collectColumns($leftRows), $rightColumns),
                    'coalesceColumns' => $columns,
                ];
                if ($type === 'LEFT' || $type === 'FULL') {
                    $join['rightColumns'] = $rightColumns;
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
            $nextJoin = self::firstJoinOffset($rest);
            $tableSql = $nextJoin === null ? $rest : trim(substr($rest, 0, $nextJoin));
            $remaining = $nextJoin === null ? '' : trim(substr($rest, $nextJoin));
            $table = self::tableReference($tableSql, $tables, [], $jsonErrorBoundaryColumns, $outerRow);
            $rightRows = ($table['name'] === 'json_each' || $table['name'] === 'json_tree')
                ? self::qualifiedJsonRows($table['rows'], $table['alias'])
                : self::qualifiedRows($table['rows'], $table['alias']);

            if ($type === 'INNER') {
                $join = [
                    'type' => 'CROSS',
                    'rows' => $rightRows,
                ];
                if (isset($table['dynamicRows']) && is_callable($table['dynamicRows'])) {
                    $join['dynamicRows'] = $table['dynamicRows'];
                    if ($table['name'] === 'json_each' || $table['name'] === 'json_tree') {
                        $join['rightColumns'] = self::qualifiedJsonTableColumns($table['alias']);
                    }
                }

                return [$join, $remaining];
            }

            $join = [
                'type' => $type,
                'rows' => $rightRows,
                'predicate' => static fn (array $_left, array $_right): bool => true,
            ];
            if (isset($table['dynamicRows']) && is_callable($table['dynamicRows'])) {
                $join['dynamicRows'] = $table['dynamicRows'];
                if ($table['name'] === 'json_each' || $table['name'] === 'json_tree') {
                    $join['rightColumns'] = self::qualifiedJsonTableColumns($table['alias']);
                }
            }
            if ($type === 'LEFT' || $type === 'FULL') {
                $join['rightColumns'] = self::tableReferenceResultColumns($table, $rightRows);
                if ($join['rightColumns'] === [] && ($table['name'] === 'json_each' || $table['name'] === 'json_tree')) {
                    $join['rightColumns'] = self::qualifiedJsonTableColumns($table['alias']);
                }
            }

            return [$join, $remaining];
        }

        $table = self::tableReference(trim(substr($rest, 0, $boundary)), $tables, [], $jsonErrorBoundaryColumns, $outerRow);
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
            $joinRightColumns = self::tableReferenceResultColumns($table, $join['rows']);
            $join['predicate'] = self::usingPredicate(
                $columns,
                $leftRows,
                $join['rows'],
                self::collectColumns($leftRows),
                $joinRightColumns,
            );
            $join['coalesceColumns'] = $columns;
            if ($type === 'CROSS') {
                $join['type'] = 'INNER';
            }
            if ($type === 'LEFT' || $type === 'FULL') {
                $join['rightColumns'] = $joinRightColumns;
            }

            return [$join, $remaining];
        }

        if (preg_match('/^on\s+(.+)$/is', $condition, $on) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL JOIN needs ON or USING');
        }

        $predicate = self::predicate($on[1], $tables);
        $jsonIndexPredicate = $predicate;
        $jsonHiddenConstraints = ($table['name'] === 'json_each' || $table['name'] === 'json_tree')
            ? self::mergeJsonTableHiddenExpressionConstraints(
                self::jsonTableHiddenExpressionConstraintsForAlias($predicate, $table['alias']),
                $wherePredicate === null ? [] : self::jsonTableHiddenExpressionConstraintsForAlias($wherePredicate, $table['alias']),
            )
            : [];
        if ($jsonHiddenConstraints !== []) {
            $function = $table['name'];
            $alias = $table['alias'];
            $baseDynamicRows = isset($table['dynamicRows']) && is_callable($table['dynamicRows'])
                ? $table['dynamicRows']
                : null;
            $join['dynamicRows'] = static function (array $row) use ($function, $alias, $jsonHiddenConstraints, $baseDynamicRows): array {
                $constraints = [];
                foreach ($jsonHiddenConstraints as $constraint) {
                    $value = SQLiteSelectExpression::evaluate($row, $constraint['expression']);
                    if ($constraint['column'] === 'root' && $value === null) {
                        return [];
                    }
                    $constraints[] = [
                        'column' => $constraint['column'],
                        'operator' => '=',
                        'value' => $value,
                        'usable' => true,
                    ];
                }

                if ($baseDynamicRows !== null) {
                    $row['__sqlite_json_table_constraints'][$alias] = array_merge(
                        $row['__sqlite_json_table_constraints'][$alias] ?? [],
                        $constraints,
                    );

                    return $baseDynamicRows($row);
                }

                $plan = SQLiteJsonTablePlan::validatedPlan($function, $constraints);
                if (!$plan['runnable'] && ($plan['jsonInputKind'] === 'jsonb' || $plan['jsonInputKind'] === 'sql-null')) {
                    return [];
                }

                return self::qualifiedJsonRows(self::jsonTableRowsForSql($function, $constraints), $alias);
            };
            $join['rightColumns'] = self::qualifiedJsonTableColumns($alias);
            $predicate = self::removeJsonTableHiddenExpressionConstraintsForAlias($predicate, $table['alias']);
            if ($predicate === null) {
                $predicate = ['operator' => 'IS NOT NULL', 'left' => ['type' => 'literal', 'value' => 1]];
            }
            $join['jsonTableHiddenIndex'] = [
                'alias' => $alias,
                'constraints' => array_map(
                    static fn (array $constraint): array => [
                        'column' => $constraint['column'],
                        'originalColumn' => $constraint['originalColumn'] ?? $constraint['column'],
                        'operator' => '=',
                        'expression' => $constraint['sql'],
                    ],
                    $jsonHiddenConstraints,
                ),
                'constraintCount' => count($jsonHiddenConstraints),
            ];
        }
        $jsonIndexConstraints = ($table['name'] === 'json_each' || $table['name'] === 'json_tree')
            ? self::jsonTableVisibleConstraintsForAlias($jsonIndexPredicate, $table['alias'])
            : [];
        if ($jsonIndexConstraints !== [] && isset($join['dynamicRows']) && is_callable($join['dynamicRows'])) {
            $dynamicRows = $join['dynamicRows'];
            $alias = $table['alias'];
            $join['indexedDynamicRows'] = static function (array $row) use ($dynamicRows, $alias, $jsonIndexConstraints): array {
                $row['__sqlite_json_table_constraints'][$alias] = array_merge(
                    $row['__sqlite_json_table_constraints'][$alias] ?? [],
                    $jsonIndexConstraints,
                );

                return $dynamicRows($row);
            };
            $join['jsonTableIndex'] = [
                'alias' => $alias,
                'constraints' => $jsonIndexConstraints,
                'constraintCount' => count($jsonIndexConstraints),
            ];
        }
        $join['predicate'] = static function (array $left, array $right) use ($predicate): bool {
            return SQLiteSelectPredicate::filter([array_merge($left, $right)], $predicate) !== [];
        };
        if ($type === 'CROSS') {
            $join['type'] = 'INNER';
        }
        if ($type === 'LEFT' || $type === 'FULL') {
            $join['rightColumns'] = self::tableReferenceResultColumns($table, $join['rows']);
            if ($join['rightColumns'] === [] && ($table['name'] === 'json_each' || $table['name'] === 'json_tree')) {
                $join['rightColumns'] = self::qualifiedJsonTableColumns($table['alias']);
            }
        }

        return [$join, $remaining];
    }

    /**
     * @return list<array{column:string,operator:string,value:mixed,usable?:bool}>
     */
    private static function jsonTableVisibleConstraintsForAlias(array $predicate, string $alias): array
    {
        $constraints = [];
        foreach (self::flattenAndPredicate($predicate) as $term) {
            $constraint = self::jsonTableVisibleConstraint($term, $alias);
            if ($constraint !== null) {
                $constraints[] = $constraint;
            }
        }

        return $constraints;
    }

    /**
     * @return array{column:string,operator:string,value:mixed,usable?:bool}|null
     */
    private static function jsonTableVisibleConstraint(array $predicate, string $alias): ?array
    {
        $operator = strtoupper((string) ($predicate['operator'] ?? ''));
        if ($operator === 'AND' || $operator === 'OR' || $operator === 'NOT') {
            return null;
        }

        if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
            $left = $predicate['left'] ?? null;
            if (!is_array($left)) {
                return null;
            }
            $column = self::jsonTableVisibleColumnName($left, $alias);
            if ($column === null) {
                return null;
            }

            return ['column' => $column, 'operator' => $operator, 'value' => null, 'usable' => true];
        }

        if ($operator === 'IN') {
            $left = $predicate['left'] ?? null;
            $values = $predicate['values'] ?? null;
            if (!is_array($left) || !is_array($values) || !array_is_list($values)) {
                return null;
            }
            $column = self::jsonTableVisibleColumnName($left, $alias);
            if ($column === null) {
                return null;
            }
            $literalValues = [];
            foreach ($values as $valueExpression) {
                if (!is_array($valueExpression) || ($valueExpression['type'] ?? null) !== 'literal' || !array_key_exists('value', $valueExpression)) {
                    return null;
                }
                $literalValues[] = $valueExpression['value'];
            }

            return ['column' => $column, 'operator' => 'IN', 'value' => $literalValues, 'usable' => true];
        }

        if (!in_array($operator, ['=', 'IS', 'IS NOT', 'IS DISTINCT FROM', 'IS NOT DISTINCT FROM', '<', '<=', '>', '>=', 'LIKE', 'GLOB'], true)) {
            return null;
        }
        if (!isset($predicate['left'], $predicate['right']) || !is_array($predicate['left']) || !is_array($predicate['right'])) {
            return null;
        }

        $columnExpression = $predicate['left'];
        $literalExpression = $predicate['right'];
        $column = self::jsonTableVisibleColumnName($columnExpression, $alias);
        if ($column === null) {
            $columnExpression = $predicate['right'];
            $literalExpression = $predicate['left'];
            $column = self::jsonTableVisibleColumnName($columnExpression, $alias);
        }
        if ($column === null || ($literalExpression['type'] ?? null) !== 'literal' || !array_key_exists('value', $literalExpression)) {
            return null;
        }

        return ['column' => $column, 'operator' => $operator, 'value' => $literalExpression['value'], 'usable' => true];
    }

    private static function jsonTableVisibleColumnName(array $expression, string $alias): ?string
    {
        if (($expression['type'] ?? null) !== 'column' || !isset($expression['name']) || !is_string($expression['name'])) {
            return null;
        }

        $name = strtolower($expression['name']);
        $normalizedAlias = strtolower($alias);
        if (str_contains($name, '.')) {
            [$prefix, $column] = explode('.', $name, 2);
            if ($prefix !== $normalizedAlias) {
                return null;
            }
        } else {
            $column = $name;
        }

        if ($column === 'rowid' || $column === '_rowid_' || $column === 'oid') {
            $column = 'id';
        }

        return in_array($column, ['key', 'value', 'type', 'atom', 'id', 'parent', 'fullkey', 'path'], true)
            ? $column
            : null;
    }

    /**
     * @return list<array{column:string,expression:array<string,mixed>,sql:string}>
     */
    private static function jsonTableHiddenExpressionConstraintsForAlias(array $predicate, string $alias): array
    {
        $constraints = [];
        $seen = [];
        foreach (self::flattenAndPredicate($predicate) as $term) {
            $constraint = self::jsonTableHiddenExpressionConstraintForAlias($term, $alias);
            if ($constraint === null) {
                continue;
            }
            if (isset($seen[$constraint['column']])) {
                continue;
            }

            $seen[$constraint['column']] = true;
            $constraints[] = $constraint;
        }

        return $constraints;
    }

    /**
     * @param list<array{column:string,expression:array<string,mixed>,sql:string}> $primary
     * @param list<array{column:string,expression:array<string,mixed>,sql:string}> $secondary
     * @return list<array{column:string,expression:array<string,mixed>,sql:string}>
     */
    private static function mergeJsonTableHiddenExpressionConstraints(array $primary, array $secondary): array
    {
        $merged = [];
        $seen = [];
        foreach ([$primary, $secondary] as $constraints) {
            foreach ($constraints as $constraint) {
                $column = (string) $constraint['column'];
                if (isset($seen[$column])) {
                    continue;
                }

                $seen[$column] = true;
                $merged[] = $constraint;
            }
        }

        return $merged;
    }

    /**
     * @return array{column:string,expression:array<string,mixed>,sql:string}|null
     */
    private static function jsonTableHiddenExpressionConstraintForAlias(array $predicate, string $alias): ?array
    {
        if (($predicate['operator'] ?? null) !== '=') {
            return null;
        }
        if (!isset($predicate['left'], $predicate['right']) || !is_array($predicate['left']) || !is_array($predicate['right'])) {
            return null;
        }

        $originalColumn = self::jsonTableHiddenOriginalColumnName($predicate['left'], $alias);
        $column = self::jsonTableHiddenColumnName($predicate['left'], $alias);
        $expression = $predicate['right'];
        if ($column === null) {
            $originalColumn = self::jsonTableHiddenOriginalColumnName($predicate['right'], $alias);
            $column = self::jsonTableHiddenColumnName($predicate['right'], $alias);
            $expression = $predicate['left'];
        }
        if ($column === null) {
            return null;
        }

        return [
            'column' => $column,
            'originalColumn' => $originalColumn ?? $column,
            'expression' => $expression,
            'sql' => self::expressionSql($expression),
        ];
    }

    private static function jsonTableHiddenOriginalColumnName(array $expression, string $alias): ?string
    {
        if (($expression['type'] ?? null) !== 'column' || !isset($expression['name']) || !is_string($expression['name'])) {
            return null;
        }

        $name = strtolower($expression['name']);
        $prefix = strtolower($alias) . '.';
        if (!str_starts_with($name, $prefix)) {
            return null;
        }

        $column = substr($name, strlen($prefix));

        return in_array($column, ['json', 'root', 'rowid', '_rowid_', 'oid'], true) ? $column : null;
    }

    private static function removeJsonTableHiddenExpressionConstraintsForAlias(array $predicate, string $alias): ?array
    {
        if (($predicate['operator'] ?? null) === 'AND' && isset($predicate['terms']) && is_array($predicate['terms'])) {
            $terms = [];
            foreach ($predicate['terms'] as $term) {
                if (is_array($term) && self::jsonTableHiddenExpressionConstraintForAlias($term, $alias) !== null) {
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

        return self::jsonTableHiddenExpressionConstraintForAlias($predicate, $alias) === null ? $predicate : null;
    }

    private static function jsonTableHiddenColumnName(array $expression, string $alias): ?string
    {
        if (($expression['type'] ?? null) !== 'column' || !isset($expression['name']) || !is_string($expression['name'])) {
            return null;
        }

        $name = strtolower($expression['name']);
        $normalizedAlias = strtolower($alias);
        if (!str_contains($name, '.')) {
            return null;
        }
        [$prefix, $column] = explode('.', $name, 2);
        if ($prefix !== $normalizedAlias) {
            return null;
        }

        if ($column === 'rowid' || $column === '_rowid_' || $column === 'oid') {
            $column = 'id';
        }

        return in_array($column, ['json', 'root', 'id'], true) ? $column : null;
    }

    private static function expressionSql(array $expression): string
    {
        if (($expression['type'] ?? null) === 'column' && isset($expression['name']) && is_string($expression['name'])) {
            return $expression['name'];
        }
        if (($expression['type'] ?? null) === 'literal' && array_key_exists('value', $expression)) {
            $value = $expression['value'];
            if ($value === null) {
                return 'NULL';
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
            if (is_string($value)) {
                return "'" . str_replace("'", "''", $value) . "'";
            }
        }
        if (($expression['type'] ?? null) === 'function' && isset($expression['name']) && is_string($expression['name'])) {
            return $expression['name'] . '(...)';
        }

        return (string) ($expression['type'] ?? 'expression');
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
                if (is_string($column) && self::isInternalMetadataColumn($column)) {
                    continue;
                }
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
        return self::naturalJoinColumnsFromColumnNames(self::collectColumns($leftRows), self::collectColumns($rightRows));
    }

    /**
     * @param list<string> $leftColumns
     * @param list<string> $rightColumns
     * @return list<string>
     */
    private static function naturalJoinColumnsFromColumnNames(array $leftColumns, array $rightColumns): array
    {
        $left = array_map(self::unqualifiedColumn(...), $leftColumns);
        $right = array_map(self::unqualifiedColumn(...), $rightColumns);

        return array_values(array_intersect($left, $right));
    }

    /**
     * @param array{name:string,alias:string,rows:list<array<string,mixed>>,columns?:list<string>} $table
     * @param list<array<string,mixed>> $qualifiedRows
     * @return list<string>
     */
    private static function tableReferenceResultColumns(array $table, array $qualifiedRows): array
    {
        $columns = self::collectColumns($qualifiedRows);
        if ($columns !== []) {
            return $columns;
        }

        $referenceColumns = $table['columns'] ?? [];
        if (!is_array($referenceColumns) || !array_is_list($referenceColumns)) {
            return [];
        }

        $qualified = [];
        foreach ($referenceColumns as $column) {
            if (!is_string($column) || $column === '') {
                continue;
            }
            $qualified[] = str_contains($column, '.') ? $column : $table['alias'] . '.' . $column;
        }

        return array_values(array_unique($qualified));
    }

    /**
     * @param list<string> $columns
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @param list<string>|null $leftColumnNames
     * @param list<string>|null $rightColumnNames
     * @return callable(array<string,mixed>,array<string,mixed>):bool
     */
    private static function usingPredicate(
        array $columns,
        array $leftRows,
        array $rightRows,
        ?array $leftColumnNames = null,
        ?array $rightColumnNames = null
    ): callable
    {
        if ($columns === []) {
            return static fn (): bool => true;
        }
        $leftColumns = self::resolveJoinColumnSets($columns, $leftColumnNames ?? self::collectColumns($leftRows), 'left');
        $rightColumns = self::resolveJoinColumnSets($columns, $rightColumnNames ?? self::collectColumns($rightRows), 'right');

        return static function (array $left, array $right) use ($leftColumns, $rightColumns): bool {
            foreach ($leftColumns as $index => $leftColumn) {
                $rightColumn = $rightColumns[$index];
                $leftValue = self::coalescedJoinColumnValue($left, $leftColumn);
                $rightValue = self::coalescedJoinColumnValue($right, $rightColumn);
                if ($leftValue === null || $rightValue === null) {
                    return false;
                }
                if (!self::joinValuesEqual(
                    $leftValue,
                    $rightValue,
                    self::coalescedJoinColumnAffinity($left, $leftColumn),
                    self::coalescedJoinColumnAffinity($right, $rightColumn),
                    self::coalescedJoinColumnCollation($left, $leftColumn),
                )) {
                    return false;
                }
            }

            return true;
        };
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function coalescedJoinColumnValue(array $row, array $columns): mixed
    {
        $sawColumn = false;
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }
            $sawColumn = true;
            if ($row[$column] !== null) {
                return $row[$column];
            }
        }
        if (!$sawColumn) {
            throw new \InvalidArgumentException('SQLite SELECT SQL JOIN USING row is missing a comparison column');
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function coalescedJoinColumnAffinity(array $row, array $columns): string
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }
            $affinity = self::joinColumnAffinity($row, $column);
            if ($affinity !== null) {
                return $affinity;
            }
            if ($row[$column] !== null) {
                return 'NONE';
            }
        }

        foreach ($columns as $column) {
            $affinity = self::joinColumnAffinity($row, $column);
            if ($affinity !== null) {
                return $affinity;
            }
        }

        return 'NONE';
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function coalescedJoinColumnCollation(array $row, array $columns): string
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }
            $collation = self::joinColumnCollation($row, $column);
            if ($collation !== null) {
                return $collation;
            }
            if ($row[$column] !== null) {
                return 'BINARY';
            }
        }

        foreach ($columns as $column) {
            $collation = self::joinColumnCollation($row, $column);
            if ($collation !== null) {
                return $collation;
            }
        }

        return 'BINARY';
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function joinColumnAffinity(array $row, string $column): ?string
    {
        $candidates = [$column];
        if (str_contains($column, '.')) {
            $candidates[] = self::unqualifiedColumn($column);
        }

        $metadataKeys = [];
        if (str_contains($column, '.')) {
            $metadataKeys[] = substr($column, 0, strrpos($column, '.')) . '.__sqlite_column_affinities';
        }
        $metadataKeys[] = '__sqlite_column_affinities';

        foreach ($row as $key => $value) {
            if (is_string($key) && str_ends_with($key, '.__sqlite_column_affinities') && !in_array($key, $metadataKeys, true)) {
                $metadataKeys[] = $key;
            }
        }

        foreach ($metadataKeys as $metadataKey) {
            $metadata = $row[$metadataKey] ?? null;
            if (!is_array($metadata)) {
                continue;
            }
            foreach ($candidates as $candidate) {
                $affinity = $metadata[$candidate] ?? null;
                if (is_string($affinity) && $affinity !== '') {
                    return $affinity;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function joinColumnCollation(array $row, string $column): ?string
    {
        $candidates = [$column];
        if (str_contains($column, '.')) {
            $candidates[] = self::unqualifiedColumn($column);
        }

        $metadataKeys = [];
        if (str_contains($column, '.')) {
            $metadataKeys[] = substr($column, 0, strrpos($column, '.')) . '.__sqlite_column_collations';
        }
        $metadataKeys[] = '__sqlite_column_collations';

        foreach ($row as $key => $value) {
            if (is_string($key) && str_ends_with($key, '.__sqlite_column_collations') && !in_array($key, $metadataKeys, true)) {
                $metadataKeys[] = $key;
            }
        }

        foreach ($metadataKeys as $metadataKey) {
            $metadata = $row[$metadataKey] ?? null;
            if (!is_array($metadata)) {
                continue;
            }
            foreach ($candidates as $candidate) {
                $collation = $metadata[$candidate] ?? null;
                if (!is_string($collation) || $collation === '') {
                    continue;
                }
                $collation = strtoupper($collation);
                if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
                    throw new \InvalidArgumentException("Unsupported SQLite SELECT SQL JOIN USING collation: {$metadata[$candidate]}");
                }

                return $collation;
            }
        }

        return null;
    }

    /**
     * @param list<string> $columns
     * @param list<string> $available
     * @return list<list<string>>
     */
    private static function resolveJoinColumnSets(array $columns, array $available, string $side): array
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
            $resolved[] = $matches;
        }

        return $resolved;
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

    private static function joinValuesEqual(
        mixed $leftValue,
        mixed $rightValue,
        string $leftAffinity = 'NONE',
        string $rightAffinity = 'NONE',
        string $collation = 'BINARY'
    ): bool
    {
        self::joinValueKey($leftValue);
        self::joinValueKey($rightValue);

        return SQLiteAffinityComparison::compareColumnValues($leftValue, $rightValue, $leftAffinity, $rightAffinity, $collation) === 0;
    }

    /**
     * @return list<string>
     */
    private static function qualifiedJsonTableColumns(string $alias): array
    {
        return array_map(
            static fn (string $column): string => $alias . '.' . $column,
            array_merge(self::JSON_TABLE_SOURCE_COLUMNS, ['rowid', '_rowid_', 'oid']),
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
     * @param list<array<string,mixed>> $select
     * @param list<array<string,mixed>> $sourceRows
     * @return list<array<string,mixed>>
     */
    private static function liftOuterAggregateScalarSubqueries(array $select, array $sourceRows): array
    {
        $sourceColumns = self::sourceRowColumnSet($sourceRows);
        if ($sourceColumns === []) {
            return $select;
        }

        foreach ($select as $index => $expression) {
            $lifted = self::liftOuterAggregateScalarSubquery($expression, $sourceColumns);
            if ($lifted === null) {
                continue;
            }
            if (isset($expression['alias']) && is_string($expression['alias'])) {
                $lifted['alias'] = $expression['alias'];
            }
            $select[$index] = $lifted;
        }

        return $select;
    }

    /**
     * @param array<string,mixed> $expression
     * @param array<string,true> $sourceColumns
     * @return array<string,mixed>|null
     */
    private static function liftOuterAggregateScalarSubquery(array $expression, array $sourceColumns): ?array
    {
        if (($expression['type'] ?? null) !== 'subquery' || !isset($expression['subquerySql']) || !is_string($expression['subquerySql'])) {
            return null;
        }

        $subquerySql = trim($expression['subquerySql']);
        if (preg_match('/^select\s+/i', $subquerySql) !== 1) {
            return null;
        }
        foreach (['FROM', 'UNION', 'INTERSECT', 'EXCEPT'] as $keyword) {
            if (self::keywordOffset($subquerySql, $keyword) !== null) {
                return null;
            }
        }

        $selectSql = trim(substr($subquerySql, 6));
        if ($selectSql === '' || self::tailClauseOffsets($selectSql) !== []) {
            return null;
        }
        [$selectSql, $distinct] = self::selectModifier($selectSql);
        if ($distinct || $selectSql === '') {
            return null;
        }

        $items = self::selectList($selectSql);
        if (count($items) !== 1) {
            return null;
        }

        $aggregate = self::aggregateSummaryColumn($items[0], null);
        if ($aggregate === null) {
            return null;
        }
        $valueColumn = $aggregate['valueColumn'] ?? null;
        if (!is_string($valueColumn) || !isset($sourceColumns[$valueColumn])) {
            return null;
        }

        return $items[0];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,true>
     */
    private static function sourceRowColumnSet(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach ($row as $column => $unused) {
                if (!is_string($column) || self::isInternalMetadataColumn($column)) {
                    continue;
                }
                $columns[$column] = true;
            }
        }

        return $columns;
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
            $implicitAlias = self::implicitProjectionAlias($item);

            return $implicitAlias ?? [$item, null];
        }

        $expression = trim(substr($item, 0, $as));
        $aliasSql = trim(substr($item, $as + 2));
        $alias = self::unquoteIdentifier($aliasSql);
        if ($alias === null) {
            $alias = $aliasSql;
            self::assertIdentifier($alias, 'SQLite SELECT SQL projection alias');
        }

        return [$expression, $alias];
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private static function implicitProjectionAlias(string $item): ?array
    {
        if (preg_match('/^(.*\S)\s+([A-Za-z_][A-Za-z0-9_]*)$/s', $item, $match) !== 1) {
            return null;
        }

        $expression = trim($match[1]);
        $alias = $match[2];
        if ($expression === '' || !self::implicitAliasBoundaryIsTopLevel($item, strlen($match[1]))) {
            return null;
        }
        if (self::isReservedProjectionAliasToken($alias)) {
            return null;
        }
        if (preg_match('/(?:^|\s)(?:collate|escape|nulls|is|not)$/i', $expression) === 1) {
            return null;
        }
        if (preg_match('/(?:\|\||->>|->|[+\-*\/%&|<>=~])$/', $expression) === 1) {
            return null;
        }

        return [$expression, $alias];
    }

    private static function implicitAliasBoundaryIsTopLevel(string $sql, int $offset): bool
    {
        $depth = 0;
        $quote = false;
        for ($i = 0; $i < $offset; $i++) {
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
            }
        }

        return $depth === 0 && !$quote;
    }

    private static function isReservedProjectionAliasToken(string $token): bool
    {
        return in_array(strtoupper($token), [
            'AND',
            'ASC',
            'BETWEEN',
            'COLLATE',
            'DESC',
            'ELSE',
            'END',
            'ESCAPE',
            'FALSE',
            'FILTER',
            'FROM',
            'GLOB',
            'IN',
            'IS',
            'LIKE',
            'MATCH',
            'NOT',
            'NULL',
            'NULLS',
            'OR',
            'OVER',
            'REGEXP',
            'THEN',
            'TRUE',
            'WHEN',
        ], true);
    }

    /**
     * @return array<string,mixed>
     */
    private static function predicate(string $sql, array $tables = []): array
    {
        $sql = trim($sql);
        if (str_starts_with($sql, '(') && str_ends_with($sql, ')')) {
            $inner = trim(substr($sql, 1, -1));
            if (
                self::unwrapParenthesizedExpression($sql) === $inner
                && (
                    preg_match('/^select\s+/i', $inner) === 1
                    || preg_match('/^values(?:\s+|\()/i', $inner) === 1
                )
            ) {
                return [
                    'operator' => 'TRUTH',
                    'left' => self::valueExpression($sql, $tables),
                ];
            }
        }

        $unwrapped = self::unwrapParenthesizedExpression($sql);
        if ($unwrapped !== $sql) {
            return self::predicate($unwrapped, $tables);
        }

        $orTerms = self::splitKeyword($sql, 'OR');
        if (count($orTerms) > 1) {
            return ['operator' => 'OR', 'terms' => array_map(static fn (string $term): array => self::predicate($term, $tables), $orTerms)];
        }

        $andTerms = self::splitKeyword($sql, 'AND');
        if (count($andTerms) > 1) {
            return ['operator' => 'AND', 'terms' => array_map(static fn (string $term): array => self::predicate($term, $tables), $andTerms)];
        }

        if (preg_match('/^not\s+(.+)$/is', $sql, $match) === 1) {
            return [
                'operator' => 'NOT',
                'term' => self::predicate(trim($match[1]), $tables),
            ];
        }

        if (preg_match('/^(not\s+)?exists\s*\(\s*(select\s+.+)\)$/is', $sql, $match) === 1) {
            $subquerySql = trim($match[2]);

            return [
                'operator' => isset($match[1]) && trim($match[1]) !== '' ? 'NOT EXISTS' : 'EXISTS',
                'subquery' => static fn (array $row): array => self::correlatedSubqueryRows($subquerySql, $tables, $row),
            ];
        }
        if (preg_match('/^not\s+(.+)$/is', $sql, $match) === 1) {
            return [
                'operator' => 'NOT',
                'term' => self::predicate($match[1], $tables),
            ];
        }
        if (preg_match('/^(.+?)\s+is\s+(not\s+)?(true|false)$/is', $sql, $match) === 1) {
            $expected = strtoupper($match[3]);

            return [
                'operator' => 'IS ' . (isset($match[2]) && trim($match[2]) !== '' ? 'NOT ' : '') . $expected,
                'left' => self::valueExpression(trim($match[1]), $tables),
            ];
        }

        $case = self::caseExpression($sql, $tables);
        if ($case !== null) {
            return [
                'operator' => 'TRUTH',
                'left' => $case,
            ];
        }

        $betweenPredicate = self::betweenPredicateSql($sql, $tables, 'predicate');
        if ($betweenPredicate !== null) {
            return $betweenPredicate;
        }

        if (preg_match('/^(.+?)\s+isnull$/i', $sql, $match) === 1) {
            return [
                'operator' => 'IS NULL',
                'left' => self::valueExpression(trim($match[1]), $tables),
            ];
        }
        if (preg_match('/^(.+?)\s+notnull$/i', $sql, $match) === 1) {
            return [
                'operator' => 'IS NOT NULL',
                'left' => self::valueExpression(trim($match[1]), $tables),
            ];
        }
        if (preg_match('/^(like|glob)\s*\(.*\)$/is', $sql) === 1) {
            return [
                'operator' => 'TRUTH',
                'left' => self::valueExpression($sql, $tables),
            ];
        }

        foreach (['IS NOT DISTINCT FROM', 'IS DISTINCT FROM', 'NOT REGEXP', 'NOT MATCH', 'NOT LIKE', 'LIKE', 'NOT GLOB', 'GLOB', 'REGEXP', 'MATCH', 'IS NOT', 'IS', '>=', '<=', '<>', '!=', '==', '=', '>', '<'] as $operator) {
            $offset = self::operatorOffset($sql, $operator);
            if ($offset === null) {
                continue;
            }
            $left = trim(substr($sql, 0, $offset));
            $right = trim(substr($sql, $offset + strlen($operator)));
            if ($left === '' || $right === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL predicate needs both operands');
            }

            $predicateRight = $right;
            $predicateEscape = null;
            if ($operator === 'LIKE' || $operator === 'NOT LIKE') {
                $escapeParts = self::splitTopLevelByKeyword($right, 'ESCAPE');
                if (count($escapeParts) > 2) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL LIKE predicate supports one ESCAPE clause');
                }
                if (count($escapeParts) === 2) {
                    if ($escapeParts[0] === '' || $escapeParts[1] === '') {
                        throw new \InvalidArgumentException('SQLite SELECT SQL LIKE ESCAPE predicate needs pattern and escape operands');
                    }
                    $predicateRight = $escapeParts[0];
                    $predicateEscape = $escapeParts[1];
                }
            }

            if (($operator === 'MATCH' || $operator === 'NOT MATCH' || $operator === 'REGEXP' || $operator === 'NOT REGEXP')
                && preg_match('/\s+ESCAPE\s+/i', $right) === 1) {
                throw new \InvalidArgumentException("SQLite SELECT SQL {$operator} expression does not support ESCAPE");
            }

            $predicate = [
                'operator' => $operator,
                'left' => self::valueExpression($left, $tables),
                'right' => self::valueExpression($predicateRight, $tables),
            ];
            if ($predicateEscape !== null) {
                $predicate['escape'] = self::valueExpression($predicateEscape, $tables);
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
                        $columns = self::subqueryResultColumns($rows[0]);
                        if (($left['type'] ?? null) === 'row') {
                            return array_map(
                                static fn (array $subqueryRow): array => array_map(
                                    static fn (string $column): mixed => $subqueryRow[$column],
                                    $columns
                                ),
                                $rows
                            );
                        }
                        if (count($columns) !== 1) {
                            throw new \InvalidArgumentException('SQLite SELECT SQL IN subquery must return one column');
                        }
                        $column = $columns[0];

                        return array_map(static function (array $subqueryRow) use ($column): array {
                            $affinities = $subqueryRow['__sqlite_column_affinities'] ?? [];

                            return [
                                '__sqlite_in_value' => $subqueryRow[$column],
                                '__sqlite_in_affinity' => is_array($affinities) && isset($affinities[$column]) && is_string($affinities[$column])
                                    ? $affinities[$column]
                                    : 'NONE',
                            ];
                        }, $rows);
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
        if (preg_match('/^(.+?)\s+not\s+null$/i', $sql, $match) === 1) {
            return [
                'operator' => 'IS NOT NULL',
                'left' => self::valueExpression(trim($match[1]), $tables),
            ];
        }

        return [
            'operator' => 'TRUTH',
            'left' => self::valueExpression($sql, $tables),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return list<string>
     */
    private static function subqueryResultColumns(array $row): array
    {
        $columns = [];
        foreach (array_keys($row) as $column) {
                if (
                    !is_string($column)
                    || $column === 'rowid'
                    || self::isInternalMetadataColumn($column)
                ) {
                    continue;
                }

            $columns[] = $column;
        }

        $unqualified = array_values(array_filter(
            $columns,
            static fn (string $column): bool => !str_contains($column, '.')
        ));

        return $unqualified !== [] ? $unqualified : $columns;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<string,mixed> $outerRow
     * @return list<array<string,mixed>>
     */
    private static function correlatedSubqueryRows(string $sql, array $tables, array $outerRow): array
    {
        $plan = self::plan($sql, $tables, [], $outerRow);
        if (isset($plan['compound']) && is_array($plan['compound'])) {
            return self::executeCompoundPlan($plan);
        }

        $plan = self::expandCorrelatedPlan($plan, $tables, $outerRow);

        return self::stripHiddenOrderColumns(SQLiteSelectQuery::execute($plan), $plan);
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<string,mixed> $outerRow
     * @return array<string,mixed>
     */
    private static function expandCorrelatedPlan(array $plan, array $tables, array $outerRow): array
    {
        $rows = $plan['from'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL subquery needs source rows');
        }

        $outerRow = self::qualifyOuterRowForCorrelation($outerRow, $tables);
        $plan['correlatedOuterRow'] = $outerRow;
        $sourceAlias = isset($plan['sourceAlias']) && is_string($plan['sourceAlias']) && $plan['sourceAlias'] !== ''
            ? $plan['sourceAlias']
            : null;
        $expanded = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL subquery source rows must be arrays');
            }
            $qualifiedRow = $sourceAlias === null ? [] : self::qualifiedRows([$row], $sourceAlias)[0];
            $expanded[] = array_merge($outerRow, $qualifiedRow, $row);
        }
        $plan['from'] = $expanded;

        return $plan;
    }

    /**
     * @param array<string,mixed> $outerRow
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,mixed>
     */
    private static function qualifyOuterRowForCorrelation(array $outerRow, array $tables): array
    {
        $qualified = $outerRow;
        foreach ($tables as $table => $rows) {
            if (!is_string($table) || $table === '' || !is_array($rows) || $rows === []) {
                continue;
            }
            $columns = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue 2;
                }
                foreach ($row as $column => $unused) {
                    if (is_string($column) && $column !== '') {
                        $columns[$column] = true;
                    }
                }
            }
            foreach ($columns as $column => $unused) {
                if (array_key_exists($column, $outerRow) && !array_key_exists($table . '.' . $column, $qualified)) {
                    $qualified[$table . '.' . $column] = $outerRow[$column];
                }
            }
        }

        return $qualified;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return ?array<string,mixed>
     */
    private static function betweenPredicateSql(string $sql, array $tables, string $context): ?array
    {
        $betweenOffset = self::keywordOffset($sql, 'BETWEEN');
        if ($betweenOffset === null) {
            return null;
        }

        $leftSql = rtrim(substr($sql, 0, $betweenOffset));
        $negate = false;
        $notOffset = self::trailingKeywordOffset($leftSql, 'NOT');
        if ($notOffset !== null) {
            $negate = true;
            $leftSql = rtrim(substr($leftSql, 0, $notOffset));
        }

        $boundsSql = trim(substr($sql, $betweenOffset + strlen('BETWEEN')));
        $boundsAndOffset = self::firstTopLevelKeywordOffset($boundsSql, 'AND');
        if ($boundsAndOffset === null) {
            throw new \InvalidArgumentException("SQLite SELECT SQL BETWEEN {$context} needs lower and upper operands");
        }

        $lowerSql = trim(substr($boundsSql, 0, $boundsAndOffset));
        $upperSql = trim(substr($boundsSql, $boundsAndOffset + strlen('AND')));
        if ($leftSql === '' || $lowerSql === '' || $upperSql === '') {
            throw new \InvalidArgumentException("SQLite SELECT SQL BETWEEN {$context} needs lower and upper operands");
        }

        $tailAndOffset = self::firstTopLevelKeywordOffset($upperSql, 'AND');
        $tailComparison = self::topLevelComparisonExpressionOperatorIn($upperSql, ['IS NOT', 'NOT LIKE', 'NOT GLOB', 'LIKE', 'GLOB', 'IS', '==', '!=', '<>', '=']);
        $tailOffset = null;
        $tailOperator = null;
        if ($tailComparison !== null) {
            [$tailOffset, $tailOperator] = $tailComparison;
        }
        if ($tailAndOffset !== null && ($tailOffset === null || $tailAndOffset < $tailOffset)) {
            $tailOffset = $tailAndOffset;
            $tailOperator = 'AND';
        }

        if ($tailOffset !== null && $tailOperator !== null) {
            $betweenUpperSql = trim(substr($upperSql, 0, $tailOffset));
            $tailRightSql = trim(substr($upperSql, $tailOffset + strlen($tailOperator)));
            if ($betweenUpperSql === '' || $tailRightSql === '') {
                throw new \InvalidArgumentException("SQLite SELECT SQL BETWEEN {$context} tail needs both operands");
            }

            $between = [
                'operator' => $negate ? 'NOT BETWEEN' : 'BETWEEN',
                'left' => self::valueExpression($leftSql, $tables),
                'lower' => self::valueExpression($lowerSql, $tables),
                'upper' => self::valueExpression($betweenUpperSql, $tables),
            ];

            if ($tailOperator === 'AND') {
                return [
                    'operator' => 'AND',
                    'terms' => [
                        $between,
                        self::predicate($tailRightSql, $tables),
                    ],
                ];
            }

            return [
                'operator' => strtoupper($tailOperator),
                'left' => [
                    'type' => 'predicate',
                    'predicate' => $between,
                ],
                'right' => self::valueExpression($tailRightSql, $tables),
            ];
        }

        return [
            'operator' => $negate ? 'NOT BETWEEN' : 'BETWEEN',
            'left' => self::valueExpression($leftSql, $tables),
            'lower' => self::valueExpression($lowerSql, $tables),
            'upper' => self::valueExpression($upperSql, $tables),
        ];
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
            if (
                (preg_match('/^select\s+/i', $subquerySql) === 1 && self::unwrapParenthesizedExpression($sql) === $subquerySql)
                || preg_match('/^values(?:\s+|\()/i', $subquerySql) === 1
            ) {
                return [
                    'type' => 'subquery',
                    'subquerySql' => $subquerySql,
                    'subquery' => static fn (array $row): array => self::correlatedSubqueryRows($subquerySql, $tables, $row),
                ];
            }
        }
        $unwrapped = self::unwrapParenthesizedExpression($sql);
        if ($unwrapped !== $sql) {
            if (preg_match('/^values\s+/i', $unwrapped) === 1) {
                return self::scalarValuesExpression($unwrapped);
            }

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

        if (preg_match('/^(not\s+)?exists\s*\(\s*(select\s+.+)\)$/is', $sql, $match) === 1) {
            $subquerySql = trim($match[2]);

            return [
                'type' => 'predicate',
                'predicate' => [
                    'operator' => isset($match[1]) && trim($match[1]) !== '' ? 'NOT EXISTS' : 'EXISTS',
                    'subquery' => static fn (array $row): array => self::correlatedSubqueryRows($subquerySql, $tables, $row),
                ],
            ];
        }

        $orTerms = self::splitKeyword($sql, 'OR');
        if (count($orTerms) > 1) {
            return [
                'type' => 'predicate',
                'predicate' => [
                    'operator' => 'OR',
                    'terms' => array_map(static fn (string $term): array => self::predicate($term, $tables), $orTerms),
                ],
            ];
        }

        $andTerms = self::splitKeyword($sql, 'AND');
        if (count($andTerms) > 1) {
            return [
                'type' => 'predicate',
                'predicate' => [
                    'operator' => 'AND',
                    'terms' => array_map(static fn (string $term): array => self::predicate($term, $tables), $andTerms),
                ],
            ];
        }

        $betweenPredicate = self::betweenPredicateSql($sql, $tables, 'expression');
        if ($betweenPredicate !== null) {
            return [
                'type' => 'predicate',
                'predicate' => $betweenPredicate,
            ];
        }

        if (preg_match('/^(.+\s+is\s+(?:not\s+)?(?:true|false))\s+COLLATE\s+([A-Za-z_][A-Za-z0-9_]*)$/is', $sql, $match) === 1) {
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

        if (preg_match('/^(.+?)\s+is\s+(not\s+)?(true|false)$/is', $sql, $match) === 1) {
            $expected = strtoupper($match[3]);

            return [
                'type' => 'predicate',
                'predicate' => [
                    'operator' => 'IS ' . (isset($match[2]) && trim($match[2]) !== '' ? 'NOT ' : '') . $expected,
                    'left' => self::valueExpression(trim($match[1]), $tables),
                ],
            ];
        }

        $orTerms = self::splitTopLevelByKeyword($sql, 'OR');
        if (count($orTerms) > 1) {
            return [
                'type' => 'predicate',
                'predicate' => [
                    'operator' => 'OR',
                    'terms' => array_map(static fn (string $term): array => self::predicate($term, $tables), $orTerms),
                ],
            ];
        }

        $andTerms = self::splitTopLevelByKeyword($sql, 'AND');
        if (count($andTerms) > 1) {
            return [
                'type' => 'predicate',
                'predicate' => [
                    'operator' => 'AND',
                    'terms' => array_map(static fn (string $term): array => self::predicate($term, $tables), $andTerms),
                ],
            ];
        }

        if (preg_match('/^(.+?)\s+is\s+not\s+null$/is', $sql, $match) === 1) {
            return [
                'type' => 'predicate',
                'predicate' => [
                    'operator' => 'IS NOT NULL',
                    'left' => self::valueExpression(trim($match[1]), $tables),
                ],
            ];
        }

        if (preg_match('/^(.+?)\s+is\s+null$/is', $sql, $match) === 1) {
            $leftSql = trim($match[1]);
            if ($leftSql !== '' && !in_array($leftSql[strlen($leftSql) - 1], ['+', '-', '~', '*', '/', '%', '|', '&', '<', '>', '='], true)) {
                return [
                    'type' => 'predicate',
                    'predicate' => [
                        'operator' => 'IS NULL',
                        'left' => self::valueExpression($leftSql, $tables),
                    ],
                ];
            }
        }

        if (preg_match('/^(.+?)\s+isnull$/is', $sql, $match) === 1) {
            $leftSql = trim($match[1]);
            if ($leftSql !== '' && !in_array($leftSql[strlen($leftSql) - 1], ['+', '-', '~', '*', '/', '%', '|', '&', '<', '>', '='], true)) {
                return [
                    'type' => 'predicate',
                    'predicate' => [
                        'operator' => 'IS NULL',
                        'left' => self::valueExpression($leftSql, $tables),
                    ],
                ];
            }
        }

        if (preg_match('/^(.+?)\s+not\s+null$/is', $sql, $match) === 1) {
            $leftSql = trim($match[1]);
            if ($leftSql !== '' && !in_array($leftSql[strlen($leftSql) - 1], ['+', '-', '~', '*', '/', '%', '|', '&', '<', '>', '='], true)) {
                return [
                    'type' => 'predicate',
                    'predicate' => [
                        'operator' => 'IS NOT NULL',
                        'left' => self::valueExpression($leftSql, $tables),
                    ],
                ];
            }
        }

        if (preg_match('/^(.+?)\s+notnull$/is', $sql, $match) === 1) {
            $leftSql = trim($match[1]);
            if ($leftSql !== '' && !in_array($leftSql[strlen($leftSql) - 1], ['+', '-', '~', '*', '/', '%', '|', '&', '<', '>', '='], true)) {
                return [
                    'type' => 'predicate',
                    'predicate' => [
                        'operator' => 'IS NOT NULL',
                        'left' => self::valueExpression($leftSql, $tables),
                    ],
                ];
            }
        }

        if (preg_match('/^(like|glob)\s*\((.*)\)$/is', $sql, $match) === 1) {
            $argumentSql = trim($match[2]);
            $arguments = $argumentSql === ''
                ? []
                : array_map(static fn (string $argument): array => self::valueExpression($argument, $tables), self::splitTopLevel($argumentSql, ','));

            return [
                'type' => 'function',
                'name' => $match[1],
                'arguments' => $arguments,
            ];
        }

        $comparison = self::topLevelComparisonExpressionOperator($sql);
        if ($comparison !== null) {
            [$offset, $operator] = $comparison;
            $left = trim(substr($sql, 0, $offset));
            $right = trim(substr($sql, $offset + strlen($operator)));
            if ($left === '' || $right === '') {
                throw new \InvalidArgumentException("SQLite SELECT SQL expression {$operator} needs both operands");
            }

            $predicateRight = $right;
            $predicateEscape = null;
            if (strcasecmp($operator, 'LIKE') === 0 || strcasecmp($operator, 'NOT LIKE') === 0) {
                $escapeParts = self::splitTopLevelByKeyword($right, 'ESCAPE');
                if (count($escapeParts) > 2) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL LIKE expression supports one ESCAPE clause');
                }
                if (count($escapeParts) === 2) {
                    if ($escapeParts[0] === '' || $escapeParts[1] === '') {
                        throw new \InvalidArgumentException('SQLite SELECT SQL LIKE ESCAPE expression needs pattern and escape operands');
                    }
                    $predicateRight = $escapeParts[0];
                    $predicateEscape = $escapeParts[1];
                }
            }

            $predicate = [
                'operator' => strtoupper($operator),
                'left' => self::valueExpression($left, $tables),
                'right' => self::valueExpression($predicateRight, $tables),
            ];
            if ($predicateEscape !== null) {
                $predicate['escape'] = self::valueExpression($predicateEscape, $tables);
            }

            return [
                'type' => 'predicate',
                'predicate' => $predicate,
            ];
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

        if (preg_match('/^not\s+(.+)$/is', $sql, $match) === 1) {
            return [
                'type' => 'unary',
                'operator' => 'NOT',
                'operand' => self::valueExpression($match[1], $tables),
            ];
        }

        foreach ([['&', '|', '<<', '>>'], ['+', '-'], ['*', '/', '%'], ['||', '->>', '->']] as $operators) {
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
        if (preg_match('/^[+-]?0[xX][0-9A-Fa-f]+$/', $sql) === 1) {
            return ['type' => 'literal', 'value' => self::hexIntegerLiteralValue($sql)];
        }
        if (preg_match('/^[+-]?[0-9]+$/', $sql) === 1) {
            return ['type' => 'literal', 'value' => self::integerLiteralValue($sql)];
        }
        if (preg_match('/^[+-]?(?:(?:[0-9]+\.[0-9]*|\.[0-9]+)(?:[eE][+-]?[0-9]+)?|[0-9]+[eE][+-]?[0-9]+)$/', $sql) === 1) {
            return ['type' => 'literal', 'value' => (float) $sql, 'literalText' => self::realLiteralText($sql)];
        }
        if (preg_match('/^[+\-~]\s*(.+)$/s', $sql, $match) === 1) {
            return [
                'type' => 'unary',
                'operator' => $sql[0],
                'operand' => self::valueExpression($match[1], $tables),
            ];
        }

        if (preg_match('/^cast\s*\((.+)\s+as\s+([A-Za-z][A-Za-z0-9_\s]*(?:\([0-9\s,]+\))?)\)$/is', $sql, $match) === 1) {
            return [
                'type' => 'cast',
                'operand' => self::valueExpression($match[1], $tables),
                'target' => trim($match[2]),
            ];
        }

        $filter = null;
        $filterOffset = self::keywordOffset($sql, 'FILTER');
        $filterTail = $filterOffset === null ? '' : ltrim(substr($sql, $filterOffset + strlen('FILTER')));
        if ($filterOffset !== null && $filterOffset > 0 && str_starts_with($filterTail, '(')) {
            $filterSql = trim(substr($sql, $filterOffset + strlen('FILTER')));
            $sql = trim(substr($sql, 0, $filterOffset));
            if (!str_starts_with($filterSql, '(') || !str_ends_with($filterSql, ')')) {
                throw new \InvalidArgumentException('SQLite SELECT SQL aggregate FILTER clause must be parenthesized');
            }
            $filterBody = self::unwrapParenthesizedExpression($filterSql);
            if (preg_match('/^WHERE\s+(.+)$/is', $filterBody, $filterMatch) !== 1) {
                throw new \InvalidArgumentException('SQLite SELECT SQL aggregate FILTER clause needs WHERE');
            }
            $filter = self::predicate(trim($filterMatch[1]), $tables);
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\((.*)\)$/s', $sql, $match) === 1) {
            $argumentSql = trim($match[2]);
            $distinct = false;
            if (preg_match('/^distinct(?:\s+|$)(.+)$/is', $argumentSql, $distinctMatch) === 1) {
                $distinct = true;
                $argumentSql = trim($distinctMatch[1]);
                if ($argumentSql === '' || $argumentSql === '*') {
                    throw new \InvalidArgumentException('SQLite SELECT SQL DISTINCT aggregate needs a value argument');
                }
            }
            $orderBy = null;
            $orderParts = self::splitTopLevelByKeyword($argumentSql, 'ORDER BY');
            if (count($orderParts) > 2) {
                throw new \InvalidArgumentException('SQLite SELECT SQL aggregate supports one ORDER BY clause');
            }
            if (count($orderParts) === 2) {
                $argumentSql = trim($orderParts[0]);
                $orderSql = trim($orderParts[1]);
                if ($argumentSql === '' || $orderSql === '') {
                    throw new \InvalidArgumentException('SQLite SELECT SQL aggregate ORDER BY needs value and order expression');
                }
                $orderTerms = self::aggregateOrderTerms($orderSql, $tables);
                $orderBy = $orderTerms[0]['expression'];
                $orderDirection = $orderTerms[0]['direction'];
            }

            if ($argumentSql === '*') {
                $arguments = [['type' => 'wildcard']];
            } else {
                $arguments = $argumentSql === '' ? [] : array_map(static fn (string $argument): array => self::valueExpression($argument, $tables), self::splitTopLevel($argumentSql, ','));
            }

            $function = ['type' => 'function', 'name' => $match[1], 'arguments' => $arguments];
            if ($distinct) {
                $function['distinct'] = true;
            }
            if ($orderBy !== null) {
                $function['orderBy'] = $orderBy;
                $function['orderDirection'] = $orderDirection;
                $function['orderByTerms'] = $orderTerms;
            }
            if ($filter !== null) {
                $function['filter'] = $filter;
            }

            return $function;
        }
        if ($filter !== null) {
            throw new \InvalidArgumentException('SQLite SELECT SQL FILTER clause needs an aggregate function');
        }
        if (preg_match('/^(.+?)\s+(not\s+)?in\s*\((.*)\)$/is', $sql, $match) === 1) {
            $valuesSql = trim($match[3]);
            if (preg_match('/^select\s+/i', $valuesSql) === 1) {
                $left = self::valueExpression(trim($match[1]), $tables);

                return [
                    'type' => 'predicate',
                    'predicate' => [
                        'operator' => isset($match[2]) && trim($match[2]) !== '' ? 'NOT IN' : 'IN',
                        'left' => $left,
                        'valuesSubquery' => static function (array $row) use ($valuesSql, $tables, $left): array {
                            $rows = self::correlatedSubqueryRows($valuesSql, $tables, $row);
                            if ($rows === []) {
                                return [];
                            }
                            $columns = self::subqueryResultColumns($rows[0]);
                            if (($left['type'] ?? null) === 'row') {
                                return array_map(
                                    static fn (array $subqueryRow): array => array_map(
                                        static fn (string $column): mixed => $subqueryRow[$column],
                                        $columns
                                    ),
                                    $rows
                                );
                            }
                            if (count($columns) !== 1) {
                                throw new \InvalidArgumentException('SQLite SELECT SQL IN subquery expression must return one column');
                            }
                            $column = $columns[0];

                            return array_map(static function (array $subqueryRow) use ($column): array {
                                $affinities = $subqueryRow['__sqlite_column_affinities'] ?? [];

                                return [
                                    '__sqlite_in_value' => $subqueryRow[$column],
                                    '__sqlite_in_affinity' => is_array($affinities) && isset($affinities[$column]) && is_string($affinities[$column])
                                        ? $affinities[$column]
                                        : 'NONE',
                                ];
                            }, $rows);
                        },
                    ],
                ];
            }

            return [
                'type' => 'predicate',
                'predicate' => [
                    'operator' => isset($match[2]) && trim($match[2]) !== '' ? 'NOT IN' : 'IN',
                    'left' => self::valueExpression(trim($match[1]), $tables),
                    'values' => $valuesSql === ''
                        ? []
                        : array_map(static fn (string $value): array => self::valueExpression($value, $tables), self::splitTopLevel($valuesSql, ',')),
                ],
            ];
        }
        if (preg_match('/^[+-]?0[xX][0-9A-Fa-f]+$/', $sql) === 1) {
            return ['type' => 'literal', 'value' => self::hexIntegerLiteralValue($sql)];
        }
        if (preg_match('/^[+-]?[0-9]+$/', $sql) === 1) {
            return ['type' => 'literal', 'value' => self::integerLiteralValue($sql)];
        }
        if (preg_match('/^[+-]?(?:(?:[0-9]+\.[0-9]*|\.[0-9]+)(?:[eE][+-]?[0-9]+)?|[0-9]+[eE][+-]?[0-9]+)$/', $sql) === 1) {
            return ['type' => 'literal', 'value' => (float) $sql, 'literalText' => self::realLiteralText($sql)];
        }
        if (strcasecmp($sql, 'NULL') === 0) {
            return ['type' => 'literal', 'value' => null];
        }
        if (strcasecmp($sql, 'TRUE') === 0) {
            return ['type' => 'literal', 'value' => 1];
        }
        if (strcasecmp($sql, 'FALSE') === 0) {
            return ['type' => 'literal', 'value' => 0];
        }
        if (strcasecmp($sql, 'CURRENT_TIME') === 0) {
            return ['type' => 'literal', 'value' => gmdate('H:i:s')];
        }
        if (strcasecmp($sql, 'CURRENT_DATE') === 0) {
            return ['type' => 'literal', 'value' => gmdate('Y-m-d')];
        }
        if (strcasecmp($sql, 'CURRENT_TIMESTAMP') === 0) {
            return ['type' => 'literal', 'value' => gmdate('Y-m-d H:i:s')];
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
        $columnName = self::columnIdentifierExpression($sql);
        if ($columnName !== null) {
            return ['type' => 'column', 'name' => $columnName];
        }
        if (str_starts_with($sql, "'") && str_ends_with($sql, "'")) {
            return ['type' => 'literal', 'value' => str_replace("''", "'", substr($sql, 1, -1))];
        }
        if (str_starts_with($sql, '"') && str_ends_with($sql, '"')) {
            return ['type' => 'literal', 'value' => str_replace('""', '"', substr($sql, 1, -1))];
        }

        throw new \InvalidArgumentException("SQLite SELECT SQL expression {$sql} is not supported");
    }

    /**
     * @return array<string,mixed>
     */
    private static function scalarValuesExpression(string $sql): array
    {
        $rows = self::executeValuesClause($sql);
        $first = $rows[0] ?? null;
        if (!is_array($first) || !array_key_exists('column1', $first)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL scalar VALUES expression needs at least one row and column');
        }
        if (count($first) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL scalar VALUES expression must return one column');
        }

        return ['type' => 'literal', 'value' => $first['column1']];
    }

    private static function realLiteralText(string $sql): string
    {
        $value = (float) $sql;
        if (is_finite($value) && floor($value) === $value) {
            return sprintf('%.1f', $value);
        }

        return (string) $value;
    }

    private static function integerLiteralValue(string $sql): int|float
    {
        $negative = str_starts_with($sql, '-');
        $digits = ltrim($sql, '+-');
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }

        $limit = $negative ? '9223372036854775808' : '9223372036854775807';
        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            return (float) $sql;
        }
        if ($negative && $digits === '9223372036854775808') {
            return PHP_INT_MIN;
        }

        $value = (int) $digits;

        return $negative ? -$value : $value;
    }

    private static function hexIntegerLiteralValue(string $sql): int
    {
        if (preg_match('/^([+-]?)0[xX]([0-9A-Fa-f]+)$/', $sql, $match) !== 1) {
            throw new \InvalidArgumentException("SQLite SELECT SQL expression {$sql} is not a hexadecimal integer literal");
        }

        $sign = $match[1];
        $digits = ltrim($match[2], '0');
        if ($digits === '') {
            return 0;
        }
        if (strlen($digits) > 16) {
            throw new \InvalidArgumentException("hex literal too big: {$sql}");
        }

        $value = self::signedHex64Value(strtolower(str_pad($digits, 16, '0', STR_PAD_LEFT)));
        if ($sign !== '-') {
            return $value;
        }
        if ($value === PHP_INT_MIN) {
            throw new \InvalidArgumentException("hex literal too big: {$sql}");
        }

        return -$value;
    }

    private static function signedHex64Value(string $hex): int
    {
        if (strlen($hex) !== 16 || preg_match('/^[0-9a-f]{16}$/', $hex) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL hexadecimal literal is malformed');
        }
        if (strcmp($hex, '8000000000000000') < 0) {
            return self::hexMagnitudeValue($hex);
        }
        if ($hex === '8000000000000000') {
            return PHP_INT_MIN;
        }

        $complement = '';
        for ($i = 0; $i < 16; $i++) {
            $complement .= dechex(15 - hexdec($hex[$i]));
        }

        return -(self::hexMagnitudeValue($complement) + 1);
    }

    private static function hexMagnitudeValue(string $hex): int
    {
        $value = 0;
        $length = strlen($hex);
        for ($i = 0; $i < $length; $i++) {
            $digit = hexdec($hex[$i]);
            if (!is_int($digit)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL hexadecimal literal is malformed');
            }
            if ($value > intdiv(PHP_INT_MAX - $digit, 16)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL hexadecimal literal magnitude is too large');
            }
            $value = ($value * 16) + $digit;
        }

        return $value;
    }

    private static function columnIdentifierExpression(string $sql): ?string
    {
        $sql = trim($sql);
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $sql) === 1) {
            return $sql;
        }

        if (preg_match('/^\[([^\]]+)\]$/', $sql, $match) === 1) {
            return self::normalizedGeneratedColumnName($match[1]);
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*)\.\[([^\]]+)\]$/', $sql, $match) === 1) {
            return $match[1] . '.' . self::normalizedGeneratedColumnName($match[2]);
        }

        return null;
    }

    private static function normalizedGeneratedColumnName(string $name): string
    {
        $name = trim($name);
        if (preg_match('/^count\s*\(\s*\*\s*\)$/i', $name) === 1) {
            return 'countAll';
        }
        if (preg_match('/^count\s*\(\s*([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\s*\)$/i', $name) === 1) {
            return 'countValue';
        }
        if (preg_match('/^(min|max|sum|total|avg)\s*\(\s*([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\s*\)$/i', $name, $match) === 1) {
            return strtolower($match[1]);
        }

        self::assertIdentifier($name, 'SQLite SELECT SQL bracket-quoted column');

        return $name;
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
            return null;
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
        $filter = null;
        $filterOffset = self::keywordOffset($functionSql, 'FILTER');
        if ($filterOffset !== null) {
            $filterSql = trim(substr($functionSql, $filterOffset + strlen('FILTER')));
            $functionSql = trim(substr($functionSql, 0, $filterOffset));
            if (!str_starts_with($filterSql, '(') || !str_ends_with($filterSql, ')')) {
                throw new \InvalidArgumentException('SQLite SELECT SQL window FILTER clause must be parenthesized');
            }
            $filterBody = self::unwrapParenthesizedExpression($filterSql);
            if (preg_match('/^WHERE\s+(.+)$/is', $filterBody, $filterMatch) !== 1) {
                throw new \InvalidArgumentException('SQLite SELECT SQL window FILTER clause needs WHERE');
            }
            $filter = self::predicate(trim($filterMatch[1]), $tables);
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\((.*)\)$/', $functionSql, $match) !== 1) {
            return null;
        }

        $name = strtolower($match[1]);
        $argumentSql = trim($match[2]);
        $arguments = [];
        $distinct = false;
        $aggregateOrderBy = null;
        if ($argumentSql !== '') {
            if (preg_match('/^distinct(?:\s+|$)(.+)$/is', $argumentSql, $distinctMatch) === 1) {
                $distinct = true;
                $argumentSql = trim($distinctMatch[1]);
                if ($argumentSql === '' || $argumentSql === '*') {
                    throw new \InvalidArgumentException('SQLite SELECT SQL DISTINCT window aggregate needs a value argument');
                }
            }
            $orderParts = self::splitTopLevelByKeyword($argumentSql, 'ORDER BY');
            if (count($orderParts) > 2) {
                throw new \InvalidArgumentException('SQLite SELECT SQL window aggregate supports one ORDER BY clause');
            }
            if (count($orderParts) === 2) {
                $argumentSql = trim($orderParts[0]);
                $orderSql = trim($orderParts[1]);
                if ($argumentSql === '' || $orderSql === '') {
                    throw new \InvalidArgumentException('SQLite SELECT SQL window aggregate ORDER BY needs value and order expression');
                }
                $aggregateOrderBy = self::aggregateOrderTerms($orderSql, $tables);
            }
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
            self::assertOrderedRangeOrGroupsFrame($orderBy, $frame);
        }

        $supported = ['row_number', 'rank', 'dense_rank', 'percent_rank', 'cume_dist', 'ntile', 'lag', 'lead', 'first_value', 'last_value', 'nth_value', 'count', 'sum', 'total', 'avg', 'min', 'max', 'group_concat', 'string_agg', 'json_group_array', 'jsonb_group_array', 'json_group_object', 'jsonb_group_object'];
        if (!in_array($name, $supported, true)) {
            throw new \InvalidArgumentException("SQLite SELECT SQL window function {$name} is not supported");
        }
        self::assertWindowFunctionArgumentCount($name, $arguments);

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
        if ($filter !== null) {
            $expression['filter'] = $filter;
        }
        if ($distinct) {
            $expression['distinct'] = true;
        }
        if ($aggregateOrderBy !== null) {
            $expression['aggregateOrderBy'] = $aggregateOrderBy;
        }

        return $expression;
    }

    /**
     * @param list<array{expression:array<string,mixed>,direction:string}> $orderBy
     * @param array{unit:string,preceding:int|float,following:int|float,exclude:string} $frame
     */
    private static function assertOrderedRangeOrGroupsFrame(array $orderBy, array $frame): void
    {
        if ($orderBy === [] && in_array($frame['unit'], ['RANGE', 'GROUPS'], true)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL RANGE/GROUPS window frame needs ORDER BY');
        }
        if (
            $frame['unit'] === 'RANGE'
            && count($orderBy) !== 1
            && (self::frameBoundaryUsesOffset((string) ($frame['startBoundary'] ?? '')) || self::frameBoundaryUsesOffset((string) ($frame['endBoundary'] ?? '')))
        ) {
            throw new \InvalidArgumentException('SQLite SELECT SQL RANGE offset window frame requires exactly one ORDER BY expression');
        }
    }

    private static function frameBoundaryUsesOffset(string $boundary): bool
    {
        return preg_match('/^\s*[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)\s+(?:PRECEDING|FOLLOWING)\s*$/i', $boundary) === 1;
    }

    /**
     * @param list<array<string,mixed>> $arguments
     */
    private static function assertWindowFunctionArgumentCount(string $name, array $arguments): void
    {
        $count = count($arguments);
        if (in_array($name, ['row_number', 'rank', 'dense_rank', 'percent_rank', 'cume_dist'], true) && $count !== 0) {
            throw new \InvalidArgumentException("SQLite SELECT SQL window function {$name} takes no arguments");
        }
        if ($name === 'ntile' && $count !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL ntile() window function takes one argument');
        }
        if (in_array($name, ['lag', 'lead'], true) && ($count < 1 || $count > 3)) {
            throw new \InvalidArgumentException("SQLite SELECT SQL {$name}() window function takes one to three arguments");
        }
        if (in_array($name, ['first_value', 'last_value'], true) && $count !== 1) {
            throw new \InvalidArgumentException("SQLite SELECT SQL {$name}() window function takes one argument");
        }
        if ($name === 'nth_value' && $count !== 2) {
            throw new \InvalidArgumentException('SQLite SELECT SQL nth_value() window function takes two arguments');
        }
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

        if (preg_match('/^(ROWS|RANGE|GROUPS)\s+BETWEEN\s+(.+?)\s+AND\s+(.+)$/i', $sql, $match) === 1) {
            $startSql = trim($match[2]);
            $endSql = trim($match[3]);
            $start = self::windowFrameBound($startSql);
            $end = self::windowFrameBound($endSql);
            $preceding = $start['direction'] === 'PRECEDING' ? $start['offset'] : 0;
            $following = $end['direction'] === 'FOLLOWING' ? $end['offset'] : 0;

            return [
                'unit' => strtoupper($match[1]),
                'preceding' => $preceding,
                'following' => $following,
                'exclude' => $exclude,
                'startBoundary' => $startSql,
                'endBoundary' => $endSql,
            ];
        }

        if (preg_match('/^(ROWS|RANGE|GROUPS)\s+(.+)$/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL window frame supports bounded frames');
        }

        $startSql = trim($match[2]);
        $endSql = 'CURRENT ROW';
        [$preceding, $following] = self::windowFrameBounds($startSql, $endSql);
        return [
            'unit' => strtoupper($match[1]),
            'preceding' => $preceding,
            'following' => $following,
            'exclude' => $exclude,
            'startBoundary' => $startSql,
            'endBoundary' => $endSql,
        ];
    }

    /**
     * @return array{0:int|float,1:int|float}
     */
    private static function windowFrameBounds(string $startSql, string $endSql): array
    {
        $start = self::windowFrameBound($startSql);
        $end = self::windowFrameBound($endSql);

        if ($start['direction'] === 'FOLLOWING' || $end['direction'] === 'PRECEDING') {
            throw new \InvalidArgumentException('SQLite SELECT SQL window frame supports start PRECEDING/CURRENT and end CURRENT/FOLLOWING bounds');
        }

        return [
            $start['direction'] === 'PRECEDING' ? $start['offset'] : 0,
            $end['direction'] === 'FOLLOWING' ? $end['offset'] : 0,
        ];
    }

    /**
     * @return array{direction:string,offset:int|float}
     */
    private static function windowFrameBound(string $sql): array
    {
        if (strcasecmp($sql, 'CURRENT ROW') === 0) {
            return ['direction' => 'CURRENT', 'offset' => 0];
        }
        if (strcasecmp($sql, 'UNBOUNDED PRECEDING') === 0) {
            return ['direction' => 'PRECEDING', 'offset' => INF];
        }
        if (strcasecmp($sql, 'UNBOUNDED FOLLOWING') === 0) {
            return ['direction' => 'FOLLOWING', 'offset' => INF];
        }
        if (preg_match('/^(.+?)\s+(PRECEDING|FOLLOWING)$/i', $sql, $match) === 1) {
            return [
                'direction' => strtoupper($match[2]),
                'offset' => self::windowFrameOffsetValue(trim($match[1])),
            ];
        }

        throw new \InvalidArgumentException('SQLite SELECT SQL window frame bound must be CURRENT ROW or N PRECEDING/FOLLOWING');
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

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{0:array<string,mixed>,1:string}
     */
    private static function aggregateOrderTerm(string $term, array $tables): array
    {
        $term = trim($term);
        $nulls = null;
        if (preg_match('/\s+NULLS\s+(FIRST|LAST)\s*$/i', $term, $match) === 1) {
            $nulls = strtoupper($match[1]);
            $term = trim(substr($term, 0, -strlen($match[0])));
        }
        $direction = 'ASC';
        if (preg_match('/^(.+?)\s+(ASC|DESC)$/i', $term, $match) === 1) {
            $term = trim($match[1]);
            $direction = strtoupper($match[2]);
        }
        if ($term === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL aggregate ORDER BY term cannot be empty');
        }

        return [self::valueExpression($term, $tables), $direction, $nulls];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array{expression:array<string,mixed>,direction:string}>
     */
    private static function aggregateOrderTerms(string $sql, array $tables): array
    {
        $terms = [];
        foreach (self::splitTopLevel($sql, ',') as $term) {
            [$expression, $direction, $nulls] = self::aggregateOrderTerm($term, $tables);
            $orderTerm = [
                'expression' => $expression,
                'direction' => $direction,
            ];
            if ($nulls !== null) {
                $orderTerm['nulls'] = $nulls;
            }
            $terms[] = $orderTerm;
        }
        if ($terms === []) {
            throw new \InvalidArgumentException('SQLite SELECT SQL aggregate ORDER BY needs at least one term');
        }

        return $terms;
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
                if ($operator === '-' && ($sql[$offset + 1] ?? null) === '>') {
                    continue;
                }
                if (($operator === '+' || $operator === '-') && (self::isUnarySign($sql, $offset) || self::isExponentSign($sql, $offset))) {
                    continue;
                }

                return [$offset, $operator];
            }
        }

        return null;
    }

    /**
     * @return array{0:int,1:string}|null
     */
    private static function topLevelComparisonExpressionOperator(string $sql): ?array
    {
        return self::topLevelComparisonExpressionOperatorIn($sql, ['IS NOT DISTINCT FROM', 'IS DISTINCT FROM', 'IS NOT', 'NOT REGEXP', 'NOT MATCH', 'NOT LIKE', 'NOT GLOB', 'LIKE', 'GLOB', 'REGEXP', 'MATCH', 'IS', '==', '!=', '<>', '='])
            ?? self::topLevelComparisonExpressionOperatorIn($sql, ['>=', '<=', '>', '<']);
    }

    /**
     * @param list<string> $operators
     * @return array{0:int,1:string}|null
     */
    private static function topLevelComparisonExpressionOperatorIn(string $sql, array $operators): ?array
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
                if ($offset < 0 || strcasecmp(substr($sql, $offset, strlen($operator)), $operator) !== 0) {
                    continue;
                }
                if ($operator === '<' && (($sql[$offset - 1] ?? null) === '<' || ($sql[$offset + 1] ?? null) === '<')) {
                    continue;
                }
                if ($operator === '>' && (($sql[$offset - 1] ?? null) === '>' || ($sql[$offset + 1] ?? null) === '>')) {
                    continue;
                }
                if (ctype_alpha($operator[0]) && !self::keywordBounded($sql, $offset, strlen($operator))) {
                    continue;
                }
                if (!ctype_alpha($operator[0]) && !self::symbolOperatorBounded($sql, $offset, $operator)) {
                    continue;
                }

                return [$offset, $operator];
            }
        }

        return null;
    }

    private static function symbolOperatorBounded(string $sql, int $offset, string $operator): bool
    {
        $before = $sql[$offset - 1] ?? '';
        $after = $sql[$offset + strlen($operator)] ?? '';
        if (($operator === '>' || $operator === '<' || $operator === '=') && ($before === '<' || $before === '>' || $before === '!' || $before === '-' || $after === '=' || $after === '>' || $after === '<')) {
            return false;
        }
        if (($operator === '>=' || $operator === '<=' || $operator === '<>' || $operator === '!=' || $operator === '==') && ($before === '<' || $before === '>' || $before === '!' || $before === '=' || $after === '=' || $after === '>')) {
            return false;
        }

        return true;
    }

    private static function isUnarySign(string $sql, int $offset): bool
    {
        $before = rtrim(substr($sql, 0, $offset));
        if ($before === '') {
            return true;
        }

        return str_contains('+-*/%&|~(<', substr($before, -1));
    }

    private static function isExponentSign(string $sql, int $offset): bool
    {
        $before = $sql[$offset - 1] ?? '';
        $after = $sql[$offset + 1] ?? '';

        return ($before === 'e' || $before === 'E') && ctype_digit($after);
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return array<string,mixed>
     */
    private static function groupBy(string $sql, array $select, array $aggregateExpressions = [], bool $specificAggregates = false): array
    {
        $columns = [];
        $expressions = [];
        $collationExpressions = [];
        foreach (self::splitTopLevel($sql, ',') as $index => $term) {
            $term = trim($term);
            if (preg_match('/^[1-9][0-9]*$/', $term) === 1) {
                $ordinal = (int) $term;
                if (!isset($select[$ordinal - 1]) || !is_array($select[$ordinal - 1])) {
                    throw new \InvalidArgumentException("SQLite SELECT SQL GROUP BY ordinal {$ordinal} is out of range");
                }
                $ordinalExpression = $select[$ordinal - 1]['sourceExpression'] ?? $select[$ordinal - 1];
                if (!is_array($ordinalExpression)) {
                    throw new \InvalidArgumentException("SQLite SELECT SQL GROUP BY ordinal {$ordinal} expression is malformed");
                }
                if (($ordinalExpression['type'] ?? null) === 'column' && isset($ordinalExpression['name']) && is_string($ordinalExpression['name']) && $ordinalExpression['name'] !== '') {
                    $columns[] = $ordinalExpression['name'];
                    $collationExpressions[$ordinalExpression['name']] = $ordinalExpression;
                    continue;
                }

                $column = '__groupByExpression' . $index;
                $columns[] = $column;
                unset($ordinalExpression['alias'], $ordinalExpression['hiddenOrderColumn']);
                $expressions[] = [
                    'column' => $column,
                    'expression' => $ordinalExpression,
                ];
                $collationExpressions[$column] = $ordinalExpression;
                continue;
            }
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/', $term) === 1) {
                $aliasExpression = self::groupByAliasExpression($term, $select);
                if ($aliasExpression !== null) {
                    $column = '__groupByExpression' . $index;
                    $columns[] = $column;
                    $expressions[] = [
                        'column' => $column,
                        'expression' => $aliasExpression,
                    ];
                    $collationExpressions[$column] = $aliasExpression;
                    continue;
                }
                $columns[] = $term;
                $collationExpressions[$term] = [
                    'type' => 'column',
                    'name' => $term,
                ];
                continue;
            }

            $column = '__groupByExpression' . $index;
            $expression = self::valueExpression($term);
            $columns[] = $column;
            $expressions[] = [
                'column' => $column,
                'expression' => $expression,
            ];
            $collationExpressions[$column] = $expression;
        }
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite SELECT SQL GROUP BY needs at least one column');
        }

        $groupBy = [
            'columns' => $columns,
            'valueColumn' => $specificAggregates ? null : self::aggregateValueColumn($select, $aggregateExpressions),
            'jsonAggregates' => self::jsonAggregateSpecs($select, $aggregateExpressions),
            'filteredAggregates' => self::aggregateSpecs($select, $aggregateExpressions, $specificAggregates),
        ];
        $sampleAggregates = self::minMaxAggregateExpressions($select, $aggregateExpressions);
        if ($sampleAggregates !== []) {
            $groupBy['sampleAggregates'] = $sampleAggregates;
        }
        if ($collationExpressions !== []) {
            $groupBy['collationExpressions'] = $collationExpressions;
        }
        array_push($expressions, ...self::aggregateArgumentExpressions($select, $aggregateExpressions));
        if ($expressions !== []) {
            $deduped = [];
            foreach ($expressions as $expression) {
                $deduped[$expression['column']] = $expression;
            }
            $groupBy['expressions'] = array_values($deduped);
        }

        return $groupBy;
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return ?array<string,mixed>
     */
    private static function groupByAliasExpression(string $term, array $select): ?array
    {
        if (str_contains($term, '.')) {
            return null;
        }

        foreach ($select as $selectTerm) {
            if (($selectTerm['alias'] ?? null) !== $term) {
                continue;
            }

            $expression = $selectTerm['sourceExpression'] ?? $selectTerm;
            if (!is_array($expression)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL GROUP BY alias expression is malformed');
            }

            unset($expression['alias'], $expression['hiddenOrderColumn']);

            return $expression;
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return array<string,mixed>
     */
    private static function implicitAggregateGroup(array $select, array $aggregateExpressions = [], bool $specificAggregates = false): array
    {
        $group = [
            'columns' => [],
            'implicitAggregate' => true,
            'valueColumn' => $specificAggregates ? null : self::aggregateValueColumn($select, $aggregateExpressions),
            'jsonAggregates' => self::jsonAggregateSpecs($select, $aggregateExpressions),
            'filteredAggregates' => self::aggregateSpecs($select, $aggregateExpressions, $specificAggregates),
        ];
        $sampleAggregates = self::minMaxAggregateExpressions($select, $aggregateExpressions);
        if ($sampleAggregates !== []) {
            $group['sampleAggregates'] = $sampleAggregates;
        }
        $expressions = self::aggregateArgumentExpressions($select, $aggregateExpressions);
        if ($expressions !== []) {
            $group['expressions'] = $expressions;
        }

        return $group;
    }

    /**
     * @param list<array<string,mixed>> $select
     */
    private static function aggregateValueColumn(array $select, array $aggregateExpressions = []): ?string
    {
        $valueColumn = null;
        $hasAggregate = false;
        foreach ($select as $term) {
            [$hasAggregate, $valueColumn] = self::mergeAggregateValueColumn($term, $hasAggregate, $valueColumn);
        }
        foreach ($aggregateExpressions as $expression) {
            if (is_array($expression)) {
                [$hasAggregate, $valueColumn] = self::mergeAggregateValueColumn($expression, $hasAggregate, $valueColumn);
            }
        }
        return $hasAggregate ? $valueColumn : null;
    }

    /**
     * @param list<array<string,mixed>> $select
     */
    private static function needsSpecificAggregateSummaries(array $select, array $aggregateExpressions = []): bool
    {
        $valueColumns = [];
        foreach (array_merge($select, $aggregateExpressions) as $term) {
            if (is_array($term)) {
                self::collectAggregateValueColumns($term, $valueColumns);
            }
        }

        return count($valueColumns) > 1;
    }

    /**
     * @param array<string,mixed> $expression
     * @param array<string,true> $valueColumns
     */
    private static function collectAggregateValueColumns(array $expression, array &$valueColumns): void
    {
        $aggregate = self::aggregateSummaryColumn($expression, null);
        if ($aggregate !== null) {
            $valueColumn = $aggregate['valueColumn'] ?? null;
            if (($aggregate['filtered'] ?? false) !== true && is_string($valueColumn) && $valueColumn !== '') {
                $valueColumns[$valueColumn] = true;
            }

            return;
        }

        foreach (['left', 'right', 'operand', 'predicate', 'expression'] as $side) {
            if (isset($expression[$side]) && is_array($expression[$side])) {
                self::collectAggregateValueColumns($expression[$side], $valueColumns);
            }
        }
        if (isset($expression['term']) && is_array($expression['term'])) {
            self::collectAggregateValueColumns($expression['term'], $valueColumns);
        }
        if (isset($expression['terms']) && is_array($expression['terms']) && array_is_list($expression['terms'])) {
            foreach ($expression['terms'] as $term) {
                if (is_array($term)) {
                    self::collectAggregateValueColumns($term, $valueColumns);
                }
            }
        }
        foreach (['arguments', 'values'] as $side) {
            if (!isset($expression[$side]) || !is_array($expression[$side]) || !array_is_list($expression[$side])) {
                continue;
            }
            foreach ($expression[$side] as $child) {
                if (is_array($child)) {
                    self::collectAggregateValueColumns($child, $valueColumns);
                }
            }
        }
    }

    /**
     * @return array{0:bool,1:?string}
     */
    private static function mergeAggregateValueColumn(array $expression, bool $hasAggregate, ?string $valueColumn): array
    {
        $aggregate = self::aggregateSummaryColumn($expression, null);
        if ($aggregate !== null) {
            $hasAggregate = true;
            if (($aggregate['filtered'] ?? false) !== true && $aggregate['valueColumn'] !== null) {
                if ($valueColumn !== null && $valueColumn !== $aggregate['valueColumn']) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL GROUP BY supports one aggregate value column');
                }
                $valueColumn = $aggregate['valueColumn'];
            }
        }
        foreach (['left', 'right', 'operand', 'predicate'] as $side) {
            if (isset($expression[$side]) && is_array($expression[$side])) {
                [$hasAggregate, $valueColumn] = self::mergeAggregateValueColumn($expression[$side], $hasAggregate, $valueColumn);
            }
        }
        if (isset($expression['term']) && is_array($expression['term'])) {
            [$hasAggregate, $valueColumn] = self::mergeAggregateValueColumn($expression['term'], $hasAggregate, $valueColumn);
        }
        if (isset($expression['terms']) && is_array($expression['terms']) && array_is_list($expression['terms'])) {
            foreach ($expression['terms'] as $term) {
                if (is_array($term)) {
                    [$hasAggregate, $valueColumn] = self::mergeAggregateValueColumn($term, $hasAggregate, $valueColumn);
                }
            }
        }
        foreach (['arguments', 'values'] as $side) {
            if (!isset($expression[$side]) || !is_array($expression[$side]) || !array_is_list($expression[$side])) {
                continue;
            }
            foreach ($expression[$side] as $child) {
                if (is_array($child)) {
                    [$hasAggregate, $valueColumn] = self::mergeAggregateValueColumn($child, $hasAggregate, $valueColumn);
                }
            }
        }

        return [$hasAggregate, $valueColumn];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function predicateExpressions(array $predicate): array
    {
        $expressions = [];
        if (isset($predicate['terms']) && is_array($predicate['terms'])) {
            foreach ($predicate['terms'] as $term) {
                if (is_array($term)) {
                    array_push($expressions, ...self::predicateExpressions($term));
                }
            }
        }
        if (isset($predicate['term']) && is_array($predicate['term'])) {
            array_push($expressions, ...self::predicateExpressions($predicate['term']));
        }
        foreach (['left', 'right', 'operand'] as $side) {
            if (isset($predicate[$side]) && is_array($predicate[$side])) {
                $expressions[] = $predicate[$side];
            }
        }

        return $expressions;
    }

    /**
     * @param list<array<string,mixed>> $select
     */
    private static function selectHasAggregate(array $select): bool
    {
        foreach ($select as $term) {
            if (self::expressionHasAggregate($term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $expression
     */
    private static function expressionHasAggregate(array $expression): bool
    {
        if (self::aggregateSummaryColumn($expression, null) !== null) {
            return true;
        }
        foreach (['left', 'right', 'operand', 'predicate', 'expression'] as $side) {
            if (isset($expression[$side]) && is_array($expression[$side]) && self::expressionHasAggregate($expression[$side])) {
                return true;
            }
        }
        if (isset($expression['term']) && is_array($expression['term']) && self::expressionHasAggregate($expression['term'])) {
            return true;
        }
        foreach (['terms', 'arguments', 'values'] as $side) {
            if (!isset($expression[$side]) || !is_array($expression[$side]) || !array_is_list($expression[$side])) {
                continue;
            }
            foreach ($expression[$side] as $child) {
                if (is_array($child) && self::expressionHasAggregate($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return list<array<string,mixed>>
     */
    private static function rewriteAggregateSelect(array $select, ?string $valueColumn, bool $specificAggregates = false): array
    {
        $rewritten = [];
        foreach ($select as $term) {
            $alias = $term['alias'] ?? null;
            $sourceExpression = $term;
            unset($sourceExpression['alias'], $sourceExpression['hiddenOrderColumn']);
            $term = self::rewriteAggregateExpression($term, $valueColumn, $specificAggregates);
            if ($alias !== null && !isset($term['alias'])) {
                $term['alias'] = $alias;
            }
            $term['sourceExpression'] = $sourceExpression;
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
        if (isset($term['filter']) && is_array($term['filter']) && self::isFilteredAggregateFunction($name)) {
            self::assertFilteredAggregateArguments($name, $arguments, $term);

            return [
                'summaryColumn' => self::filteredAggregateSummaryColumn($term),
                'valueColumn' => null,
                'filtered' => true,
            ];
        }

        if ($name === 'count' && $arguments === []) {
            if (($term['distinct'] ?? false) === true) {
                throw new \InvalidArgumentException('SQLite SELECT SQL count(DISTINCT) is not supported');
            }

            return ['summaryColumn' => 'countAll', 'valueColumn' => null];
        }
        if ($name === 'count' && count($arguments) === 1 && (($arguments[0]['type'] ?? null) === 'wildcard')) {
            if (($term['distinct'] ?? false) === true) {
                throw new \InvalidArgumentException('SQLite SELECT SQL count(DISTINCT *) is not supported');
            }

            return ['summaryColumn' => 'countAll', 'valueColumn' => null];
        }
        if ($name === 'count' && count($arguments) === 1 && (($arguments[0]['type'] ?? null) === 'literal')) {
            if (($term['distinct'] ?? false) === true) {
                throw new \InvalidArgumentException('SQLite SELECT SQL count(DISTINCT literal) is not supported');
            }

            return ($arguments[0]['value'] ?? null) === null
                ? ['summaryColumn' => 'countValue', 'valueColumn' => null]
                : ['summaryColumn' => 'countAll', 'valueColumn' => null];
        }
        if (($name === 'min' || $name === 'max') && count($arguments) !== 1) {
            return null;
        }

        if ($name === 'json_group_array' || $name === 'jsonb_group_array') {
            if (count($arguments) !== 1 || (($arguments[0]['type'] ?? null) !== 'column') || !isset($arguments[0]['name']) || !is_string($arguments[0]['name'])) {
                throw new \InvalidArgumentException("SQLite SELECT SQL aggregate {$name} needs one column argument");
            }
            foreach (self::jsonAggregateOrderTerms($term, $name) as $orderTerm) {
                if (!in_array($orderTerm['direction'], ['ASC', 'DESC'], true)) {
                    throw new \InvalidArgumentException("SQLite SELECT SQL aggregate {$name} ORDER BY direction must be ASC or DESC");
                }
            }

            return [
                'summaryColumn' => self::jsonAggregateSummaryColumn($term),
                'valueColumn' => null,
            ];
        }

        if ($name === 'json_group_object' || $name === 'jsonb_group_object') {
            if (
                count($arguments) !== 2
                || (($arguments[0]['type'] ?? null) !== 'column')
                || (($arguments[1]['type'] ?? null) !== 'column')
                || !isset($arguments[0]['name'], $arguments[1]['name'])
                || !is_string($arguments[0]['name'])
                || !is_string($arguments[1]['name'])
            ) {
                throw new \InvalidArgumentException("SQLite SELECT SQL aggregate {$name} needs key and value column arguments");
            }
            foreach (self::jsonAggregateOrderTerms($term, $name) as $orderTerm) {
                if (!in_array($orderTerm['direction'], ['ASC', 'DESC'], true)) {
                    throw new \InvalidArgumentException("SQLite SELECT SQL aggregate {$name} ORDER BY direction must be ASC or DESC");
                }
            }

            return [
                'summaryColumn' => self::jsonAggregateSummaryColumn($term),
                'valueColumn' => null,
            ];
        }

        $distinct = ($term['distinct'] ?? false) === true;
        if ($distinct && $name !== 'count') {
            throw new \InvalidArgumentException("SQLite SELECT SQL aggregate {$name}(DISTINCT ...) is not supported");
        }

        $summaryColumn = match ($name) {
            'count' => $distinct ? 'countDistinct' : 'countValue',
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
            if (count($arguments) !== 1 || !is_array($arguments[0])) {
                throw new \InvalidArgumentException("SQLite SELECT SQL aggregate {$name} needs one column or scalar expression argument");
            }
            $valueColumn = self::aggregateExpressionColumn($arguments[0]);
            if ($requiredValueColumn !== null && $valueColumn !== $requiredValueColumn) {
                throw new \InvalidArgumentException('SQLite SELECT SQL GROUP BY aggregate column does not match value column');
            }

            return ['summaryColumn' => $summaryColumn, 'valueColumn' => $valueColumn];
        }
        if ($requiredValueColumn !== null && $arguments[0]['name'] !== $requiredValueColumn) {
            throw new \InvalidArgumentException('SQLite SELECT SQL GROUP BY aggregate column does not match value column');
        }

        return ['summaryColumn' => $summaryColumn, 'valueColumn' => $arguments[0]['name']];
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return list<array{column:string,expression:array<string,mixed>}>
     */
    private static function aggregateArgumentExpressions(array $select, array $aggregateExpressions = []): array
    {
        $expressions = [];
        foreach (array_merge($select, $aggregateExpressions) as $term) {
            if (is_array($term)) {
                self::collectAggregateArgumentExpressions($term, $expressions);
            }
        }

        return array_values($expressions);
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return list<array<string,mixed>>
     */
    private static function minMaxAggregateExpressions(array $select, array $aggregateExpressions = []): array
    {
        $expressions = [];
        foreach (array_merge($select, $aggregateExpressions) as $term) {
            if (is_array($term)) {
                self::collectMinMaxAggregateExpressions($term, $expressions);
            }
        }

        return array_values($expressions);
    }

    /**
     * @param array<string,mixed> $expression
     * @param array<string,array<string,mixed>> $expressions
     */
    private static function collectMinMaxAggregateExpressions(array $expression, array &$expressions): void
    {
        if (($expression['type'] ?? null) === 'function' && isset($expression['name']) && is_string($expression['name'])) {
            $name = strtolower($expression['name']);
            $arguments = $expression['arguments'] ?? [];
            if (
                ($name === 'min' || $name === 'max')
                && is_array($arguments)
                && array_is_list($arguments)
                && count($arguments) === 1
                && is_array($arguments[0])
            ) {
                $expressions[sha1(json_encode([$name, $arguments[0]], JSON_THROW_ON_ERROR))] = $expression;
            }
        }

        foreach (['left', 'right', 'operand', 'predicate', 'expression', 'base'] as $side) {
            if (isset($expression[$side]) && is_array($expression[$side])) {
                self::collectMinMaxAggregateExpressions($expression[$side], $expressions);
            }
        }
        if (isset($expression['term']) && is_array($expression['term'])) {
            self::collectMinMaxAggregateExpressions($expression['term'], $expressions);
        }
        if (isset($expression['terms']) && is_array($expression['terms']) && array_is_list($expression['terms'])) {
            foreach ($expression['terms'] as $term) {
                if (is_array($term)) {
                    self::collectMinMaxAggregateExpressions($term, $expressions);
                }
            }
        }
        foreach (['arguments', 'values'] as $side) {
            if (!isset($expression[$side]) || !is_array($expression[$side]) || !array_is_list($expression[$side])) {
                continue;
            }
            foreach ($expression[$side] as $child) {
                if (is_array($child)) {
                    self::collectMinMaxAggregateExpressions($child, $expressions);
                }
            }
        }
        if (isset($expression['branches']) && is_array($expression['branches']) && array_is_list($expression['branches'])) {
            foreach ($expression['branches'] as $branch) {
                if (!is_array($branch)) {
                    continue;
                }
                foreach (['when', 'then'] as $side) {
                    if (isset($branch[$side]) && is_array($branch[$side])) {
                        self::collectMinMaxAggregateExpressions($branch[$side], $expressions);
                    }
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $expression
     * @param array<string,array{column:string,expression:array<string,mixed>}> $expressions
     */
    private static function collectAggregateArgumentExpressions(array $expression, array &$expressions): void
    {
        if (($expression['type'] ?? null) === 'function' && isset($expression['name']) && is_string($expression['name'])) {
            $name = strtolower($expression['name']);
            $arguments = $expression['arguments'] ?? [];
            if (
                in_array($name, ['count', 'sum', 'total', 'avg', 'min', 'max', 'group_concat'], true)
                && is_array($arguments)
                && array_is_list($arguments)
                && count($arguments) === 1
                && is_array($arguments[0])
                && (($arguments[0]['type'] ?? null) !== 'wildcard')
                && (($arguments[0]['type'] ?? null) !== 'column')
                && (($arguments[0]['type'] ?? null) !== 'literal' || $name !== 'count')
            ) {
                $column = self::aggregateExpressionColumn($arguments[0]);
                $expressions[$column] = [
                    'column' => $column,
                    'expression' => $arguments[0],
                ];
            }
        }

        foreach (['left', 'right', 'operand'] as $side) {
            if (isset($expression[$side]) && is_array($expression[$side])) {
                self::collectAggregateArgumentExpressions($expression[$side], $expressions);
            }
        }
        foreach (['arguments', 'values'] as $side) {
            if (!isset($expression[$side]) || !is_array($expression[$side]) || !array_is_list($expression[$side])) {
                continue;
            }
            foreach ($expression[$side] as $child) {
                if (is_array($child)) {
                    self::collectAggregateArgumentExpressions($child, $expressions);
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $expression
     */
    private static function aggregateExpressionColumn(array $expression): string
    {
        return '__aggregateExpression' . substr(sha1(json_encode($expression, JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return list<array<string,mixed>>
     */
    private static function filteredAggregateSpecs(array $select, array $aggregateExpressions = []): array
    {
        $specs = [];
        foreach (array_merge($select, $aggregateExpressions) as $term) {
            if (is_array($term)) {
                self::collectFilteredAggregateSpecs($term, $specs);
            }
        }

        return array_values($specs);
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return list<array<string,mixed>>
     */
    private static function aggregateSpecs(array $select, array $aggregateExpressions = [], bool $specificAggregates = false): array
    {
        $specs = [];
        foreach (self::filteredAggregateSpecs($select, $aggregateExpressions) as $spec) {
            if (isset($spec['summaryColumn']) && is_string($spec['summaryColumn'])) {
                $specs[$spec['summaryColumn']] = $spec;
            }
        }
        if ($specificAggregates) {
            foreach (self::specificAggregateSpecs($select, $aggregateExpressions) as $spec) {
                if (isset($spec['summaryColumn']) && is_string($spec['summaryColumn'])) {
                    $specs[$spec['summaryColumn']] = $spec;
                }
            }
        }

        return array_values($specs);
    }

    /**
     * @param array<string,mixed> $expression
     * @param array<string,array<string,mixed>> $specs
     */
    private static function collectFilteredAggregateSpecs(array $expression, array &$specs): void
    {
        $spec = self::filteredAggregateSpec($expression);
        if ($spec !== null) {
            $specs[$spec['summaryColumn']] = $spec;

            return;
        }

        foreach (['left', 'right', 'operand', 'predicate'] as $side) {
            if (isset($expression[$side]) && is_array($expression[$side])) {
                self::collectFilteredAggregateSpecs($expression[$side], $specs);
            }
        }
        if (isset($expression['term']) && is_array($expression['term'])) {
            self::collectFilteredAggregateSpecs($expression['term'], $specs);
        }
        if (isset($expression['terms']) && is_array($expression['terms']) && array_is_list($expression['terms'])) {
            foreach ($expression['terms'] as $term) {
                if (is_array($term)) {
                    self::collectFilteredAggregateSpecs($term, $specs);
                }
            }
        }
        foreach (['arguments', 'values'] as $side) {
            if (!isset($expression[$side]) || !is_array($expression[$side]) || !array_is_list($expression[$side])) {
                continue;
            }
            foreach ($expression[$side] as $child) {
                if (is_array($child)) {
                    self::collectFilteredAggregateSpecs($child, $specs);
                }
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return list<array<string,mixed>>
     */
    private static function specificAggregateSpecs(array $select, array $aggregateExpressions = []): array
    {
        $specs = [];
        foreach (array_merge($select, $aggregateExpressions) as $term) {
            if (is_array($term)) {
                self::collectSpecificAggregateSpecs($term, $specs);
            }
        }

        return array_values($specs);
    }

    /**
     * @param array<string,mixed> $expression
     * @param array<string,array<string,mixed>> $specs
     */
    private static function collectSpecificAggregateSpecs(array $expression, array &$specs): void
    {
        $spec = self::specificAggregateSpec($expression);
        if ($spec !== null) {
            $specs[$spec['summaryColumn']] = $spec;

            return;
        }

        foreach (['left', 'right', 'operand', 'predicate'] as $side) {
            if (isset($expression[$side]) && is_array($expression[$side])) {
                self::collectSpecificAggregateSpecs($expression[$side], $specs);
            }
        }
        if (isset($expression['term']) && is_array($expression['term'])) {
            self::collectSpecificAggregateSpecs($expression['term'], $specs);
        }
        if (isset($expression['terms']) && is_array($expression['terms']) && array_is_list($expression['terms'])) {
            foreach ($expression['terms'] as $term) {
                if (is_array($term)) {
                    self::collectSpecificAggregateSpecs($term, $specs);
                }
            }
        }
        foreach (['arguments', 'values'] as $side) {
            if (!isset($expression[$side]) || !is_array($expression[$side]) || !array_is_list($expression[$side])) {
                continue;
            }
            foreach ($expression[$side] as $child) {
                if (is_array($child)) {
                    self::collectSpecificAggregateSpecs($child, $specs);
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $term
     * @return array<string,mixed>|null
     */
    private static function specificAggregateSpec(array $term): ?array
    {
        if (($term['type'] ?? null) !== 'function' || !isset($term['name']) || !is_string($term['name']) || isset($term['filter'])) {
            return null;
        }
        $name = strtolower($term['name']);
        if (!self::isFilteredAggregateFunction($name)) {
            return null;
        }
        $arguments = $term['arguments'] ?? [];
        if (!is_array($arguments) || !array_is_list($arguments)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL aggregate arguments must be a list');
        }
        if (($name === 'min' || $name === 'max') && count($arguments) !== 1) {
            return null;
        }
        self::assertFilteredAggregateArguments($name, $arguments, $term);
        $argument = $arguments[0] ?? ['type' => 'wildcard'];
        if ($name === 'count' && (($argument['type'] ?? null) === 'wildcard')) {
            return null;
        }
        if ($name === 'count' && (($argument['type'] ?? null) === 'literal')) {
            return null;
        }

        return [
            'summaryColumn' => self::specificAggregateSummaryColumn($term),
            'function' => $name,
            'argument' => $argument,
            ...($term['distinct'] ?? false) === true ? ['distinct' => true] : [],
        ];
    }

    /**
     * @param array<string,mixed> $term
     * @return array<string,mixed>|null
     */
    private static function filteredAggregateSpec(array $term): ?array
    {
        if (($term['type'] ?? null) !== 'function' || !isset($term['name']) || !is_string($term['name']) || !isset($term['filter']) || !is_array($term['filter'])) {
            return null;
        }
        $name = strtolower($term['name']);
        if (!self::isFilteredAggregateFunction($name)) {
            return null;
        }
        $arguments = $term['arguments'] ?? [];
        if (!is_array($arguments) || !array_is_list($arguments)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL filtered aggregate arguments must be a list');
        }
        self::assertFilteredAggregateArguments($name, $arguments, $term);

        return [
            'summaryColumn' => self::filteredAggregateSummaryColumn($term),
            'function' => $name,
            'argument' => $arguments[0] ?? ['type' => 'wildcard'],
            'filter' => $term['filter'],
            ...($term['distinct'] ?? false) === true ? ['distinct' => true] : [],
        ];
    }

    private static function isFilteredAggregateFunction(string $name): bool
    {
        return in_array($name, ['count', 'sum', 'total', 'avg', 'min', 'max', 'group_concat'], true);
    }

    /**
     * @param list<array<string,mixed>> $arguments
     * @param array<string,mixed> $term
     */
    private static function assertFilteredAggregateArguments(string $name, array $arguments, array $term): void
    {
        if (($term['distinct'] ?? false) === true && $name !== 'count') {
            throw new \InvalidArgumentException("SQLite SELECT SQL aggregate {$name}(DISTINCT ...) is not supported");
        }
        if ($name === 'count' && $arguments === []) {
            if (($term['distinct'] ?? false) === true) {
                throw new \InvalidArgumentException('SQLite SELECT SQL count(DISTINCT) is not supported');
            }

            return;
        }
        if ($arguments === [] || count($arguments) !== 1 || !is_array($arguments[0])) {
            throw new \InvalidArgumentException("SQLite SELECT SQL aggregate {$name} FILTER needs one argument");
        }
        if ($name !== 'count' && (($arguments[0]['type'] ?? null) === 'wildcard')) {
            throw new \InvalidArgumentException("SQLite SELECT SQL aggregate {$name} FILTER needs one column or scalar expression argument");
        }
    }

    /**
     * @param array<string,mixed> $term
     */
    private static function filteredAggregateSummaryColumn(array $term): string
    {
        $name = strtolower((string) $term['name']);

        return $name . 'Filter_' . substr(sha1(json_encode([
            'name' => $name,
            'arguments' => $term['arguments'] ?? [],
            'distinct' => ($term['distinct'] ?? false) === true,
            'filter' => $term['filter'] ?? null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * @param array<string,mixed> $term
     */
    private static function specificAggregateSummaryColumn(array $term): string
    {
        $name = strtolower((string) ($term['name'] ?? 'aggregate'));

        return $name . 'Aggregate_' . substr(sha1(json_encode([
            'name' => $name,
            'arguments' => $term['arguments'] ?? [],
            'distinct' => ($term['distinct'] ?? false) === true,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * @param list<array<string,mixed>> $select
     * @return list<array<string,mixed>>
     */
    private static function jsonAggregateSpecs(array $select, array $aggregateExpressions = []): array
    {
        $specs = [];
        foreach (array_merge($select, $aggregateExpressions) as $term) {
            if (!is_array($term)) {
                continue;
            }
            $aggregate = self::aggregateSummaryColumn($term, null);
            if (
                $aggregate === null
                || (
                    !str_starts_with($aggregate['summaryColumn'], 'jsonGroupArray')
                    && !str_starts_with($aggregate['summaryColumn'], 'jsonbGroupArray')
                    && !str_starts_with($aggregate['summaryColumn'], 'jsonGroupObject')
                    && !str_starts_with($aggregate['summaryColumn'], 'jsonbGroupObject')
                )
            ) {
                foreach (['left', 'right', 'operand'] as $side) {
                    if (isset($term[$side]) && is_array($term[$side])) {
                        array_push($specs, ...self::jsonAggregateSpecs([], [$term[$side]]));
                    }
                }
                continue;
            }
            $arguments = $term['arguments'];
            $spec = [
                'summaryColumn' => $aggregate['summaryColumn'],
                'function' => strtolower($term['name']),
                'column' => $arguments[0]['name'],
            ];
            if (isset($arguments[1]['name']) && is_string($arguments[1]['name'])) {
                $spec['valueColumn'] = $arguments[1]['name'];
            }
            if (isset($term['orderBy'])) {
                if (($term['orderBy']['type'] ?? null) === 'column' && isset($term['orderBy']['name']) && is_string($term['orderBy']['name'])) {
                    $spec['orderBy'] = $term['orderBy']['name'];
                }
                $spec['orderDirection'] = strtoupper((string) ($term['orderDirection'] ?? 'ASC'));
                $spec['orderByTerms'] = array_map(
                    static fn (array $orderTerm): array => [
                        'expression' => $orderTerm['expression'],
                        'direction' => $orderTerm['direction'],
                        ...isset($orderTerm['nulls']) ? ['nulls' => $orderTerm['nulls']] : [],
                    ],
                    self::jsonAggregateOrderTerms($term, strtolower($term['name'])),
                );
            }
            if (($term['distinct'] ?? false) === true) {
                $spec['distinct'] = true;
            }
            if (isset($term['filter'])) {
                $spec['filter'] = $term['filter'];
            }
            $specs[$aggregate['summaryColumn']] = $spec;
        }

        return array_values($specs);
    }

    /**
     * @param array<string,mixed> $term
     * @return list<array{expression:array<string,mixed>,direction:string}>
     */
    private static function jsonAggregateOrderTerms(array $term, string $name): array
    {
        if (isset($term['orderByTerms'])) {
            if (!is_array($term['orderByTerms']) || !array_is_list($term['orderByTerms']) || $term['orderByTerms'] === []) {
                throw new \InvalidArgumentException("SQLite SELECT SQL aggregate {$name} ORDER BY terms are malformed");
            }
            $terms = [];
            foreach ($term['orderByTerms'] as $orderTerm) {
                if (!is_array($orderTerm) || !isset($orderTerm['expression']) || !is_array($orderTerm['expression'])) {
                    throw new \InvalidArgumentException("SQLite SELECT SQL aggregate {$name} ORDER BY term is malformed");
                }
                $terms[] = [
                    'expression' => $orderTerm['expression'],
                    'direction' => strtoupper((string) ($orderTerm['direction'] ?? 'ASC')),
                    ...isset($orderTerm['nulls']) ? ['nulls' => strtoupper((string) $orderTerm['nulls'])] : [],
                ];
            }

            return $terms;
        }

        if (!isset($term['orderBy'])) {
            return [];
        }
        if (!is_array($term['orderBy'])) {
            throw new \InvalidArgumentException("SQLite SELECT SQL aggregate {$name} ORDER BY term is malformed");
        }

        return [[
            'expression' => $term['orderBy'],
            'direction' => strtoupper((string) ($term['orderDirection'] ?? 'ASC')),
            ...isset($term['orderNulls']) ? ['nulls' => strtoupper((string) $term['orderNulls'])] : [],
        ]];
    }

    /**
     * @param array<string,mixed> $term
     */
    private static function jsonAggregateSummaryColumn(array $term): string
    {
        $arguments = $term['arguments'];
        $column = str_replace(['.', '-'], '_', $arguments[0]['name']);
        $functionName = strtolower($term['name']);
        if (($functionName === 'json_group_object' || $functionName === 'jsonb_group_object') && isset($arguments[1]['name']) && is_string($arguments[1]['name'])) {
            $column .= '_' . str_replace(['.', '-'], '_', $arguments[1]['name']);
        }
        $name = match ($functionName) {
            'jsonb_group_array' => 'jsonbGroupArray',
            'json_group_object' => 'jsonGroupObject',
            'jsonb_group_object' => 'jsonbGroupObject',
            default => 'jsonGroupArray',
        };
        if (($term['distinct'] ?? false) === true) {
            $name .= 'Distinct';
        }
        if (isset($term['orderBy'])) {
            $orderParts = [];
            foreach (self::jsonAggregateOrderTerms($term, strtolower((string) $term['name'])) as $orderTerm) {
                $orderParts[] = self::jsonAggregateOrderTermLabel($orderTerm['expression'])
                    . ($orderTerm['direction'] === 'DESC' ? 'Desc' : 'Asc')
                    . (isset($orderTerm['nulls']) ? 'Nulls' . ucfirst(strtolower((string) $orderTerm['nulls'])) : '');
            }
            $name .= 'OrderBy' . implode('_', $orderParts);
        }
        if (isset($term['filter'])) {
            $name .= 'Filter';
        }

        return $name . '_' . $column;
    }

    /**
     * @param array<string,mixed> $expression
     */
    private static function jsonAggregateOrderTermLabel(array $expression): string
    {
        if (($expression['type'] ?? null) === 'column' && isset($expression['name']) && is_string($expression['name'])) {
            return str_replace(['.', '-'], '_', $expression['name']);
        }

        return 'expr' . substr(sha1(json_encode($expression, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: serialize($expression)), 0, 12);
    }

    /**
     * @param array<string,mixed> $predicate
     * @return array<string,mixed>
     */
    private static function rewriteAggregatePredicate(array $predicate, ?string $valueColumn, bool $specificAggregates = false): array
    {
        if (isset($predicate['terms']) && is_array($predicate['terms']) && array_is_list($predicate['terms'])) {
            $predicate['terms'] = array_map(
                static fn (array $term): array => self::rewriteAggregatePredicate($term, $valueColumn, $specificAggregates),
                $predicate['terms'],
            );

            return $predicate;
        }
        if (isset($predicate['term']) && is_array($predicate['term'])) {
            $predicate['term'] = self::rewriteAggregatePredicate($predicate['term'], $valueColumn, $specificAggregates);

            return $predicate;
        }
        foreach (['left', 'right'] as $side) {
            if (isset($predicate[$side]) && is_array($predicate[$side])) {
                $predicate[$side] = self::rewriteAggregateExpression($predicate[$side], $valueColumn, $specificAggregates);
            }
        }

        return $predicate;
    }

    /**
     * @param array<string,mixed> $expression
     * @return array<string,mixed>
     */
    private static function rewriteAggregateExpression(array $expression, ?string $valueColumn, bool $specificAggregates = false): array
    {
        if (($expression['type'] ?? null) === 'wildcard') {
            $expression['aggregateWildcard'] = true;

            return $expression;
        }

        if ($specificAggregates) {
            $specificAggregate = self::specificAggregateSpec($expression);
            if ($specificAggregate !== null) {
                return ['type' => 'column', 'name' => $specificAggregate['summaryColumn']];
            }
        }
        $aggregate = self::aggregateSummaryColumn($expression, $valueColumn);
        if ($aggregate === null) {
            foreach (['left', 'right', 'operand'] as $side) {
                if (isset($expression[$side]) && is_array($expression[$side])) {
                    $expression[$side] = self::rewriteAggregateExpression($expression[$side], $valueColumn, $specificAggregates);
                }
            }
            if (isset($expression['predicate']) && is_array($expression['predicate'])) {
                $expression['predicate'] = self::rewriteAggregatePredicate($expression['predicate'], $valueColumn, $specificAggregates);
            }
            foreach (['arguments', 'values'] as $side) {
                if (!isset($expression[$side]) || !is_array($expression[$side]) || !array_is_list($expression[$side])) {
                    continue;
                }
                foreach ($expression[$side] as $index => $child) {
                    if (is_array($child)) {
                        $expression[$side][$index] = self::rewriteAggregateExpression($child, $valueColumn, $specificAggregates);
                    }
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
    private static function orderBy(string $sql, array &$select, ?string $aggregateValueColumn, bool $rewriteAggregates = false, array $tables = [], bool $specificAggregates = false): array
    {
        $terms = [];
        foreach (self::splitTopLevel($sql, ',') as $index => $term) {
            [$expressionSql, $direction, $collation, $nulls] = self::orderByTermParts($term);
            if ($expressionSql === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY term cannot be empty');
            }

            $inheritedCollation = null;
            if (preg_match('/^[0-9]+$/', $expressionSql) === 1) {
                $ordinal = (int) $expressionSql;
                $order = ['column' => self::orderByOrdinalColumn($select, $ordinal)];
                $inheritedCollation = self::orderByOrdinalCollation($select, $ordinal);
            } elseif (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/', $expressionSql) === 1) {
                if (self::selectProvidesColumn($select, $expressionSql)) {
                    $order = ['column' => $expressionSql];
                    $inheritedCollation = self::orderByResultAliasCollation($select, $expressionSql);
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
                if ($rewriteAggregates) {
                    $expression = self::rewriteSelectAliasColumns($expression, $select);
                    $expression = self::rewriteAggregateExpression($expression, $aggregateValueColumn, $specificAggregates);
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
            } elseif ($inheritedCollation !== null) {
                $order['collation'] = $inheritedCollation;
            }
            if ($nulls !== null) {
                $order['nulls'] = $nulls;
            }
            $terms[] = $order;
        }

        return $terms;
    }

    /**
     * @param array<string,mixed> $expression
     * @param list<array<string,mixed>> $select
     * @return array<string,mixed>
     */
    private static function rewriteSelectAliasColumns(array $expression, array $select): array
    {
        if (($expression['type'] ?? null) === 'column' && isset($expression['name']) && is_string($expression['name']) && !str_contains($expression['name'], '.')) {
            foreach ($select as $selectTerm) {
                if (($selectTerm['alias'] ?? null) !== $expression['name']) {
                    continue;
                }
                $sourceExpression = $selectTerm['sourceExpression'] ?? $selectTerm;
                if (!is_array($sourceExpression)) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY alias expression is malformed');
                }
                unset($sourceExpression['alias'], $sourceExpression['hiddenOrderColumn'], $sourceExpression['sourceExpression']);

                return $sourceExpression;
            }

            return $expression;
        }

        foreach (['left', 'right', 'operand'] as $side) {
            if (isset($expression[$side]) && is_array($expression[$side])) {
                $expression[$side] = self::rewriteSelectAliasColumns($expression[$side], $select);
            }
        }
        foreach (['arguments', 'values'] as $side) {
            if (!isset($expression[$side]) || !is_array($expression[$side]) || !array_is_list($expression[$side])) {
                continue;
            }
            foreach ($expression[$side] as $index => $child) {
                if (is_array($child)) {
                    $expression[$side][$index] = self::rewriteSelectAliasColumns($child, $select);
                }
            }
        }
        if (isset($expression['predicate']) && is_array($expression['predicate'])) {
            $expression['predicate'] = self::rewriteSelectAliasPredicate($expression['predicate'], $select);
        }

        return $expression;
    }

    /**
     * @param array<string,mixed> $predicate
     * @param list<array<string,mixed>> $select
     * @return array<string,mixed>
     */
    private static function rewriteSelectAliasPredicate(array $predicate, array $select): array
    {
        foreach (['left', 'right', 'operand'] as $side) {
            if (isset($predicate[$side]) && is_array($predicate[$side])) {
                $predicate[$side] = self::rewriteSelectAliasColumns($predicate[$side], $select);
            }
        }
        if (isset($predicate['term']) && is_array($predicate['term'])) {
            $predicate['term'] = self::rewriteSelectAliasPredicate($predicate['term'], $select);
        }
        if (isset($predicate['terms']) && is_array($predicate['terms']) && array_is_list($predicate['terms'])) {
            foreach ($predicate['terms'] as $index => $term) {
                if (is_array($term)) {
                    $predicate['terms'][$index] = self::rewriteSelectAliasPredicate($term, $select);
                }
            }
        }
        if (isset($predicate['values']) && is_array($predicate['values']) && array_is_list($predicate['values'])) {
            foreach ($predicate['values'] as $index => $value) {
                if (is_array($value)) {
                    $predicate['values'][$index] = self::rewriteSelectAliasColumns($value, $select);
                }
            }
        }

        return $predicate;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array<string,mixed>>
     */
    private static function orderByExpressions(string $sql, array $tables = []): array
    {
        $expressions = [];
        foreach (self::splitTopLevel($sql, ',') as $term) {
            [$expressionSql] = self::orderByTermParts($term);
            if ($expressionSql === '' || preg_match('/^[0-9]+$/', $expressionSql) === 1) {
                continue;
            }
            $expressions[] = self::valueExpression($expressionSql, $tables);
        }

        return $expressions;
    }

    /**
     * @param list<array<string,mixed>> $select
     */
    private static function orderByOrdinalColumn(array $select, int $ordinal): string
    {
        if ($ordinal < 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY ordinal is out of range');
        }

        $remaining = $ordinal;
        foreach ($select as $term) {
            if (($term['type'] ?? null) === 'wildcard') {
                $columns = $term['columns'] ?? null;
                if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY wildcard ordinal needs source columns');
                }
                if ($remaining <= count($columns)) {
                    $column = $columns[$remaining - 1];
                    if (!is_string($column) || $column === '') {
                        throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY wildcard ordinal target is malformed');
                    }

                    return $column;
                }
                $remaining -= count($columns);
                continue;
            }

            if ($remaining !== 1) {
                $remaining--;
                continue;
            }

            if (isset($term['alias']) && is_string($term['alias']) && $term['alias'] !== '') {
                return $term['alias'];
            }
            if (($term['type'] ?? null) === 'column' && isset($term['name']) && is_string($term['name']) && $term['name'] !== '') {
                return $term['name'];
            }

            return 'expr' . $ordinal;
        }

        throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY ordinal is out of range');
    }

    /**
     * @param list<array<string,mixed>> $select
     */
    private static function orderByOrdinalCollation(array $select, int $ordinal): ?string
    {
        if ($ordinal < 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY ordinal is out of range');
        }

        $remaining = $ordinal;
        foreach ($select as $term) {
            if (($term['type'] ?? null) === 'wildcard') {
                $columns = $term['columns'] ?? null;
                if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
                    throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY wildcard ordinal needs source columns');
                }
                if ($remaining <= count($columns)) {
                    return null;
                }
                $remaining -= count($columns);
                continue;
            }

            if ($remaining !== 1) {
                $remaining--;
                continue;
            }

            return self::selectTermCollation($term);
        }

        throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY ordinal is out of range');
    }

    /**
     * @param list<array<string,mixed>> $select
     */
    private static function orderByResultAliasCollation(array $select, string $alias): ?string
    {
        foreach ($select as $term) {
            $termAlias = $term['alias'] ?? null;
            if (!is_string($termAlias) || strcasecmp($termAlias, $alias) !== 0) {
                continue;
            }

            return self::selectTermCollation($term);
        }

        return null;
    }

    /**
     * @param array<string,mixed> $term
     */
    private static function selectTermCollation(array $term): ?string
    {
        if (($term['type'] ?? null) === 'collate' && isset($term['collation']) && is_string($term['collation']) && $term['collation'] !== '') {
            return strtoupper($term['collation']);
        }

        $sourceExpression = $term['sourceExpression'] ?? null;
        if (is_array($sourceExpression)) {
            return self::selectTermCollation($sourceExpression);
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $select
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function annotateWildcardColumns(array $select, array $rows): array
    {
        if ($rows === []) {
            return $select;
        }

        $sourceColumns = array_keys($rows[0]);
        foreach ($select as $index => $term) {
            if (($term['type'] ?? null) !== 'wildcard') {
                continue;
            }

            $prefix = isset($term['prefix']) && is_string($term['prefix']) && $term['prefix'] !== ''
                ? $term['prefix'] . '.'
                : null;
            $schemaQualifiedPrefix = null;
            if ($prefix !== null && substr_count($prefix, '.') >= 2) {
                $schemaQualifiedPrefix = substr($prefix, strpos($prefix, '.') + 1);
            }
            $columns = [];
            foreach ($sourceColumns as $column) {
                if (!is_string($column) || $column === '') {
                    continue;
                }
                if ($prefix !== null) {
                    if (str_starts_with($column, $prefix)) {
                        $columns[] = substr($column, strlen($prefix));
                        continue;
                    }
                    if ($schemaQualifiedPrefix === null || !str_starts_with($column, $schemaQualifiedPrefix)) {
                        continue;
                    }
                    $columns[] = substr($column, strlen($schemaQualifiedPrefix));
                    continue;
                }
                $columns[] = $column;
            }
            if ($columns !== []) {
                $select[$index]['columns'] = $columns;
            }
        }

        return $select;
    }

    /**
     * @param array{from:list<array<string,mixed>>,joins?:list<array<string,mixed>>} $source
     * @return list<array<string,mixed>>
     */
    private static function wildcardAnnotationRows(array $source): array
    {
        $columns = self::collectColumns($source['from']);
        $joins = $source['joins'] ?? [];
        if (!is_array($joins) || !array_is_list($joins)) {
            return $source['from'];
        }

        foreach ($joins as $join) {
            if (!is_array($join)) {
                continue;
            }
            $rightColumns = $join['rightColumns'] ?? null;
            if (is_array($rightColumns) && array_is_list($rightColumns)) {
                foreach ($rightColumns as $column) {
                    if (is_string($column) && !in_array($column, $columns, true)) {
                        $columns[] = $column;
                    }
                }
                continue;
            }
            $rows = $join['rows'] ?? null;
            if (is_array($rows) && array_is_list($rows)) {
                foreach (self::collectColumns($rows) as $column) {
                    if (!in_array($column, $columns, true)) {
                        $columns[] = $column;
                    }
                }
            }
        }

        return $columns === [] ? $source['from'] : [array_fill_keys($columns, null)];
    }

    /**
     * @param list<array<string,mixed>> $select
     */
    private static function selectProvidesColumn(array $select, string $column): bool
    {
        foreach ($select as $term) {
            if (($term['type'] ?? null) === 'wildcard') {
                $columns = $term['columns'] ?? null;
                if (!is_array($columns) || !array_is_list($columns)) {
                    return true;
                }
                foreach ($columns as $wildcardColumn) {
                    if (is_string($wildcardColumn) && strcasecmp($wildcardColumn, $column) === 0) {
                        return true;
                    }
                }
                continue;
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

        if (is_bool($value) || is_int($value)) {
            return (int) $value;
        }

        if (is_float($value)) {
            if (is_finite($value) && floor($value) === $value) {
                return (int) $value;
            }

            throw new \InvalidArgumentException('SQLite SELECT SQL LIMIT datatype mismatch');
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if (
                $trimmed !== ''
                && preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?$/', $trimmed) === 1
            ) {
                $numeric = (float) $trimmed;
                if (is_finite($numeric) && floor($numeric) === $numeric) {
                    return (int) $numeric;
                }
            }
        }

        throw new \InvalidArgumentException('SQLite SELECT SQL LIMIT datatype mismatch');
    }

    /**
     * @return array<string,int>
     */
    private static function tailClauseOffsets(string $sql): array
    {
        $offsets = [];
        foreach (['WHERE', 'GROUP BY', 'HAVING', 'WINDOW', 'ORDER BY', 'LIMIT'] as $keyword) {
            $offset = self::keywordOffset($sql, $keyword);
            if ($offset !== null) {
                $offsets[$keyword] = $offset;
            }
        }
        asort($offsets);

        return $offsets;
    }

    /**
     * @return array<string,string>
     */
    private static function namedWindowDefinitions(string $sql): array
    {
        $windows = [];
        foreach (self::splitTopLevel($sql, ',') as $definition) {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+AS\s*(\(.*\))$/is', trim($definition), $match) !== 1) {
                throw new \InvalidArgumentException('SQLite SELECT SQL WINDOW clause needs name AS (...) definitions');
            }
            $name = strtolower($match[1]);
            if (isset($windows[$name])) {
                throw new \InvalidArgumentException("SQLite SELECT SQL WINDOW clause repeats window name {$match[1]}");
            }
            $body = self::unwrapParenthesizedExpression(trim($match[2]));
            if ($body === trim($match[2])) {
                throw new \InvalidArgumentException('SQLite SELECT SQL WINDOW definition must be parenthesized');
            }
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\b/i', $body, $baseMatch) === 1
                && !in_array(strtoupper($baseMatch[1]), ['PARTITION', 'ORDER', 'ROWS', 'RANGE', 'GROUPS'], true)) {
                throw new \InvalidArgumentException('SQLite SELECT SQL WINDOW base-window chaining is not supported');
            }
            $windows[$name] = $body;
        }
        if ($windows === []) {
            throw new \InvalidArgumentException('SQLite SELECT SQL WINDOW clause needs at least one definition');
        }

        return $windows;
    }

    /**
     * @param array<string,string> $windows
     */
    private static function expandNamedWindowReferences(string $sql, array $windows): string
    {
        $result = '';
        $length = strlen($sql);
        $quote = false;
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
            if (!self::keywordAt($sql, $i, 'OVER')) {
                $result .= $char;
                continue;
            }

            $result .= substr($sql, $i, 4);
            $i += 4;
            $spaces = '';
            while ($i < $length && ctype_space($sql[$i])) {
                $spaces .= $sql[$i];
                $i++;
            }
            if ($i < $length && ($sql[$i] ?? '') === '(') {
                [$body, $endOffset] = self::consumeParenthesized($sql, $i);
                $trimmedBody = trim($body);
                if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:\s+(.*))?$/is', $trimmedBody, $match) === 1
                    && !in_array(strtoupper($match[1]), ['PARTITION', 'ORDER', 'ROWS', 'RANGE', 'GROUPS'], true)) {
                    $key = strtolower($match[1]);
                    if (!isset($windows[$key])) {
                        throw new \InvalidArgumentException("SQLite SELECT SQL named window {$match[1]} is not defined");
                    }
                    $suffix = trim((string) ($match[2] ?? ''));
                    $result .= $spaces . '(' . trim($windows[$key] . ($suffix === '' ? '' : ' ' . $suffix)) . ')';
                    $i = $endOffset - 1;
                    continue;
                }

                $result .= $spaces . '(' . $body . ')';
                $i = $endOffset - 1;
                continue;
            }

            if ($i >= $length || !preg_match('/[A-Za-z_]/', $sql[$i])) {
                $result .= $spaces;
                $i--;
                continue;
            }

            $start = $i;
            $i++;
            while ($i < $length && preg_match('/[A-Za-z0-9_]/', $sql[$i]) === 1) {
                $i++;
            }
            $name = substr($sql, $start, $i - $start);
            $key = strtolower($name);
            if (!isset($windows[$key])) {
                throw new \InvalidArgumentException("SQLite SELECT SQL named window {$name} is not defined");
            }
            $result .= $spaces . '(' . $windows[$key] . ')';
            $i--;
        }
        if ($quote) {
            throw new \InvalidArgumentException('SQLite SELECT SQL has unterminated string literal');
        }

        return $result;
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

    private static function firstTopLevelKeywordOffset(string $sql, string $keyword): ?int
    {
        $length = strlen($sql);
        $keywordLength = strlen($keyword);
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
            if ($depth === 0 && strncasecmp(substr($sql, $i), $keyword, $keywordLength) === 0 && self::keywordBounded($sql, $i, $keywordLength)) {
                return $i;
            }
        }

        return null;
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

    private static function trailingKeywordOffset(string $sql, string $keyword): ?int
    {
        $offset = self::keywordOffset($sql, $keyword);
        if ($offset === null) {
            return null;
        }

        $tail = trim(substr($sql, $offset + strlen($keyword)));

        return $tail === '' ? $offset : null;
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
                if ($operator === '<' && (($sql[$i - 1] ?? null) === '<' || ($sql[$i + 1] ?? null) === '<')) {
                    continue;
                }
                if ($operator === '>' && (($sql[$i - 1] ?? null) === '>' || ($sql[$i + 1] ?? null) === '>')) {
                    continue;
                }
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
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $value) !== 1) {
            throw new \InvalidArgumentException("{$context} must be a simple identifier");
        }
    }

    private static function unquoteIdentifier(string $value): ?string
    {
        $value = trim($value);
        if (strlen($value) < 2) {
            return null;
        }

        $quote = $value[0];
        $last = $value[strlen($value) - 1];
        if ($quote === '"' && $last === '"') {
            return str_replace('""', '"', substr($value, 1, -1));
        }
        if ($quote === "'" && $last === "'") {
            return str_replace("''", "'", substr($value, 1, -1));
        }
        if ($quote === '`' && $last === '`') {
            return str_replace('``', '`', substr($value, 1, -1));
        }
        if ($quote === '[' && $last === ']') {
            return substr($value, 1, -1);
        }

        return null;
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
