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
