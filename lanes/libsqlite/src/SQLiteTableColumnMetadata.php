<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

/**
 * Bounded native equivalent for upstream sqlite3_table_column_metadata() cases.
 */
final class SQLiteTableColumnMetadata
{
    /** @var list<SQLiteSchemaRecord> */
    private array $records;

    /** @param list<SQLiteSchemaRecord> $records */
    public function __construct(array $records)
    {
        $this->records = array_values($records);
    }

    /** @param list<SQLiteSchemaRecord> $records */
    public static function fromRecords(array $records): self
    {
        return new self($records);
    }

    /**
     * @return array{
     *     status:string,
     *     schema:string,
     *     table:string,
     *     column:string|null,
     *     declared_type?:string,
     *     collation?:string,
     *     not_null?:int,
     *     primary_key?:int,
     *     auto_increment?:int,
     *     source?:string,
     *     exists?:bool,
     *     message?:string
     * }
     */
    public function lookup(?string $schema, string $tableName, ?string $columnName = null): array
    {
        $schemaName = self::schemaName($schema);
        $record = $schemaName === 'main' ? $this->findObject($tableName) : null;
        if ($record === null) {
            return [
                'status' => 'error',
                'schema' => $schemaName,
                'table' => $tableName,
                'column' => $columnName,
                'message' => "no such table: {$tableName}",
            ];
        }

        if ($columnName === null) {
            return [
                'status' => 'ok',
                'schema' => $schemaName,
                'table' => $record->name,
                'column' => null,
                'exists' => true,
            ];
        }

        if ($record->type !== 'table' || $record->sql === null) {
            return self::columnError($schemaName, $record->name, $columnName);
        }

        $columns = self::columnsFromCreateTable($record->sql);
        foreach ($columns as $column) {
            if (strcasecmp($column['name'], $columnName) !== 0) {
                continue;
            }

            return [
                'status' => 'ok',
                'schema' => $schemaName,
                'table' => $record->name,
                'column' => $column['name'],
                'declared_type' => $column['type'],
                'collation' => $column['collation'],
                'not_null' => $column['notNull'] ? 1 : 0,
                'primary_key' => $column['primaryKey'] ? 1 : 0,
                'auto_increment' => $column['autoIncrement'] ? 1 : 0,
                'source' => 'explicit_column',
            ];
        }

        if (
            self::isRowidAlias($columnName)
            && !self::isWithoutRowidSql($record->sql)
            && !self::hasExplicitRowidAlias($columns)
        ) {
            return [
                'status' => 'ok',
                'schema' => $schemaName,
                'table' => $record->name,
                'column' => $columnName,
                'declared_type' => 'INTEGER',
                'collation' => 'BINARY',
                'not_null' => 0,
                'primary_key' => 1,
                'auto_increment' => self::hasAutoIncrementRowid($columns) ? 1 : 0,
                'source' => 'implicit_rowid',
            ];
        }

        return self::columnError($schemaName, $record->name, $columnName);
    }

    private static function schemaName(?string $schema): string
    {
        $schema = trim((string) $schema);

        return $schema === '' ? 'main' : $schema;
    }

