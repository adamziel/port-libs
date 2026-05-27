<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaSchemaCatalog
{
    /** @var array<string, SQLiteSchemaRecord> */
    private array $tables = [];

    /** @var array<string, list<SQLiteSchemaRecord>> */
    private array $indexesByTable = [];

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    public function __construct(private readonly array $records)
    {
        foreach ($records as $record) {
            if ($record->type === 'table') {
                $this->tables[strtolower($record->name)] = $record;
                continue;
            }

            if ($record->type === 'index') {
                $this->indexesByTable[strtolower($record->tableName)][] = $record;
            }
        }

        foreach ($this->indexesByTable as &$indexes) {
            usort($indexes, static fn (SQLiteSchemaRecord $a, SQLiteSchemaRecord $b): int => $a->rowId <=> $b->rowId);
        }
    }

    public static function fromDatabase(SQLiteDatabase $database): self
    {
        return new self($database->schemaRecords());
    }

    /**
     * @return array{status: string, pragma: string, schema: string, target: string, rows: list<array<string, int|string|null>>}
     */
    public function execute(string $sql): array
    {
        $parsed = self::parsePragma($sql);

        return [
            'status' => 'ok',
            'pragma' => $parsed['pragma'],
            'schema' => $parsed['schema'] ?? 'main',
            'target' => $parsed['target'],
            'rows' => match ($parsed['pragma']) {
                'table_info' => $this->tableInfo($parsed['target'], false),
                'table_xinfo' => $this->tableInfo($parsed['target'], true),
                'index_list' => $this->indexList($parsed['target']),
                'index_info' => $this->indexInfo($parsed['target']),
            },
        ];
    }

    /**
     * @return list<array{cid: int, name: string, type: string, notnull: int, dflt_value: string|null, pk: int}|array{cid: int, name: string, type: string, notnull: int, dflt_value: string|null, pk: int, hidden: int}>
     */
    public function tableInfo(string $tableName, bool $includeHidden = false): array
    {
        $record = $this->tables[strtolower($tableName)] ?? null;
        if ($record === null || $record->sql === null) {
            return [];
        }

        $columns = self::columnsFromCreateTable($record->sql);
        $rows = [];
        $pkOrdinal = 0;
        foreach ($columns as $cid => $column) {
            if ($column['hidden'] !== 0 && !$includeHidden) {
                continue;
            }

            $pk = $column['primaryKey'] ? ++$pkOrdinal : 0;
            $row = [
                'cid' => $cid,
                'name' => $column['name'],
                'type' => $column['type'],
                'notnull' => $column['notNull'] ? 1 : 0,
                'dflt_value' => $column['default'],
                'pk' => $pk,
            ];
            if ($includeHidden) {
                $row['hidden'] = $column['hidden'];
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array{seq: int, name: string, unique: int, origin: string, partial: int}>
     */
    public function indexList(string $tableName): array
    {
        $rows = [];
        foreach ($this->indexesByTable[strtolower($tableName)] ?? [] as $seq => $record) {
            $origin = str_starts_with($record->name, 'sqlite_autoindex_') ? 'u' : 'c';
            $rows[] = [
                'seq' => $seq,
                'name' => $record->name,
                'unique' => $origin === 'u' || ($record->sql !== null && self::createIndexIsUnique($record->sql)) ? 1 : 0,
                'origin' => $origin,
                'partial' => $record->sql !== null && self::hasTopLevelWhere($record->sql) ? 1 : 0,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{seqno: int, cid: int, name: string}>
     */
    public function indexInfo(string $indexName): array
    {
        $index = null;
        foreach ($this->records as $record) {
            if ($record->type === 'index' && strcasecmp($record->name, $indexName) === 0) {
                $index = $record;
                break;
            }
        }

        if ($index === null) {
            return [];
        }

        $columns = $index->sql === null
            ? $this->autoIndexColumns($index)
            : self::columnsFromCreateIndex($index->sql);
        $tableColumns = $this->tableColumnNames($index->tableName);
        $rows = [];
        foreach ($columns as $seqno => $columnName) {
            $cid = array_search(strtolower($columnName), $tableColumns, true);
            $rows[] = [
                'seqno' => $seqno,
                'cid' => $cid === false ? -2 : $cid,
                'name' => $columnName,
            ];
        }

        return $rows;
    }

    /**
     * @return array{pragma: 'table_info'|'table_xinfo'|'index_list'|'index_info', schema: string|null, target: string}
     */
    private static function parsePragma(string $sql): array
    {
        $trimmed = rtrim(trim($sql), ';');
        if (!preg_match('/^pragma\s+(?:(?<schema>[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?(?<pragma>table_info|table_xinfo|index_list|index_info)\s*(?:\(\s*(?<paren>(?:\"(?:\"\"|[^\"])+\"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*))\s*\)|=\s*(?<equals>(?:\"(?:\"\"|[^\"])+\"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*)))$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Only PRAGMA table_info, table_xinfo, index_list, and index_info are supported');
        }

        return [
            'pragma' => strtolower($matches['pragma']),
            'schema' => isset($matches['schema']) && $matches['schema'] !== '' ? strtolower($matches['schema']) : null,
            'target' => self::unquoteIdentifier($matches['paren'] !== '' ? $matches['paren'] : $matches['equals']),
        ];
    }

    /**
     * @return list<array{name: string, type: string, notNull: bool, default: string|null, primaryKey: bool, hidden: int}>
     */
    private static function columnsFromCreateTable(string $sql): array
    {
        $body = self::parenthesizedBody($sql);
        if ($body === null) {
            return [];
        }

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
                $tablePrimaryKeys = $list === null ? [] : array_map(
                    static fn (string $part): string => strtolower(self::unquoteIdentifier(strtok(trim($part), " \t\r\n") ?: '')),
                    self::splitTopLevel($list, ','),
                );
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
            $columns[] = [
                'name' => $name,
                'type' => $type,
                'notNull' => self::containsTopLevelKeyword($tail, 'NOT NULL') || self::containsTopLevelKeyword($tail, 'PRIMARY KEY'),
                'default' => self::defaultValue($tail),
                'primaryKey' => self::containsTopLevelKeyword($tail, 'PRIMARY KEY'),
                'hidden' => self::generatedHiddenCode($tail),
            ];
        }

        if ($tablePrimaryKeys !== []) {
            foreach ($columns as &$column) {
                $column['primaryKey'] = in_array(strtolower($column['name']), $tablePrimaryKeys, true);
                if ($column['primaryKey']) {
                    $column['notNull'] = true;
                }
            }
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    private static function columnsFromCreateIndex(string $sql): array
    {
        $body = self::parenthesizedBody($sql);
        if ($body === null) {
            return [];
        }

        $columns = [];
        foreach (self::splitTopLevel($body, ',') as $term) {
            $identifier = self::readIdentifier(trim($term), 0);
            $columns[] = $identifier === null ? trim($term) : $identifier['identifier'];
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    private function autoIndexColumns(SQLiteSchemaRecord $index): array
    {
        $table = $this->tables[strtolower($index->tableName)] ?? null;
        if ($table === null || $table->sql === null) {
            return [];
        }

        $autoIndexes = SQLiteCreateTable::automaticIndexColumnMetadata($table->sql);
        $autoIndexOffset = 0;
        foreach ($this->indexesByTable[strtolower($index->tableName)] ?? [] as $candidate) {
            if (!str_starts_with($candidate->name, 'sqlite_autoindex_')) {
                continue;
            }
            if (strcasecmp($candidate->name, $index->name) === 0) {
                return array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $autoIndexes[$autoIndexOffset] ?? []);
            }
            $autoIndexOffset++;
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function tableColumnNames(string $tableName): array
    {
        return array_map(
            static fn (array $column): string => strtolower($column['name']),
            self::columnsFromCreateTable($this->tables[strtolower($tableName)]->sql ?? ''),
        );
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
     * @return array{identifier: string, end: int}|null
     */
    private static function readIdentifier(string $text, int $offset): ?array
    {
        while (isset($text[$offset]) && ctype_space($text[$offset])) {
            $offset++;
        }
        if (!isset($text[$offset])) {
            return null;
        }
        if ($text[$offset] === '"' || $text[$offset] === '`') {
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
            if ($upper === '' || in_array($upper, ['PRIMARY', 'NOT', 'NULL', 'UNIQUE', 'CHECK', 'DEFAULT', 'COLLATE', 'REFERENCES', 'GENERATED', 'AS'], true)) {
                break;
            }
            $words[] = trim($word);
        }

        return implode(' ', $words);
    }

    private static function defaultValue(string $tail): ?string
    {
        $offset = self::findTopLevelKeyword($tail, 'DEFAULT');
        if ($offset === null) {
            return null;
        }
        $value = ltrim(substr($tail, $offset + strlen('DEFAULT')));
        $end = strlen($value);
        foreach ([' COLLATE ', ' NOT ', ' NULL ', ' PRIMARY ', ' UNIQUE ', ' CHECK ', ' REFERENCES ', ' GENERATED '] as $keyword) {
            $found = stripos($value, $keyword);
            if ($found !== false) {
                $end = min($end, $found);
            }
        }

        return trim(substr($value, 0, $end));
    }

    private static function generatedHiddenCode(string $tail): int
    {
        if (!self::containsTopLevelKeyword($tail, 'GENERATED')) {
            return 0;
        }
        if (self::containsTopLevelKeyword($tail, 'STORED')) {
            return 3;
        }

        return 2;
    }

    private static function createIndexIsUnique(string $sql): bool
    {
        return preg_match('/^\s*CREATE\s+UNIQUE\s+INDEX\b/i', $sql) === 1;
    }

    private static function hasTopLevelWhere(string $sql): bool
    {
        return self::findTopLevelKeyword($sql, 'WHERE') !== null;
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
