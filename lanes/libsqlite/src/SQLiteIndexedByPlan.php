<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIndexedByPlan
{
    /**
     * @param list<array{name:string,table:string,columns:list<string>,unique?:bool,auto?:bool,partial?:array<string,mixed>,covering?:bool}> $indexes
     * @param list<array{column:string,operator?:string,value?:mixed}> $whereTerms
     * @return array<string,mixed>
     */
    public static function plan(
        string $statementType,
        string $tableName,
        array $indexes,
        array $whereTerms = [],
        ?string $indexedBy = null,
        bool $notIndexed = false,
        array $orderBy = [],
        array $projectedColumns = ['*'],
        bool $isView = false,
    ): array {
        $statement = strtoupper($statementType);
        if (!in_array($statement, ['SELECT', 'DELETE', 'UPDATE'], true)) {
            throw new \InvalidArgumentException('SQLite INDEXED BY planning only supports SELECT, DELETE, and UPDATE statements');
        }
        if ($tableName === '') {
            throw new \InvalidArgumentException('SQLite INDEXED BY planning needs a table name');
        }
        if ($indexedBy !== null && $notIndexed) {
            throw new \InvalidArgumentException('SQLite INDEXED BY and NOT INDEXED cannot both be specified');
        }
        if ($isView && $indexedBy !== null) {
            return self::error('no such index: ' . $indexedBy, $statement, $tableName, null, 'view-indexed-by');
        }

        $rowidTerm = self::rowidEqualityTerm($whereTerms);
        if ($notIndexed) {
            if ($rowidTerm !== null) {
                return self::result($statement, $tableName, 'integer-primary-key', null, 'SEARCH', ['rowid'], true, false, null, $whereTerms, $orderBy, $projectedColumns);
            }

            return self::result($statement, $tableName, 'table-scan', null, 'SCAN', [], false, false, null, $whereTerms, $orderBy, $projectedColumns);
        }

        if ($indexedBy !== null) {
            $index = self::findIndex($indexes, $indexedBy, $tableName);
            if ($index === null) {
                return self::error('no such index: ' . $indexedBy, $statement, $tableName, $indexedBy, 'missing-index');
            }
            if (!self::partialPredicateImplied($index['partial'] ?? null, $whereTerms)) {
                return self::error('no query solution', $statement, $tableName, $indexedBy, 'partial-index-not-implied');
            }

            return self::resultForIndex($statement, $tableName, $index, $whereTerms, $orderBy, $projectedColumns, true);
        }

        if ($rowidTerm !== null) {
            return self::result($statement, $tableName, 'integer-primary-key', null, 'SEARCH', ['rowid'], true, false, null, $whereTerms, $orderBy, $projectedColumns);
        }

        $best = null;
        $bestColumns = [];
        foreach ($indexes as $index) {
            if (($index['table'] ?? '') !== $tableName) {
                continue;
            }
            if (!self::partialPredicateImplied($index['partial'] ?? null, $whereTerms)) {
                continue;
            }
            $columns = self::matchedColumns($index['columns'] ?? [], $whereTerms);
            if ($columns === []) {
                continue;
            }
            if ($best === null || count($columns) > count($bestColumns) || (count($columns) === count($bestColumns) && strcmp($index['name'], $best['name']) < 0)) {
                $best = $index;
                $bestColumns = $columns;
            }
        }

        if ($best === null) {
            return self::result($statement, $tableName, 'table-scan', null, 'SCAN', [], false, false, null, $whereTerms, $orderBy, $projectedColumns);
        }

        return self::resultForIndex($statement, $tableName, $best, $whereTerms, $orderBy, $projectedColumns, false);
    }

    /**
     * @param list<array<string,mixed>> $queries
     * @return list<array<string,mixed>>
     */
    public static function batch(array $queries): array
    {
        $plans = [];
        foreach ($queries as $query) {
            $plans[] = self::plan(
                (string) ($query['statement'] ?? 'SELECT'),
                (string) ($query['table'] ?? ''),
                $query['indexes'] ?? [],
                $query['where'] ?? [],
                $query['indexedBy'] ?? null,
                (bool) ($query['notIndexed'] ?? false),
                $query['orderBy'] ?? [],
                $query['projectedColumns'] ?? ['*'],
                (bool) ($query['isView'] ?? false),
            );
        }

        return $plans;
    }

    /**
     * @param list<array{name:string,table:string,columns:list<string>}> $indexes
     */
    private static function findIndex(array $indexes, string $name, string $tableName): ?array
    {
        foreach ($indexes as $index) {
            if (strcasecmp($index['name'], $name) === 0 && ($index['table'] ?? '') === $tableName) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<array{column:string,operator?:string,value?:mixed}> $whereTerms
     */
    private static function rowidEqualityTerm(array $whereTerms): ?array
    {
        foreach ($whereTerms as $term) {
            $column = strtolower((string) ($term['column'] ?? ''));
            $operator = strtoupper((string) ($term['operator'] ?? '='));
            if (in_array($column, ['rowid', '_rowid_', 'oid'], true) && $operator === '=') {
                return $term;
            }
        }

        return null;
    }

    /**
     * @param list<string> $columns
     * @param list<array{column:string,operator?:string,value?:mixed}> $whereTerms
     * @return list<string>
     */
    private static function matchedColumns(array $columns, array $whereTerms): array
    {
        $terms = [];
        foreach ($whereTerms as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? '='));
            if (!in_array($operator, ['=', 'IS', 'IN', '>', '>=', '<', '<=', 'BETWEEN'], true)) {
                continue;
            }
            $terms[strtolower((string) ($term['column'] ?? ''))] = true;
        }

        $matched = [];
        foreach ($columns as $column) {
            if (!isset($terms[strtolower($column)])) {
                break;
            }
            $matched[] = $column;
        }

        return $matched;
    }

    /**
     * @param null|array<string,mixed> $partial
     * @param list<array{column:string,operator?:string,value?:mixed}> $whereTerms
     */
    private static function partialPredicateImplied(?array $partial, array $whereTerms): bool
    {
        if ($partial === null) {
            return true;
        }

        $column = strtolower((string) ($partial['column'] ?? ''));
        $operator = strtoupper((string) ($partial['operator'] ?? '='));
        foreach ($whereTerms as $term) {
            if (strtolower((string) ($term['column'] ?? '')) !== $column) {
                continue;
            }
            if (strtoupper((string) ($term['operator'] ?? '=')) !== $operator) {
                continue;
            }
            if (($term['value'] ?? null) === ($partial['value'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{column:string,operator?:string,value?:mixed}> $whereTerms
     * @param list<string> $orderBy
     * @param list<string> $projectedColumns
     */
    private static function resultForIndex(string $statement, string $tableName, array $index, array $whereTerms, array $orderBy, array $projectedColumns, bool $forced): array
    {
        $matched = self::matchedColumns($index['columns'] ?? [], $whereTerms);
        $access = $matched === [] ? 'SCAN' : 'SEARCH';
        $covering = self::isCovering($statement, $index, $projectedColumns);

        return self::result(
            $statement,
            $tableName,
            $covering ? 'covering-index' : 'index',
            $index['name'],
            $access,
            $matched,
            $covering,
            $forced,
            $index['partial'] ?? null,
            $whereTerms,
            $orderBy,
            $projectedColumns,
            (bool) ($index['auto'] ?? false),
        );
    }

    /**
     * @param list<string> $projectedColumns
     */
    private static function isCovering(string $statement, array $index, array $projectedColumns): bool
    {
        if ($statement !== 'SELECT' && $statement !== 'UPDATE') {
            return false;
        }
        if (($index['covering'] ?? false) === true) {
            return true;
        }
        if ($projectedColumns === ['*']) {
            return false;
        }

        $columns = array_map('strtolower', $index['columns'] ?? []);
        foreach ($projectedColumns as $column) {
            if ($column === 'rowid') {
                continue;
            }
            if (!in_array(strtolower($column), $columns, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $matchedColumns
     * @param null|array<string,mixed> $partial
     * @param list<array{column:string,operator?:string,value?:mixed}> $whereTerms
     * @param list<string> $orderBy
     * @param list<string> $projectedColumns
     * @return array<string,mixed>
     */
    private static function result(string $statement, string $tableName, string $accessPath, ?string $indexName, string $operation, array $matchedColumns, bool $covering, bool $forced, ?array $partial, array $whereTerms, array $orderBy, array $projectedColumns, bool $autoIndex = false): array
    {
        return [
            'ok' => true,
            'statement' => $statement,
            'table' => $tableName,
            'operation' => $operation,
            'accessPath' => $accessPath,
            'indexName' => $indexName,
            'forced' => $forced,
            'notIndexed' => $accessPath === 'table-scan' || $accessPath === 'integer-primary-key',
            'matchedColumns' => $matchedColumns,
            'covering' => $covering,
            'autoIndex' => $autoIndex,
            'partial' => $partial !== null,
            'whereTermCount' => count($whereTerms),
            'orderBy' => $orderBy,
            'projectedColumns' => $projectedColumns,
            'detail' => self::detail($tableName, $operation, $accessPath, $indexName, $matchedColumns, $covering),
            'upstream' => 'indexedby.test',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function error(string $message, string $statement, string $tableName, ?string $indexName, string $reason): array
    {
        return [
            'ok' => false,
            'statement' => $statement,
            'table' => $tableName,
            'operation' => 'ERROR',
            'accessPath' => null,
            'indexName' => $indexName,
            'forced' => $indexName !== null,
            'reason' => $reason,
            'error' => $message,
            'detail' => $message,
            'upstream' => 'indexedby.test',
        ];
    }

    /**
     * @param list<string> $matchedColumns
     */
    private static function detail(string $tableName, string $operation, string $accessPath, ?string $indexName, array $matchedColumns, bool $covering): string
    {
        if ($accessPath === 'integer-primary-key') {
            return "SEARCH {$tableName} USING INTEGER PRIMARY KEY (rowid=?)";
        }
        if ($accessPath === 'table-scan') {
            return "SCAN {$tableName}";
        }

        $prefix = $operation === 'SCAN' ? 'SCAN' : 'SEARCH';
        $coveringText = $covering ? ' COVERING' : '';
        $constraint = $matchedColumns === [] ? '' : ' (' . implode('=? AND ', $matchedColumns) . '=?)';

        return "{$prefix} {$tableName} USING{$coveringText} INDEX {$indexName}{$constraint}";
    }
}
