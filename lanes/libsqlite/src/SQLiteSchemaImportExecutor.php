<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSchemaImportExecutor
{
    /** @var array<string,list<SQLiteSchemaRecord>> */
    private array $records;

    /** @var array<string,int> */
    private array $nextRootPage;

    /** @var array<string,int> */
    private array $nextRowId;

    /**
     * @param array<string,list<SQLiteSchemaRecord>> $records
     */
    public function __construct(array $records = [], int $firstRootPage = 2)
    {
        $this->records = [];
        $this->nextRootPage = [];
        $this->nextRowId = [];

        foreach ($records + ['main' => [], 'temp' => []] as $schema => $schemaRecords) {
            $name = self::normalizeSchema($schema);
            $this->records[$name] = array_values($schemaRecords);
            $this->nextRootPage[$name] = max($firstRootPage, self::maxRootPage($schemaRecords) + 1);
            $this->nextRowId[$name] = max(1, self::maxRowId($schemaRecords) + 1);
        }
    }

    /**
     * @return array{status:string,statements:list<array<string,mixed>>,schemas:array<string,list<SQLiteSchemaRecord>>}
     */
    public function executeScript(string $sql, ?string $currentSchema = null): array
    {
        $results = [];
        foreach (self::splitStatements($sql) as $statement) {
            $results[] = $this->execute($statement, $currentSchema);
        }

        return [
            'status' => 'ok',
            'statements' => $results,
            'schemas' => $this->records,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function execute(string $sql, ?string $currentSchema = null): array
    {
        $statement = trim(rtrim(trim($sql), ';'));
        if ($statement === '') {
            return ['status' => 'skipped', 'operation' => 'empty'];
        }

        if (preg_match('/^create\s+(temp|temporary)\s+table\b/i', $statement) === 1) {
            return $this->createTable($statement, 'temp', true);
        }
        if (preg_match('/^create\s+table\b/i', $statement) === 1) {
            return $this->createTable($statement, $currentSchema, false);
        }
        if (preg_match('/^create\s+(unique\s+)?index\b/i', $statement) === 1) {
            return $this->createIndex($statement, $currentSchema, false);
        }
        if (preg_match('/^create\s+(temp|temporary)\s+(unique\s+)?index\b/i', $statement) === 1) {
            return $this->createIndex($statement, 'temp', true);
        }

        throw new \InvalidArgumentException('SQLite schema import executor only supports CREATE TABLE and CREATE INDEX statements');
    }

    /**
     * @return list<SQLiteSchemaRecord>
     */
    public function schemaRecords(string $schema = 'main'): array
    {
        return $this->records[self::normalizeSchema($schema)] ?? [];
    }

    public function catalog(): SQLiteAttachedSchemaCatalog
    {
        $attached = $this->records;
        $main = $attached['main'] ?? [];
        $temp = $attached['temp'] ?? [];
        unset($attached['main'], $attached['temp']);

        $catalog = new SQLiteAttachedSchemaCatalog($main, $temp);
        foreach ($attached as $schema => $records) {
            $catalog->attach($schema, $schema . '.sqlite', $records);
        }

        return $catalog;
    }

    /**
     * @return array{status:string,operation:string,schema:string,name:string,type:string,rootpage:int|null,autoindexes:list<string>,created:bool,sql:string}
     */
    private function createTable(string $sql, ?string $currentSchema, bool $temporary): array
    {
        if (!preg_match('/^create\s+(?:(?:temp|temporary)\s+)?table\s+(?<if>if\s+not\s+exists\s+)?(?<name>(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?:\s*\.\s*(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*))?)\s*\(/i', $sql, $matches, PREG_OFFSET_CAPTURE)) {
            throw new \InvalidArgumentException('SQLite schema import CREATE TABLE statement is not supported');
        }

        $qualified = self::qualifiedName($matches['name'][0], $temporary ? 'temp' : $currentSchema);
        $schema = $temporary ? 'temp' : $qualified['schema'];
        $name = $qualified['name'];
        if (str_starts_with(strtolower($name), 'sqlite_')) {
            throw new \InvalidArgumentException('SQLite schema import cannot create reserved sqlite_* objects');
        }
        $this->ensureSchema($schema);
        $ifNotExists = trim($matches['if'][0] ?? '') !== '';
        if ($this->findRecord($schema, 'table', $name) !== null) {
            if ($ifNotExists) {
                return [
                    'status' => 'ok',
                    'operation' => 'create_table',
                    'schema' => $schema,
                    'name' => $name,
                    'type' => 'table',
                    'rootpage' => $this->findRecord($schema, 'table', $name)?->rootPage,
                    'autoindexes' => [],
                    'created' => false,
                    'sql' => $sql,
                ];
            }
            throw new \InvalidArgumentException("SQLite schema import table {$name} already exists in {$schema}");
        }

        $open = (int) $matches[0][1] + strlen($matches[0][0]) - 1;
        $close = self::matchingParen($sql, $open);
        if ($close === null) {
            throw new \InvalidArgumentException('SQLite schema import CREATE TABLE column list is malformed');
        }
        $body = substr($sql, $open + 1, $close - $open - 1);
        $rootPage = $this->allocateRootPage($schema);
        $record = new SQLiteSchemaRecord('table', $name, $name, $rootPage, $sql, $this->allocateRowId($schema));
        $this->records[$schema][] = $record;

        $autoindexes = [];
        $autoindexCount = self::autoIndexCount($body);
        for ($i = 1; $i <= $autoindexCount; $i++) {
            $indexName = 'sqlite_autoindex_' . $name . '_' . $i;
            $autoindexes[] = $indexName;
            $this->records[$schema][] = new SQLiteSchemaRecord(
                'index',
                $indexName,
                $name,
                $this->allocateRootPage($schema),
                null,
                $this->allocateRowId($schema),
            );
        }

        return [
            'status' => 'ok',
            'operation' => 'create_table',
            'schema' => $schema,
            'name' => $name,
            'type' => 'table',
            'rootpage' => $rootPage,
            'autoindexes' => $autoindexes,
            'created' => true,
            'sql' => $sql,
        ];
    }

    /**
     * @return array{status:string,operation:string,schema:string,name:string,type:string,table:string,rootpage:int|null,unique:bool,created:bool,sql:string}
     */
    private function createIndex(string $sql, ?string $currentSchema, bool $temporary): array
    {
        if (!preg_match('/^create\s+(?:(?:temp|temporary)\s+)?(?<unique>unique\s+)?index\s+(?<if>if\s+not\s+exists\s+)?(?<name>(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?:\s*\.\s*(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*))?)\s+on\s+(?<table>(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?:\s*\.\s*(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*))?)\s*\(/i', $sql, $matches)) {
            throw new \InvalidArgumentException('SQLite schema import CREATE INDEX statement is not supported');
        }

        $index = self::qualifiedName($matches['name'], $temporary ? 'temp' : $currentSchema);
        $table = self::qualifiedName($matches['table'], $index['schema']);
        $schema = $temporary ? 'temp' : $index['schema'];
        if ($table['schema'] !== $schema) {
            throw new \InvalidArgumentException('SQLite schema import index and table schemas must match');
        }
        if (str_starts_with(strtolower($index['name']), 'sqlite_')) {
            throw new \InvalidArgumentException('SQLite schema import cannot create reserved sqlite_* objects');
        }
        $this->ensureSchema($schema);
        $ifNotExists = trim($matches['if'] ?? '') !== '';
        if ($this->findRecord($schema, 'index', $index['name']) !== null) {
            if ($ifNotExists) {
                return [
                    'status' => 'ok',
                    'operation' => 'create_index',
                    'schema' => $schema,
                    'name' => $index['name'],
                    'type' => 'index',
                    'table' => $table['name'],
                    'rootpage' => $this->findRecord($schema, 'index', $index['name'])?->rootPage,
                    'unique' => trim($matches['unique'] ?? '') !== '',
                    'created' => false,
                    'sql' => $sql,
                ];
            }
            throw new \InvalidArgumentException("SQLite schema import index {$index['name']} already exists in {$schema}");
        }
        if ($this->findRecord($schema, 'table', $table['name']) === null) {
            throw new \InvalidArgumentException("SQLite schema import index target table {$table['name']} is missing in {$schema}");
        }

        $rootPage = $this->allocateRootPage($schema);
        $this->records[$schema][] = new SQLiteSchemaRecord(
            'index',
            $index['name'],
            $table['name'],
            $rootPage,
            $sql,
            $this->allocateRowId($schema),
        );

        return [
            'status' => 'ok',
            'operation' => 'create_index',
            'schema' => $schema,
            'name' => $index['name'],
            'type' => 'index',
            'table' => $table['name'],
            'rootpage' => $rootPage,
            'unique' => trim($matches['unique'] ?? '') !== '',
            'created' => true,
            'sql' => $sql,
        ];
    }

    /**
     * @return list<string>
     */
    private static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $buffer .= $char;
            if ($quote !== null) {
                if ($char === $quote) {
                    if (($sql[$i + 1] ?? null) === $quote) {
                        $buffer .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
                continue;
            }
            if ($char === ';') {
                $statement = trim(rtrim($buffer, ';'));
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }
        if ($quote !== null) {
            throw new \InvalidArgumentException('SQLite schema import SQL has unterminated string literal');
        }
        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    /**
     * @return array{schema:string,name:string}
     */
    private static function qualifiedName(string $sql, ?string $defaultSchema): array
    {
        $parts = self::splitQualifiedName($sql);
        if (count($parts) === 1) {
            return ['schema' => self::normalizeSchema($defaultSchema ?? 'main'), 'name' => $parts[0]];
        }
        if (count($parts) === 2) {
            return ['schema' => self::normalizeSchema($parts[0]), 'name' => $parts[1]];
        }

        throw new \InvalidArgumentException('SQLite schema import object name has too many qualifiers');
    }

    /**
     * @return list<string>
     */
    private static function splitQualifiedName(string $sql): array
    {
        $parts = [];
        foreach (preg_split('/\s*\.\s*/', trim($sql)) ?: [] as $part) {
            $parts[] = self::unquoteIdentifier($part);
        }

        return $parts;
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new \InvalidArgumentException('SQLite schema import object name cannot be empty');
        }
        $first = $identifier[0];
        $last = $identifier[strlen($identifier) - 1];
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`')) {
            return str_replace($first . $first, $first, substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }

    private static function normalizeSchema(string $schema): string
    {
        $schema = strtolower(trim($schema));
        if ($schema === '') {
            throw new \InvalidArgumentException('SQLite schema import schema name cannot be empty');
        }

        return $schema;
    }

    private function ensureSchema(string $schema): void
    {
        if (!isset($this->records[$schema])) {
            $this->records[$schema] = [];
            $this->nextRootPage[$schema] = 2;
            $this->nextRowId[$schema] = 1;
        }
    }

    private function allocateRootPage(string $schema): int
    {
        return $this->nextRootPage[$schema]++;
    }

    private function allocateRowId(string $schema): int
    {
        return $this->nextRowId[$schema]++;
    }

    private function findRecord(string $schema, string $type, string $name): ?SQLiteSchemaRecord
    {
        foreach ($this->records[$schema] ?? [] as $record) {
            if ($record->type === $type && strcasecmp($record->name, $name) === 0) {
                return $record;
            }
        }

        return null;
    }

    private static function autoIndexCount(string $body): int
    {
        $count = 0;
        foreach (self::splitCommaTerms($body) as $term) {
            $lower = strtolower($term);
            if (preg_match('/^\s*(constraint\s+(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s+)?(primary\s+key|unique)\b/', $lower) === 1) {
                $count++;
                continue;
            }
            if (preg_match('/\b(unique|primary\s+key)\b/', $lower) === 1 && preg_match('/^\s*(constraint\b|check\b|foreign\b)/', $lower) !== 1) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private static function splitCommaTerms(string $sql): array
    {
        $terms = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote) {
                    if (($sql[$i + 1] ?? null) === $quote) {
                        $buffer .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
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
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $terms[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $term = trim($buffer);
        if ($term !== '') {
            $terms[] = $term;
        }

        return $terms;
    }

    private static function matchingParen(string $sql, int $open): ?int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = $open; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if (($sql[$i + 1] ?? null) === $quote) {
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function maxRootPage(array $records): int
    {
        $max = 0;
        foreach ($records as $record) {
            $max = max($max, $record->rootPage ?? 0);
        }

        return $max;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function maxRowId(array $records): int
    {
        $max = 0;
        foreach ($records as $record) {
            $max = max($max, $record->rowId);
        }

        return $max;
    }
}
