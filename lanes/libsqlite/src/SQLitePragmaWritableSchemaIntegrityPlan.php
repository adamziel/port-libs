<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaWritableSchemaIntegrityPlan
{
    /**
     * Model the integrity_check diagnostics reached after writable_schema has
     * rewritten a table/index definition and the schema is reloaded by rename.
     *
     * @param list<array<string,mixed>> $rows
     * @return array{source:string,pragma:string,limit:int,scope:?string,table:string,renamed_table:string,index:string,unique_columns:list<string>,required_columns:list<string>,rows_checked:int,result:list<string>,rows:list<array<string,string>>,violations:list<array{kind:string,row:int,column:string,index:?string,value:mixed,message:string}>,schema_events:list<string>}
     */
    public static function constraintViolationPlan(
        string $pragmaSql,
        string $createTableSql,
        string $createIndexSql,
        array $rows,
        ?string $renamedTable = null
    ): array {
        $pragma = self::parsePragmaSql($pragmaSql);
        $table = self::parseCreateTable($createTableSql);
        $index = self::parseUniqueIndex($createIndexSql);
        if (strcasecmp($table['table'], $index['table']) !== 0) {
            throw new InvalidArgumentException('SQLite writable-schema integrity plan requires the index to target the parsed table');
        }

        $renamedTable ??= $table['table'];
        $violations = self::interleaveViolations(
            self::uniqueViolations($rows, $index['name'], $index['columns']),
            self::requiredColumnViolations($rows, $renamedTable, $table['required_columns'])
        );
        $result = array_slice(array_map(static fn (array $violation): string => $violation['message'], $violations), 0, $pragma['limit']);
        if ($result === []) {
            $result = ['ok'];
        }

        return [
            'source' => 'pragma.test pragma-3.20 through pragma-3.23',
            'pragma' => $pragma['pragma'],
            'limit' => $pragma['limit'],
            'scope' => $pragma['scope'],
            'table' => $table['table'],
            'renamed_table' => $renamedTable,
            'index' => $index['name'],
            'unique_columns' => $index['columns'],
            'required_columns' => $table['required_columns'],
            'rows_checked' => count($rows),
            'result' => $result,
            'rows' => array_map(static fn (string $message): array => [$pragma['pragma'] => $message], $result),
            'violations' => array_slice($violations, 0, $pragma['limit']),
            'schema_events' => [
                'writable_schema_on',
                'sqlite_schema_index_sql_rewritten',
                'sqlite_schema_table_sql_rewritten',
                $renamedTable === $table['table'] ? 'schema_reloaded' : 'schema_reloaded_by_rename',
                'writable_schema_off',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $alterTableSql
     * @return array{source:string,pragma:string,limit:int,scope:?string,table:string,columns:list<string>,added_columns:list<array{name:string,required:bool,default:mixed,check:string|null}>,projected_rows:list<array<string,mixed>>,result:list<string>,rows:list<array<string,string>>,rows_checked:int}
     */
    public static function additiveColumnIntegrityPlan(
        string $pragmaSql,
        string $createTableSql,
        array $rows,
        array $alterTableSql
    ): array {
        $pragma = self::parsePragmaSql($pragmaSql);
        $table = self::parseCreateTable($createTableSql);
        $columns = $table['columns'];
        $projectedRows = $rows;
        $addedColumns = [];
        foreach ($alterTableSql as $sql) {
            $added = self::parseAddColumn($sql, $table['table']);
            $columns[] = $added['name'];
            $addedColumns[] = $added;
            foreach ($projectedRows as $rowOffset => $row) {
                if (!is_array($row)) {
                    throw new InvalidArgumentException('SQLite writable-schema additive column plan rows must be arrays');
                }
                $projectedRows[$rowOffset][$added['name']] = $added['default'];
            }
        }

        return [
            'source' => 'pragma.test pragma-3.24 through pragma-3.25',
            'pragma' => $pragma['pragma'],
            'limit' => $pragma['limit'],
            'scope' => $pragma['scope'],
            'table' => $table['table'],
            'columns' => $columns,
            'added_columns' => $addedColumns,
            'projected_rows' => $projectedRows,
            'result' => ['ok'],
            'rows' => [[$pragma['pragma'] => 'ok']],
            'rows_checked' => count($projectedRows),
        ];
    }

    /**
     * Model the integrity_check rows reached when writable_schema swaps two
     * index rootpages whose NOCASE columns compare equal but are not
     * byte-for-byte identical.
     *
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @param list<array{name:string,collation?:string}|array{0:string,1?:string}> $indexColumns
     * @return array{source:string,pragma:string,limit:int,scope:?string,left_table:string,right_table:string,left_index:string,right_index:string,index_columns:list<array{name:string,collation:string}>,rootpage_swap:bool,result:list<string>,rows:list<array<string,string>>,violations:list<array{kind:string,table:string,index:string,row:int,expected:array<string,mixed>,actual:array<string,mixed>|null,collated_match:bool,byte_for_byte_match:bool,message:string}>,rows_checked:int,schema_events:list<string>}
     */
    public static function indexRootSwapIntegrityPlan(
        string $pragmaSql,
        array $leftRows,
        array $rightRows,
        array $indexColumns,
        string $leftTable,
        string $rightTable,
        string $leftIndex,
        string $rightIndex
    ): array {
        $pragma = self::parsePragmaSql($pragmaSql);
        $columns = self::normalizeIndexColumns($indexColumns);

        $violations = array_merge(
            self::indexRootSwapViolations($leftRows, $rightRows, $columns, $leftTable, $leftIndex),
            self::indexRootSwapViolations($rightRows, $leftRows, $columns, $rightTable, $rightIndex)
        );
        usort($violations, static fn (array $left, array $right): int => [$left['row'], $left['message']] <=> [$right['row'], $right['message']]);

        $result = array_slice(array_map(static fn (array $violation): string => $violation['message'], $violations), 0, $pragma['limit']);
        if ($result === []) {
            $result = ['ok'];
        }

        return [
            'source' => 'pragma.test pragma-3.40 through pragma-3.41',
            'pragma' => $pragma['pragma'],
            'limit' => $pragma['limit'],
            'scope' => $pragma['scope'],
            'left_table' => $leftTable,
            'right_table' => $rightTable,
            'left_index' => $leftIndex,
            'right_index' => $rightIndex,
            'index_columns' => $columns,
            'rootpage_swap' => true,
            'result' => $result,
            'rows' => array_map(static fn (string $message): array => [$pragma['pragma'] => $message], $result),
            'violations' => array_slice($violations, 0, $pragma['limit']),
            'rows_checked' => count($leftRows) + count($rightRows),
            'schema_events' => [
                'writable_schema_on',
                'sqlite_schema_index_rootpages_swapped',
                'writable_schema_reset',
                'pragma_integrity_check_virtual_table_scan',
            ],
        ];
    }

    /**
     * @return array{pragma:string,limit:int,scope:?string}
     */
    private static function parsePragmaSql(string $sql): array
    {
        $identifier = self::identifierPattern();
        $trimmed = trim(rtrim(trim($sql), ';'));
        if (!preg_match('/^PRAGMA\s+(?:(?:' . $identifier . ')\s*\.\s*)?(?<pragma>integrity_check|quick_check)(?:\s*(?:\(\s*(?<argument>[^)]+)\s*\)|=\s*(?<equals>\d+)))?$/i', $trimmed, $match)) {
            throw new InvalidArgumentException('SQLite writable-schema integrity plan requires an integrity_check PRAGMA');
        }

        $limit = 100;
        $scope = null;
        $argument = trim((string) ($match['argument'] ?? ''));
        if ($argument !== '') {
            if (preg_match('/^\d+$/', $argument) === 1) {
                $limit = max(1, (int) $argument);
            } elseif (preg_match('/^' . $identifier . '$/i', $argument) === 1) {
                $scope = self::unquoteIdentifier($argument);
            } else {
                throw new InvalidArgumentException('SQLite writable-schema integrity plan received an unsupported PRAGMA argument');
            }
        } elseif (($match['equals'] ?? '') !== '') {
            $limit = max(1, (int) $match['equals']);
        }

        return [
            'pragma' => strtolower($match['pragma']),
            'limit' => $limit,
            'scope' => $scope,
        ];
    }

    /**
     * @return array{table:string,columns:list<string>,required_columns:list<string>}
     */
    private static function parseCreateTable(string $sql): array
    {
        $identifier = self::identifierPattern();
        if (!preg_match('/^\s*CREATE\s+TABLE\s+(?<table>' . $identifier . ')\s*\((?<body>.*)\)\s*;?\s*$/is', $sql, $match)) {
            throw new InvalidArgumentException('SQLite writable-schema integrity plan requires a simple CREATE TABLE statement');
        }

        $columns = [];
        $requiredColumns = [];
        foreach (self::splitTopLevelComma($match['body']) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (!preg_match('/^(?<name>' . $identifier . ')(?<definition>.*)$/is', $part, $columnMatch)) {
                throw new InvalidArgumentException('SQLite writable-schema integrity plan table column is unsupported');
            }
            $name = self::unquoteIdentifier($columnMatch['name']);
            $columns[] = $name;
            if (preg_match('/\bNOT\s+NULL\b/i', $columnMatch['definition']) === 1) {
                $requiredColumns[] = $name;
            }
        }

        if ($columns === []) {
            throw new InvalidArgumentException('SQLite writable-schema integrity plan requires at least one table column');
        }

        return [
            'table' => self::unquoteIdentifier($match['table']),
            'columns' => $columns,
            'required_columns' => $requiredColumns,
        ];
    }

    /**
     * @return array{name:string,table:string,columns:list<string>}
     */
    private static function parseUniqueIndex(string $sql): array
    {
        $identifier = self::identifierPattern();
        if (!preg_match('/^\s*CREATE\s+UNIQUE\s+INDEX\s+(?<index>' . $identifier . ')\s+ON\s+(?<table>' . $identifier . ')\s*\((?<columns>.*)\)\s*;?\s*$/is', $sql, $match)) {
            throw new InvalidArgumentException('SQLite writable-schema integrity plan requires a simple CREATE UNIQUE INDEX statement');
        }

        $columns = [];
        foreach (self::splitTopLevelComma($match['columns']) as $column) {
            $column = trim($column);
            if (!preg_match('/^' . $identifier . '$/i', $column)) {
                throw new InvalidArgumentException('SQLite writable-schema integrity plan supports only column-name unique indexes');
            }
            $columns[] = self::unquoteIdentifier($column);
        }
        if ($columns === []) {
            throw new InvalidArgumentException('SQLite writable-schema integrity plan requires at least one unique index column');
        }

        return [
            'name' => self::unquoteIdentifier($match['index']),
            'table' => self::unquoteIdentifier($match['table']),
            'columns' => $columns,
        ];
    }

    /**
     * @return array{name:string,required:bool,default:mixed,check:string|null}
     */
    private static function parseAddColumn(string $sql, string $table): array
    {
        $identifier = self::identifierPattern();
        if (!preg_match('/^\s*ALTER\s+TABLE\s+' . $identifier . '\s+ADD\s+COLUMN\s+(?<column>' . $identifier . ')(?<definition>.*)\s*;?\s*$/is', $sql, $match)) {
            throw new InvalidArgumentException('SQLite writable-schema additive column plan requires ALTER TABLE ADD COLUMN');
        }
        if (!preg_match('/^\s*ALTER\s+TABLE\s+(?<table>' . $identifier . ')/i', $sql, $tableMatch) || strcasecmp(self::unquoteIdentifier($tableMatch['table']), $table) !== 0) {
            throw new InvalidArgumentException('SQLite writable-schema additive column plan requires ALTER TABLE to target the parsed table');
        }

        $definition = trim($match['definition']);
        $default = null;
        if (preg_match('/\bDEFAULT\s+(?<value>\S+)/i', $definition, $defaultMatch)) {
            $default = self::parseLiteral($defaultMatch['value']);
        }
        $check = null;
        if (preg_match('/\bCHECK\s*\((?<expr>[^)]*)\)/i', $definition, $checkMatch)) {
            $check = trim($checkMatch['expr']);
        }

        return [
            'name' => self::unquoteIdentifier($match['column']),
            'required' => preg_match('/\bNOT\s+NULL\b/i', $definition) === 1,
            'default' => $default,
            'check' => $check,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array{kind:string,row:int,column:string,index:?string,value:mixed,message:string}>
     */
    private static function uniqueViolations(array $rows, string $index, array $columns): array
    {
        $counts = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('SQLite writable-schema integrity plan rows must be arrays');
            }
            $key = self::uniqueKey($row, $columns);
            if ($key === null) {
                continue;
            }
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $violations = [];
        foreach ($rows as $offset => $row) {
            $key = self::uniqueKey($row, $columns);
            if ($key === null || ($counts[$key] ?? 0) < 2) {
                continue;
            }
            $violations[] = [
                'kind' => 'unique',
                'row' => $offset + 1,
                'column' => implode(',', $columns),
                'index' => $index,
                'value' => $key,
                'message' => "non-unique entry in index {$index}",
            ];
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array{kind:string,row:int,column:string,index:?string,value:mixed,message:string}>
     */
    private static function requiredColumnViolations(array $rows, string $table, array $columns): array
    {
        $violations = [];
        foreach ($rows as $offset => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('SQLite writable-schema integrity plan rows must be arrays');
            }
            foreach ($columns as $column) {
                if (array_key_exists($column, $row) && $row[$column] !== null) {
                    continue;
                }
                $violations[] = [
                    'kind' => 'required_column',
                    'row' => $offset + 1,
                    'column' => $column,
                    'index' => null,
                    'value' => null,
                    'message' => "NULL value in {$table}.{$column}",
                ];
            }
        }

        return $violations;
    }

    /**
     * @param list<array{kind:string,row:int,column:string,index:?string,value:mixed,message:string}> $left
     * @param list<array{kind:string,row:int,column:string,index:?string,value:mixed,message:string}> $right
     * @return list<array{kind:string,row:int,column:string,index:?string,value:mixed,message:string}>
     */
    private static function interleaveViolations(array $left, array $right): array
    {
        $interleaved = [];
        $max = max(count($left), count($right));
        for ($i = 0; $i < $max; $i++) {
            if (isset($left[$i])) {
                $interleaved[] = $left[$i];
            }
            if (isset($right[$i])) {
                $interleaved[] = $right[$i];
            }
        }

        return $interleaved;
    }

    /**
     * @param list<array{name:string,collation?:string}|array{0:string,1?:string}> $indexColumns
     * @return list<array{name:string,collation:string}>
     */
    private static function normalizeIndexColumns(array $indexColumns): array
    {
        $columns = [];
        foreach ($indexColumns as $column) {
            if (!is_array($column)) {
                throw new InvalidArgumentException('SQLite writable-schema index root swap columns must be arrays');
            }

            $name = (string) ($column['name'] ?? $column[0] ?? '');
            if ($name === '') {
                throw new InvalidArgumentException('SQLite writable-schema index root swap column name cannot be empty');
            }

            $collation = strtoupper((string) ($column['collation'] ?? $column[1] ?? 'BINARY'));
            if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
                throw new InvalidArgumentException("SQLite writable-schema index root swap collation {$collation} is unsupported");
            }

            $columns[] = ['name' => $name, 'collation' => $collation];
        }

        if ($columns === []) {
            throw new InvalidArgumentException('SQLite writable-schema index root swap requires at least one indexed column');
        }

        return $columns;
    }

    /**
     * @param list<array<string,mixed>> $tableRows
     * @param list<array<string,mixed>> $swappedIndexRows
     * @param list<array{name:string,collation:string}> $columns
     * @return list<array{kind:string,table:string,index:string,row:int,expected:array<string,mixed>,actual:array<string,mixed>|null,collated_match:bool,byte_for_byte_match:bool,message:string}>
     */
    private static function indexRootSwapViolations(array $tableRows, array $swappedIndexRows, array $columns, string $table, string $index): array
    {
        $indexByRowId = [];
        foreach ($swappedIndexRows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('SQLite writable-schema index root swap rows must be arrays');
            }
            $indexByRowId[self::rowId($row)] = $row;
        }

        $violations = [];
        foreach ($tableRows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('SQLite writable-schema index root swap rows must be arrays');
            }

            $rowId = self::rowId($row);
            $actual = $indexByRowId[$rowId] ?? null;
            $collatedMatch = $actual !== null && self::indexKeyEqualByCollation($row, $actual, $columns);
            $byteMatch = $actual !== null && self::indexKeyByteEqual($row, $actual, $columns);

            if ($actual === null || !$collatedMatch) {
                $violations[] = [
                    'kind' => 'missing_index_entry',
                    'table' => $table,
                    'index' => $index,
                    'row' => $rowId,
                    'expected' => self::indexKey($row, $columns),
                    'actual' => $actual === null ? null : self::indexKey($actual, $columns),
                    'collated_match' => false,
                    'byte_for_byte_match' => false,
                    'message' => "row {$rowId} missing from index {$index}",
                ];
                continue;
            }

            if (!$byteMatch) {
                $violations[] = [
                    'kind' => 'index_value_mismatch',
                    'table' => $table,
                    'index' => $index,
                    'row' => $rowId,
                    'expected' => self::indexKey($row, $columns),
                    'actual' => self::indexKey($actual, $columns),
                    'collated_match' => true,
                    'byte_for_byte_match' => false,
                    'message' => "row {$rowId} values differ from index {$index}",
                ];
            }
        }

        return $violations;
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     * @param list<array{name:string,collation:string}> $columns
     */
    private static function indexKeyEqualByCollation(array $left, array $right, array $columns): bool
    {
        foreach ($columns as $column) {
            $name = $column['name'];
            if (!array_key_exists($name, $left) || !array_key_exists($name, $right)) {
                throw new InvalidArgumentException("SQLite writable-schema index root swap row is missing column {$name}");
            }
            if (!self::collationEqual($left[$name], $right[$name], $column['collation'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     * @param list<array{name:string,collation:string}> $columns
     */
    private static function indexKeyByteEqual(array $left, array $right, array $columns): bool
    {
        foreach ($columns as $column) {
            $name = $column['name'];
            if (!array_key_exists($name, $left) || !array_key_exists($name, $right)) {
                throw new InvalidArgumentException("SQLite writable-schema index root swap row is missing column {$name}");
            }
            if (get_debug_type($left[$name]) !== get_debug_type($right[$name]) || $left[$name] !== $right[$name]) {
                return false;
            }
        }

        return true;
    }

    private static function collationEqual(mixed $left, mixed $right, string $collation): bool
    {
        if (!is_string($left) || !is_string($right)) {
            return $left === $right;
        }

        return match ($collation) {
            'NOCASE' => strtolower($left) === strtolower($right),
            'RTRIM' => rtrim($left, " \t\r\n\0\x0B") === rtrim($right, " \t\r\n\0\x0B"),
            default => $left === $right,
        };
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowId(array $row): int
    {
        if (!array_key_exists('rowid', $row)) {
            throw new InvalidArgumentException('SQLite writable-schema index root swap rows require rowid');
        }

        $rowId = $row['rowid'];
        if (!is_int($rowId) || $rowId < 1) {
            throw new InvalidArgumentException('SQLite writable-schema index root swap rowid must be a positive integer');
        }

        return $rowId;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array{name:string,collation:string}> $columns
     * @return array<string,mixed>
     */
    private static function indexKey(array $row, array $columns): array
    {
        $key = [];
        foreach ($columns as $column) {
            $name = $column['name'];
            if (!array_key_exists($name, $row)) {
                throw new InvalidArgumentException("SQLite writable-schema index root swap row is missing column {$name}");
            }
            $key[$name] = $row[$name];
        }

        return $key;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function uniqueKey(array $row, array $columns): ?string
    {
        $parts = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row) || $row[$column] === null) {
                return null;
            }
            $parts[] = get_debug_type($row[$column]) . ':' . (string) $row[$column];
        }

        return implode('|', $parts);
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevelComma(string $body): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $length = strlen($body);
        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote) {
                    if ($quote === "'" && $i + 1 < $length && $body[$i + 1] === "'") {
                        $buffer .= $body[++$i];
                        continue;
                    }
                    if ($quote === '"' && $i + 1 < $length && $body[$i + 1] === '"') {
                        $buffer .= $body[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
                $buffer .= $char;
                continue;
            }
            if ($char === ')') {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }
            if ($char === ',' && $depth === 0) {
                $parts[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }

        return $parts;
    }

    private static function parseLiteral(string $literal): mixed
    {
        $literal = trim(rtrim($literal, ';'));
        if (preg_match('/^-?\d+$/', $literal)) {
            return (int) $literal;
        }
        if (preg_match('/^-?(?:\d+\.\d*|\d*\.\d+)$/', $literal)) {
            return (float) $literal;
        }
        if (strcasecmp($literal, 'NULL') === 0) {
            return null;
        }
        if (preg_match("/^'(.*)'$/s", $literal, $match)) {
            return str_replace("''", "'", $match[1]);
        }

        return $literal;
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new InvalidArgumentException('SQLite writable-schema integrity plan received an empty identifier');
        }
        $first = $identifier[0];
        $last = $identifier[strlen($identifier) - 1];
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`')) {
            return str_replace($first . $first, $first, substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return substr($identifier, 1, -1);
        }
        if ($first === "'" && $last === "'") {
            return str_replace("''", "'", substr($identifier, 1, -1));
        }

        return $identifier;
    }

    private static function identifierPattern(): string
    {
        return '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*)';
    }
}
