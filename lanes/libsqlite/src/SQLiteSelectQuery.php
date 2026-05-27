<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectQuery
{
    /**
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    public static function execute(array $plan): array
    {
        $rows = self::sourceRows($plan);
        $rows = self::applyJoins($rows, $plan['joins'] ?? []);

        if (array_key_exists('where', $plan)) {
            $where = $plan['where'];
            if (!is_array($where)) {
                throw new \InvalidArgumentException('SQLite SELECT query where clause must be a predicate');
            }
            $rows = SQLiteSelectPredicate::filter($rows, $where);
        }

        if (array_key_exists('select', $plan)) {
            $select = $plan['select'];
            if (!is_array($select) || !array_is_list($select)) {
                throw new \InvalidArgumentException('SQLite SELECT query select list must be a list');
            }
            foreach ($select as $expression) {
                if (!is_array($expression)) {
                    throw new \InvalidArgumentException('SQLite SELECT query select expressions must be arrays');
                }
            }
            $rows = SQLiteSelectProjection::project($rows, $select);
        }

        $distinct = null;
        if (array_key_exists('distinct', $plan)) {
            if (!is_array($plan['distinct']) || !array_is_list($plan['distinct'])) {
                throw new \InvalidArgumentException('SQLite SELECT query distinct columns must be a list');
            }
            foreach ($plan['distinct'] as $column) {
                if (!is_string($column)) {
                    throw new \InvalidArgumentException('SQLite SELECT query distinct columns must be strings');
                }
            }
            $distinct = $plan['distinct'];
        }

        $orderBy = $plan['orderBy'] ?? [];
        if (!is_array($orderBy) || !array_is_list($orderBy)) {
            throw new \InvalidArgumentException('SQLite SELECT query orderBy terms must be a list');
        }

        $limit = null;
        if (array_key_exists('limit', $plan)) {
            if (!is_int($plan['limit'])) {
                throw new \InvalidArgumentException('SQLite SELECT query limit must be an integer');
            }
            $limit = $plan['limit'];
        }

        $offset = $plan['offset'] ?? 0;
        if (!is_int($offset)) {
            throw new \InvalidArgumentException('SQLite SELECT query offset must be an integer');
        }

        return SQLiteSelectResult::execute($rows, $distinct, $orderBy, $limit, $offset);
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    private static function sourceRows(array $plan): array
    {
        if (!array_key_exists('from', $plan)) {
            throw new \InvalidArgumentException('SQLite SELECT query needs from rows');
        }
        if (!is_array($plan['from']) || !array_is_list($plan['from'])) {
            throw new \InvalidArgumentException('SQLite SELECT query from rows must be a list');
        }

        foreach ($plan['from'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite SELECT query source rows must be arrays');
            }
        }

        return $plan['from'];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param mixed $joins
     * @return list<array<string,mixed>>
     */
    private static function applyJoins(array $rows, mixed $joins): array
    {
        if ($joins === []) {
            return $rows;
        }
        if (!is_array($joins) || !array_is_list($joins)) {
            throw new \InvalidArgumentException('SQLite SELECT query joins must be a list');
        }

        foreach ($joins as $join) {
            if (!is_array($join)) {
                throw new \InvalidArgumentException('SQLite SELECT query join must be an array');
            }

            $type = strtoupper(self::requiredString($join, 'type', 'join'));
            $rightRows = self::rightRows($join);
            $rows = match ($type) {
                'INNER' => SQLiteSelectResult::innerJoin($rows, $rightRows, self::requiredPredicate($join, 'INNER')),
                'LEFT' => SQLiteSelectResult::leftJoin($rows, $rightRows, self::requiredPredicate($join, 'LEFT'), self::rightColumns($join, $rightRows)),
                'CROSS' => SQLiteSelectResult::crossJoin($rows, $rightRows),
                'USING' => SQLiteSelectResult::joinUsing($rows, $rightRows, self::usingColumns($join), (bool) ($join['left'] ?? false)),
                default => throw new \InvalidArgumentException("SQLite SELECT query join type {$type} is not supported"),
            };
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $join
     * @return list<array<string,mixed>>
     */
    private static function rightRows(array $join): array
    {
        if (!isset($join['rows']) || !is_array($join['rows']) || !array_is_list($join['rows'])) {
            throw new \InvalidArgumentException('SQLite SELECT query join rows must be a list');
        }
        foreach ($join['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite SELECT query join rows must be arrays');
            }
        }

        return $join['rows'];
    }

    /**
     * @param array<string,mixed> $join
     * @return callable(array<string,mixed>,array<string,mixed>):bool|null
     */
    private static function requiredPredicate(array $join, string $type): callable
    {
        if (!isset($join['predicate']) || !is_callable($join['predicate'])) {
            throw new \InvalidArgumentException("SQLite SELECT query {$type} join needs a predicate");
        }

        return $join['predicate'];
    }

    /**
     * @param array<string,mixed> $join
     * @param list<array<string,mixed>> $rightRows
     * @return list<string>
     */
    private static function rightColumns(array $join, array $rightRows): array
    {
        if (array_key_exists('rightColumns', $join)) {
            if (!is_array($join['rightColumns']) || !array_is_list($join['rightColumns'])) {
                throw new \InvalidArgumentException('SQLite SELECT query LEFT join rightColumns must be a list');
            }
            foreach ($join['rightColumns'] as $column) {
                if (!is_string($column)) {
                    throw new \InvalidArgumentException('SQLite SELECT query LEFT join rightColumns must be strings');
                }
            }

            return $join['rightColumns'];
        }

        $columns = [];
        foreach ($rightRows as $row) {
            foreach (array_keys($row) as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite SELECT query join column names must be non-empty strings');
                }
                if (!in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }

    /**
     * @param array<string,mixed> $join
     * @return list<string>
     */
    private static function usingColumns(array $join): array
    {
        if (!isset($join['columns']) || !is_array($join['columns']) || !array_is_list($join['columns'])) {
            throw new \InvalidArgumentException('SQLite SELECT query USING join columns must be a list');
        }

        return $join['columns'];
    }

    /**
     * @param array<string,mixed> $input
     */
    private static function requiredString(array $input, string $key, string $context): string
    {
        if (!isset($input[$key]) || !is_string($input[$key]) || $input[$key] === '') {
            throw new \InvalidArgumentException("SQLite SELECT query {$context} needs {$key}");
        }

        return $input[$key];
    }
}
