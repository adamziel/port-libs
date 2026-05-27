<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonbPatchGeneratedIndexPlan
{
    /**
     * @param list<array{name?:string,sql:string,rootPage?:int,estimatedRows?:int,coveringColumns?:list<string>}> $indexDefinitions
     * @param list<string> $generatedColumnSql
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    public static function rankedPlans(
        array $indexDefinitions,
        array $generatedColumnSql,
        array $predicate,
        array $orderBy = [],
        array $neededColumns = [],
    ): array {
        $generatedColumns = self::generatedColumns($generatedColumnSql);
        $terms = self::flattenAndTerms($predicate);
        $plans = [];

        foreach ($terms as $term) {
            $constraint = self::constraintFromPredicate($term);
            if ($constraint === null) {
                continue;
            }

            foreach ($indexDefinitions as $indexDefinition) {
                $index = self::indexExpression($indexDefinition['sql'], $generatedColumns);
                if ($index === null || !self::sameExpression($constraint['expression'], $index)) {
                    continue;
                }
                if ($index['partialPredicate'] !== null && !self::partialPredicateImplied($index['partialPredicate'], $terms)) {
                    continue;
                }
                if (!self::compatibleValues($constraint['operator'], $constraint['values'])) {
                    continue;
                }

                $estimatedRows = max(1, (int) ($indexDefinition['estimatedRows'] ?? 1000));
                if ($constraint['operator'] === 'point') {
                    $estimatedRows = max(1, intdiv($estimatedRows, 20));
                } elseif ($constraint['operator'] === 'IN' && is_array($constraint['values'])) {
                    $estimatedRows = max(1, min($estimatedRows, count(array_filter($constraint['values'], static fn (mixed $value): bool => $value !== null)) * 8));
                } else {
                    $estimatedRows = max(1, intdiv($estimatedRows, 4));
                }

                $orderSatisfied = self::orderSatisfied($index, $orderBy);
                $covering = self::covering($indexDefinition, $neededColumns);
                $plans[] = [
                    'usable' => true,
                    'name' => $indexDefinition['name'] ?? null,
                    'rootPage' => $indexDefinition['rootPage'] ?? null,
                    'generatedColumn' => $index['generatedColumn'],
                    'sourceColumn' => $index['sourceColumn'],
                    'patchJson' => $index['patchJson'],
                    'path' => $index['path'],
                    'operator' => $constraint['operator'],
                    'values' => $constraint['values'],
                    'partial' => $index['partialPredicate'] !== null,
                    'residualPredicateRequired' => true,
                    'orderBySatisfied' => $orderSatisfied,
                    'covering' => $covering,
                    'estimatedRows' => $estimatedRows,
                    'estimatedCost' => $estimatedRows + ($orderSatisfied ? 0 : 25) + ($covering ? 0 : 10),
                ];
            }
        }

        usort($plans, static function (array $left, array $right): int {
            return [$left['estimatedCost'], $left['estimatedRows'], (string) ($left['name'] ?? '')]
                <=> [$right['estimatedCost'], $right['estimatedRows'], (string) ($right['name'] ?? '')];
        });

        return $plans;
    }

    /**
     * @param list<array{name?:string,sql:string,rootPage?:int,estimatedRows?:int,coveringColumns?:list<string>}> $indexDefinitions
     * @param list<string> $generatedColumnSql
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return null|array<string,mixed>
     */
    public static function choose(
        array $indexDefinitions,
        array $generatedColumnSql,
        array $predicate,
        array $orderBy = [],
        array $neededColumns = [],
    ): ?array {
        return self::rankedPlans($indexDefinitions, $generatedColumnSql, $predicate, $orderBy, $neededColumns)[0] ?? null;
    }

    /**
     * @param list<string> $generatedColumnSql
     * @return array<string,array{generatedColumn:string,sourceColumn:string,patchJson:string,path:string}>
     */
    private static function generatedColumns(array $generatedColumnSql): array
    {
        $columns = [];
        foreach ($generatedColumnSql as $sql) {
            if (!preg_match('/\b([A-Za-z_][A-Za-z0-9_]*)\b[^,]*\bAS\s*\((.*)\)\s*(?:VIRTUAL|STORED)?/is', $sql, $match)) {
                continue;
            }

            $expression = self::jsonbPatchExtractExpression($match[2]);
            if ($expression === null) {
                continue;
            }

            $expression['generatedColumn'] = $match[1];
            $columns[strtolower($match[1])] = $expression;
        }

        return $columns;
    }

    /**
     * @param array<string,array{generatedColumn:string,sourceColumn:string,patchJson:string,path:string}> $generatedColumns
     * @return null|array{generatedColumn:?string,sourceColumn:string,patchJson:string,path:string,partialPredicate:?array{column:string,value:mixed},descending:bool}
     */
    private static function indexExpression(string $sql, array $generatedColumns): ?array
    {
        if (!preg_match('/\bON\s+[A-Za-z_][A-Za-z0-9_]*\s*\((.*)\)\s*(.*)$/is', $sql, $match)) {
            return null;
        }

        $term = trim(self::firstTopLevelTerm($match[1]));
        $tail = (string) $match[2];
        $descending = preg_match('/\bDESC\b/i', $term) === 1;
        $term = preg_replace('/\s+COLLATE\s+[A-Za-z_][A-Za-z0-9_]*|\s+ASC\b|\s+DESC\b/i', '', $term) ?? $term;
        $partialPredicate = self::partialPredicate($tail);

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $term) === 1) {
            $generated = $generatedColumns[strtolower($term)] ?? null;
            if ($generated === null) {
                return null;
            }

            return [
                'generatedColumn' => $generated['generatedColumn'],
                'sourceColumn' => $generated['sourceColumn'],
                'patchJson' => $generated['patchJson'],
                'path' => $generated['path'],
                'partialPredicate' => $partialPredicate,
                'descending' => $descending,
            ];
        }

        $expression = self::jsonbPatchExtractExpression($term);
        if ($expression === null) {
            return null;
        }

        return [
            'generatedColumn' => null,
            'sourceColumn' => $expression['sourceColumn'],
            'patchJson' => $expression['patchJson'],
            'path' => $expression['path'],
            'partialPredicate' => $partialPredicate,
            'descending' => $descending,
        ];
    }

    /**
     * @return null|array{sourceColumn:string,patchJson:string,path:string}
     */
    private static function jsonbPatchExtractExpression(string $expression): ?array
    {
        $expression = trim($expression);
        if (!preg_match("/^json_extract\\s*\\(\\s*jsonb_patch\\s*\\(\\s*([A-Za-z_][A-Za-z0-9_]*)\\s*,\\s*('(?:''|[^'])*')\\s*\\)\\s*,\\s*('(?:''|[^'])*')\\s*\\)$/is", $expression, $match)) {
            return null;
        }

        $patchJson = self::literal($match[2]);
        $path = self::literal($match[3]);
        if (!is_string($patchJson) || !is_string($path) || !SQLiteJsonPath::isWellFormed($path)) {
            return null;
        }

        SQLiteJsonPatch::patch('{}', $patchJson);

        return [
            'sourceColumn' => $match[1],
            'patchJson' => SQLiteJsonCanonical::encodeDecodedJson(SQLiteJson5Parser::decode($patchJson)),
            'path' => $path,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function flattenAndTerms(array $predicate): array
    {
        if (strtoupper((string) ($predicate['operator'] ?? '')) !== 'AND') {
            return [$predicate];
        }

        $terms = $predicate['terms'] ?? [];
        if (!is_array($terms) || !array_is_list($terms)) {
            throw new \InvalidArgumentException('SQLite JSONB patch generated-index AND predicate needs a term list');
        }

        $flattened = [];
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite JSONB patch generated-index predicate term must be an array');
            }
            array_push($flattened, ...self::flattenAndTerms($term));
        }

        return $flattened;
    }

    /**
     * @return null|array{expression:array{generatedColumn:?string,sourceColumn:string,patchJson:string,path:string},operator:string,values:mixed}
     */
    private static function constraintFromPredicate(array $predicate): ?array
    {
        $operator = strtoupper((string) ($predicate['operator'] ?? ''));
        if ($operator === '=' || $operator === '==') {
            return self::binaryConstraint($predicate, 'point');
        }
        if (in_array($operator, ['<', '<=', '>', '>='], true)) {
            return self::binaryConstraint($predicate, 'range-' . $operator);
        }
        if ($operator === 'IN') {
            $left = self::expressionOperand($predicate['left'] ?? null);
            $values = $predicate['values'] ?? null;
            if ($left === null || !is_array($values) || !array_is_list($values)) {
                return null;
            }

            return ['expression' => $left, 'operator' => 'IN', 'values' => $values];
        }

        return null;
    }

    /**
     * @return null|array{expression:array{generatedColumn:?string,sourceColumn:string,patchJson:string,path:string},operator:string,values:mixed}
     */
    private static function binaryConstraint(array $predicate, string $operator): ?array
    {
        $left = self::expressionOperand($predicate['left'] ?? null);
        $right = self::expressionOperand($predicate['right'] ?? null);
        if ($left !== null && $right === null && array_key_exists('right', $predicate)) {
            return ['expression' => $left, 'operator' => $operator, 'values' => $predicate['right']];
        }
        if ($right !== null && $left === null && array_key_exists('left', $predicate)) {
            return ['expression' => $right, 'operator' => self::reverseOperator($operator), 'values' => $predicate['left']];
        }

        return null;
    }

    /**
     * @return null|array{generatedColumn:?string,sourceColumn:string,patchJson:string,path:string}
     */
    private static function expressionOperand(mixed $operand): ?array
    {
        if (!is_array($operand)) {
            return null;
        }
        $column = $operand['generatedColumn'] ?? null;
        if (is_string($column) && $column !== '') {
            return ['generatedColumn' => $column, 'sourceColumn' => '', 'patchJson' => '', 'path' => ''];
        }
        if (($operand['function'] ?? null) !== 'json_extract_jsonb_patch') {
            return null;
        }

        $sourceColumn = $operand['sourceColumn'] ?? null;
        $patchJson = $operand['patchJson'] ?? null;
        $path = $operand['path'] ?? null;
        if (!is_string($sourceColumn) || !is_string($patchJson) || !is_string($path) || !SQLiteJsonPath::isWellFormed($path)) {
            return null;
        }

        return [
            'generatedColumn' => null,
            'sourceColumn' => $sourceColumn,
            'patchJson' => SQLiteJsonCanonical::encodeDecodedJson(SQLiteJson5Parser::decode($patchJson)),
            'path' => $path,
        ];
    }

    /**
     * @param array{generatedColumn:?string,sourceColumn:string,patchJson:string,path:string} $constraint
     * @param array{generatedColumn:?string,sourceColumn:string,patchJson:string,path:string} $index
     */
    private static function sameExpression(array $constraint, array $index): bool
    {
        if ($constraint['generatedColumn'] !== null) {
            return is_string($index['generatedColumn']) && strcasecmp($constraint['generatedColumn'], $index['generatedColumn']) === 0;
        }

        return strcasecmp($constraint['sourceColumn'], $index['sourceColumn']) === 0
            && $constraint['patchJson'] === $index['patchJson']
            && $constraint['path'] === $index['path'];
    }

    /**
     * @return null|array{column:string,value:mixed}
     */
    private static function partialPredicate(string $tail): ?array
    {
        if (!preg_match("/\\bWHERE\\s+([A-Za-z_][A-Za-z0-9_]*)\\s*=\\s*('(?:''|[^'])*'|[0-9]+)/i", $tail, $match)) {
            return null;
        }

        return ['column' => $match[1], 'value' => self::literal($match[2])];
    }

    /**
     * @param null|array{column:string,value:mixed} $partialPredicate
     * @param list<array<string,mixed>> $terms
     */
    private static function partialPredicateImplied(?array $partialPredicate, array $terms): bool
    {
        if ($partialPredicate === null) {
            return true;
        }

        foreach ($terms as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if ($operator !== '=' && $operator !== '==') {
                continue;
            }

            foreach ([['left', 'right'], ['right', 'left']] as [$columnSide, $valueSide]) {
                $column = $term[$columnSide] ?? null;
                if (is_array($column) && isset($column['column']) && strcasecmp((string) $column['column'], $partialPredicate['column']) === 0 && ($term[$valueSide] ?? null) === $partialPredicate['value']) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function compatibleValues(string $operator, mixed $values): bool
    {
        $list = $operator === 'IN' && is_array($values) ? $values : [$values];
        foreach ($list as $value) {
            if ($value !== null && !is_string($value) && !is_int($value) && !is_float($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{generatedColumn:?string,sourceColumn:string,patchJson:string,path:string,partialPredicate:?array{column:string,value:mixed},descending:bool} $index
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function orderSatisfied(array $index, array $orderBy): bool
    {
        if ($orderBy === []) {
            return false;
        }
        $first = $orderBy[0];
        $column = $index['generatedColumn'];
        if (!is_string($column) || strcasecmp($first['column'], $column) !== 0) {
            return false;
        }

        $direction = strtoupper($first['direction'] ?? 'ASC');
        return ($direction === 'DESC') === $index['descending'];
    }

    /**
     * @param array{name?:string,sql:string,rootPage?:int,estimatedRows?:int,coveringColumns?:list<string>} $indexDefinition
     * @param list<string> $neededColumns
     */
    private static function covering(array $indexDefinition, array $neededColumns): bool
    {
        $coveringColumns = array_map('strtolower', $indexDefinition['coveringColumns'] ?? []);
        foreach ($neededColumns as $column) {
            if (!in_array(strtolower($column), $coveringColumns, true)) {
                return false;
            }
        }

        return true;
    }

    private static function reverseOperator(string $operator): string
    {
        return match ($operator) {
            'range-<' => 'range->',
            'range-<=' => 'range->=',
            'range->' => 'range-<',
            'range->=' => 'range-<=',
            default => $operator,
        };
    }

    private static function firstTopLevelTerm(string $terms): string
    {
        $depth = 0;
        $quote = false;
        for ($offset = 0, $length = strlen($terms); $offset < $length; $offset++) {
            $char = $terms[$offset];
            if ($quote) {
                if ($char === "'" && ($terms[$offset + 1] ?? '') === "'") {
                    $offset++;
                } elseif ($char === "'") {
                    $quote = false;
                }
                continue;
            }
            if ($char === "'") {
                $quote = true;
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
            if ($char === ',' && $depth === 0) {
                return substr($terms, 0, $offset);
            }
        }

        return $terms;
    }

    private static function literal(string $sql): mixed
    {
        $sql = trim($sql);
        if ($sql !== '' && $sql[0] === "'") {
            return str_replace("''", "'", substr($sql, 1, -1));
        }
        if (preg_match('/^-?[0-9]+$/', $sql) === 1) {
            return (int) $sql;
        }

        return $sql;
    }
}