    private function findObject(string $tableName): ?SQLiteSchemaRecord
    {
        foreach ($this->records as $record) {
            if (($record->type === 'table' || $record->type === 'view') && strcasecmp($record->name, $tableName) === 0) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @return array{status:string,schema:string,table:string,column:string,message:string}
     */
    private static function columnError(string $schemaName, string $tableName, string $columnName): array
    {
        return [
            'status' => 'error',
            'schema' => $schemaName,
            'table' => $tableName,
            'column' => $columnName,
            'message' => "no such table column: {$tableName}.{$columnName}",
        ];
    }

    /**
     * @return list<array{name:string,type:string,collation:string,notNull:bool,primaryKey:bool,autoIncrement:bool,rowidAlias:bool}>
     */
    private static function columnsFromCreateTable(string $sql): array
    {
        $body = self::parenthesizedBody($sql);
        if ($body === null) {
            return [];
        }

        $withoutRowid = self::isWithoutRowidSql($sql);
        $tablePrimaryKeys = [];
        $columns = [];
        foreach (self::splitTopLevel($body, ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }

            $constraint = self::stripLeadingConstraint($definition);
            if (self::startsWithKeyword($constraint, 'PRIMARY')) {
                $list = self::parenthesizedBody($constraint);
                $tablePrimaryKeys = $list === null ? [] : self::tablePrimaryKeyColumns($list);
                continue;
            }
            if (
                self::startsWithKeyword($constraint, 'UNIQUE')
                || self::startsWithKeyword($constraint, 'CHECK')
                || self::startsWithKeyword($constraint, 'FOREIGN')
            ) {
                continue;
            }

            $identifier = self::readIdentifier($definition, 0);
            if ($identifier === null) {
                continue;
            }

            $name = $identifier['identifier'];
            $tail = ltrim(substr($definition, $identifier['end']));
            $type = self::declaredType($tail);
            $primaryKey = self::containsTopLevelKeyword($tail, 'PRIMARY KEY');
            $autoIncrement = $primaryKey
                && strcasecmp($type, 'INTEGER') === 0
                && self::containsTopLevelKeyword($tail, 'AUTOINCREMENT');

            $columns[] = [
                'name' => $name,
                'type' => $type,
                'collation' => self::declaredCollation($tail),
                'notNull' => self::containsTopLevelKeyword($tail, 'NOT NULL'),
                'primaryKey' => $primaryKey,
                'autoIncrement' => $autoIncrement,
                'rowidAlias' => $primaryKey && strcasecmp($type, 'INTEGER') === 0 && !$withoutRowid,
            ];
        }

        if ($tablePrimaryKeys !== []) {
            foreach ($columns as &$column) {
                $primaryKey = isset($tablePrimaryKeys[strtolower($column['name'])]);
                $column['primaryKey'] = $primaryKey;
                if ($withoutRowid && $primaryKey) {
                    $column['notNull'] = true;
                }
            }
            unset($column);
        }

        return $columns;
    }

    /**
     * @param list<array{name:string,type:string,collation:string,notNull:bool,primaryKey:bool,autoIncrement:bool,rowidAlias:bool}> $columns
     */
    private static function hasExplicitRowidAlias(array $columns): bool
    {
        foreach ($columns as $column) {
            if (self::isRowidAlias($column['name'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{name:string,type:string,collation:string,notNull:bool,primaryKey:bool,autoIncrement:bool,rowidAlias:bool}> $columns
     */
    private static function hasAutoIncrementRowid(array $columns): bool
    {
        foreach ($columns as $column) {
            if ($column['rowidAlias'] && $column['autoIncrement']) {
                return true;
            }
        }

        return false;
    }

    private static function isRowidAlias(string $columnName): bool
    {
        return in_array(strtolower($columnName), ['rowid', 'oid', '_rowid_'], true);
    }

    private static function isWithoutRowidSql(string $sql): bool
    {
        return preg_match('/\)\s*(?:STRICT\s*,\s*)?WITHOUT\s+ROWID\b/i', $sql) === 1
            || preg_match('/\)\s*WITHOUT\s+ROWID\s*,\s*STRICT\b/i', $sql) === 1;
    }

    private static function parenthesizedBody(string $sql): ?string
    {
        $open = strpos($sql, '(');
        if ($open === false) {
            return null;
        }

        $close = self::matchingParen($sql, $open);

        return $close === null ? null : substr($sql, $open + 1, $close - $open - 1);
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $text, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($text, $i, $char);
                continue;
            }
            if ($char === '[') {
                $i = self::skipBracketQuoted($text, $i);
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')' && $depth > 0) {
                $depth--;
                continue;
            }
            if ($char === $delimiter && $depth === 0) {
                $parts[] = substr($text, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($text, $start);

        return $parts;
    }

    /**
     * @return array{identifier:string,end:int}|null
     */
    private static function readIdentifier(string $text, int $offset): ?array
    {
        while (isset($text[$offset]) && ctype_space($text[$offset])) {
            $offset++;
        }
        if (!isset($text[$offset])) {
            return null;
        }
        if ($text[$offset] === '"' || $text[$offset] === '`' || $text[$offset] === "'") {
            $end = self::skipQuoted($text, $offset, $text[$offset]);

            return [
                'identifier' => self::unquoteIdentifier(substr($text, $offset, $end - $offset + 1)),
                'end' => $end + 1,
            ];
        }
        if ($text[$offset] === '[') {
            $end = self::skipBracketQuoted($text, $offset);

            return [
                'identifier' => self::unquoteIdentifier(substr($text, $offset, $end - $offset + 1)),
                'end' => $end + 1,
            ];
        }
        if (!preg_match('/\G([A-Za-z_][A-Za-z0-9_]*)/A', $text, $matches, 0, $offset)) {
            return null;
        }

        return [
            'identifier' => $matches[1],
            'end' => $offset + strlen($matches[1]),
        ];
    }

    private static function declaredType(string $tail): string
    {
        $words = [];
        foreach (preg_split('/\s+/', trim($tail)) ?: [] as $word) {
            $upper = strtoupper(trim($word));
            if (
                $upper === ''
                || in_array($upper, ['CONSTRAINT', 'PRIMARY', 'NOT', 'NULL', 'UNIQUE', 'CHECK', 'DEFAULT', 'COLLATE', 'REFERENCES', 'GENERATED', 'ALWAYS', 'AS'], true)
            ) {
                break;
            }
            $words[] = strtoupper(self::unquoteIdentifier(trim($word)));
        }

        return implode(' ', $words);
    }

    private static function declaredCollation(string $tail): string
    {
        $offset = self::findTopLevelKeyword($tail, 'COLLATE');
        if ($offset === null) {
            return 'BINARY';
        }

        $identifier = self::readIdentifier($tail, $offset + strlen('COLLATE'));

        return $identifier === null ? 'BINARY' : $identifier['identifier'];
    }

    /**
     * @return array<string, true>
     */
    private static function tablePrimaryKeyColumns(string $list): array
    {
        $columns = [];
        foreach (self::splitTopLevel($list, ',') as $part) {
            $identifier = self::readIdentifier(trim($part), 0);
            if ($identifier !== null) {
                $columns[strtolower($identifier['identifier'])] = true;
            }
        }

        return $columns;
    }

    private static function stripLeadingConstraint(string $definition): string
    {
        $trimmed = ltrim($definition);
        if (!self::startsWithKeyword($trimmed, 'CONSTRAINT')) {
            return $trimmed;
        }

        $identifier = self::readIdentifier($trimmed, strlen('CONSTRAINT'));

        return $identifier === null ? $trimmed : ltrim(substr($trimmed, $identifier['end']));
    }

    private static function startsWithKeyword(string $text, string $keyword): bool
    {
        $text = ltrim($text);
        $length = strlen($keyword);
        if (strncasecmp($text, $keyword, $length) !== 0) {
            return false;
        }

        return strlen($text) === $length || !self::isIdentifierChar($text[$length]);
    }

    private static function containsTopLevelKeyword(string $text, string $keyword): bool
    {
        return self::findTopLevelKeyword($text, $keyword) !== null;
    }

    private static function findTopLevelKeyword(string $text, string $keyword): ?int
    {
        $depth = 0;
        $length = strlen($text);
        $keywordLength = strlen($keyword);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($text, $i, $char);
                continue;
            }
            if ($char === '[') {
                $i = self::skipBracketQuoted($text, $i);
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')' && $depth > 0) {
                $depth--;
                continue;
            }
            if ($depth === 0 && strncasecmp(substr($text, $i, $keywordLength), $keyword, $keywordLength) === 0) {
                $before = $i === 0 ? '' : $text[$i - 1];
                $after = $text[$i + $keywordLength] ?? '';
                if (($before === '' || !self::isIdentifierChar($before)) && ($after === '' || !self::isIdentifierChar($after))) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function matchingParen(string $text, int $open): ?int
    {
        $depth = 0;
        $length = strlen($text);
        for ($i = $open; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($text, $i, $char);
                continue;
            }
            if ($char === '[') {
                $i = self::skipBracketQuoted($text, $i);
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function skipQuoted(string $text, int $offset, string $quote): int
    {
        $length = strlen($text);
        for ($i = $offset + 1; $i < $length; $i++) {
            if ($text[$i] !== $quote) {
                continue;
            }
            if (isset($text[$i + 1]) && $text[$i + 1] === $quote) {
                $i++;
                continue;
            }

            return $i;
        }

        return $length - 1;
    }

    private static function skipBracketQuoted(string $text, int $offset): int
    {
        $end = strpos($text, ']', $offset + 1);

        return $end === false ? strlen($text) - 1 : $end;
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return $identifier;
        }

        $first = $identifier[0];
        $last = $identifier[strlen($identifier) - 1];
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`') || ($first === "'" && $last === "'")) {
            return str_replace($first . $first, $first, substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }

    private static function isIdentifierChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_';
    }
}
