<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaSchemaCatalog
{
    private const DEFAULT_FUNCTIONS = [
        ['name' => 'abs', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
        ['name' => 'coalesce', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 2099200],
        ['name' => 'count', 'builtin' => 1, 'type' => 'w', 'enc' => 'utf8', 'narg' => 0, 'flags' => 2097152],
        ['name' => 'count', 'builtin' => 1, 'type' => 'w', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2097152],
        ['name' => 'glob', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 2, 'flags' => 2099200],
        ['name' => 'json_extract', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 2099200],
        ['name' => 'json_group_array', 'builtin' => 1, 'type' => 'w', 'enc' => 'utf8', 'narg' => 1, 'flags' => 3147776],
        ['name' => 'json_group_object', 'builtin' => 1, 'type' => 'w', 'enc' => 'utf8', 'narg' => 2, 'flags' => 3147776],
        ['name' => 'like', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 2, 'flags' => 2099200],
        ['name' => 'like', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 3, 'flags' => 2099200],
        ['name' => 'lower', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
        ['name' => 'max', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 2099200],
        ['name' => 'min', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 2099200],
        ['name' => 'printf', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 2099200],
        ['name' => 'sum', 'builtin' => 1, 'type' => 'w', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2097152],
        ['name' => 'total', 'builtin' => 1, 'type' => 'w', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2097152],
        ['name' => 'upper', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
    ];

    private const DEFAULT_MODULES = [
        ['name' => 'json_each'],
        ['name' => 'json_tree'],
        ['name' => 'fts5'],
        ['name' => 'rtree'],
    ];

    private const DEFAULT_COLLATIONS = [
        ['seq' => 0, 'name' => 'BINARY'],
        ['seq' => 1, 'name' => 'NOCASE'],
        ['seq' => 2, 'name' => 'RTRIM'],
    ];

    /** @var array<string, SQLiteSchemaRecord> */
    private array $tables = [];

    /** @var array<string, list<SQLiteSchemaRecord>> */
    private array $indexesByTable = [];

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array{name:string,builtin?:int,type?:string,enc?:string,narg?:int,flags?:int}> $functions
     * @param list<array{name:string}> $modules
     * @param list<array{name:string,seq?:int}> $collations
     */
    public function __construct(
        private readonly array $records,
        private readonly array $functions = self::DEFAULT_FUNCTIONS,
        private readonly array $modules = self::DEFAULT_MODULES,
        private readonly array $collations = self::DEFAULT_COLLATIONS,
    ) {
        foreach ($records as $record) {
            if ($record->type === 'table' || $record->type === 'view') {
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
                'index_xinfo' => $this->indexXInfo($parsed['target']),
                'foreign_key_list' => $this->foreignKeyList($parsed['target']),
                'table_list' => $this->tableList($parsed['schema'] ?? 'main', $parsed['target'] === '' ? null : $parsed['target']),
                'function_list' => $this->functionList(),
                'module_list' => $this->moduleList(),
                'collation_list' => $this->collationList(),
                'pragma_list' => $this->pragmaList(),
            },
        ];
    }

    public function executeCursor(string $sql): SQLitePragmaRowCursor
    {
        return new SQLitePragmaRowCursor($this->execute($sql));
    }

    /**
     * @return array{status: string, pragma: 'table_info'|'table_xinfo'|'index_list'|'index_info'|'index_xinfo'|'foreign_key_list', schema: string, target: string, rows: list<array<string, int|string|null>>}
     */
    public function executeTableValuedPragma(string $sql): array
    {
        $parsed = self::parseTableValuedPragma($sql);

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
                'index_xinfo' => $this->indexXInfo($parsed['target']),
                'foreign_key_list' => $this->foreignKeyList($parsed['target']),
                'table_list' => $this->tableList($parsed['schema'] ?? 'main', $parsed['target'] === '' ? null : $parsed['target']),
                'function_list' => $this->functionList(),
                'module_list' => $this->moduleList(),
                'collation_list' => $this->collationList(),
                'pragma_list' => $this->pragmaList(),
            },
        ];
    }

    public function executeTableValuedPragmaCursor(string $sql): SQLitePragmaRowCursor
    {
        return new SQLitePragmaRowCursor($this->executeTableValuedPragma($sql));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function executeVirtualTableSelect(string $sql): array
    {
        return SQLiteSelectSql::execute($sql, $this->virtualPragmaTables());
    }

    /**
     * @return array<string, list<array<string, int|string|null>>>
     */
    public function virtualPragmaTables(): array
    {
        return [
            'pragma_function_list' => $this->functionList(),
            'pragma_module_list' => $this->moduleList(),
            'pragma_collation_list' => $this->collationList(),
            'pragma_pragma_list' => $this->pragmaList(),
        ];
    }

    /**
     * @return list<SQLiteSchemaRecord>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * @return list<array{cid: int, name: string, type: string, notnull: int, dflt_value: string|null, pk: int}|array{cid: int, name: string, type: string, notnull: int, dflt_value: string|null, pk: int, hidden: int}>
     */
    public function tableInfo(string $tableName, bool $includeHidden = false): array
    {
        $pragmaVirtualTable = self::pragmaVirtualTableColumns($tableName);
        if ($pragmaVirtualTable !== null) {
            return self::columnsToPragmaTableInfo($pragmaVirtualTable, $includeHidden);
        }

        $record = $this->tables[strtolower($tableName)] ?? null;
        if ($record === null || $record->sql === null) {
            return [];
        }

        $columns = $record->type === 'view'
            ? $this->columnsFromCreateView($record->sql)
            : self::columnsFromCreateTable($record->sql);
        $rows = [];
        foreach ($columns as $cid => $column) {
            if ($column['hidden'] !== 0 && !$includeHidden) {
                continue;
            }

            $row = [
                'cid' => $cid,
                'name' => $column['name'],
                'type' => $column['type'],
                'notnull' => $column['notNull'] ? 1 : 0,
                'dflt_value' => $column['default'],
                'pk' => $column['primaryKey'],
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
            $origin = $this->indexOrigin($record);
            $rows[] = [
                'seq' => $seq,
                'name' => $record->name,
                'unique' => $origin === 'u' || $origin === 'pk' || ($record->sql !== null && self::createIndexIsUnique($record->sql)) ? 1 : 0,
                'origin' => $origin,
                'partial' => $record->sql !== null && self::hasTopLevelWhere($record->sql) ? 1 : 0,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{seqno: int, cid: int, name: string|null}>
     */
    public function indexInfo(string $indexName): array
    {
        $index = $this->findIndex($indexName);
        if ($index === null) {
            return [];
        }

        $tableColumns = $this->tableColumnNames($index->tableName);
        $terms = $index->sql === null
            ? $this->autoIndexColumnTerms($index)
            : self::indexTermsFromCreateIndex($index->sql);

        $rows = [];
        foreach ($terms as $seqno => $term) {
            $columnName = $term['expression'] ? null : $term['name'];
            $cid = $columnName === null ? -2 : array_search(strtolower($columnName), $tableColumns, true);
            $rows[] = [
                'seqno' => $seqno,
                'cid' => $cid === false ? -2 : $cid,
                'name' => $columnName,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{seqno: int, cid: int, name: string|null, desc: int, coll: string, key: int}>
     */
    public function indexXInfo(string $indexName): array
    {
        $index = $this->findIndex($indexName);
        if ($index === null) {
            return [];
        }

        $tableColumns = $this->tableColumns($index->tableName);
        $tableColumnNames = array_map(static fn (array $column): string => strtolower($column['name']), $tableColumns);
        $terms = $index->sql === null
            ? $this->autoIndexColumnTerms($index)
            : self::indexTermsFromCreateIndex($index->sql);

        $rows = [];
        foreach ($terms as $seqno => $term) {
            $columnName = $term['expression'] ? null : $term['name'];
            $cid = $columnName === null ? -2 : array_search(strtolower($columnName), $tableColumnNames, true);
            $rows[] = [
                'seqno' => $seqno,
                'cid' => $cid === false ? -2 : $cid,
                'name' => $columnName,
                'desc' => $term['descending'] ? 1 : 0,
                'coll' => $term['collation'],
                'key' => 1,
            ];
        }

        foreach ($this->auxiliaryIndexTerms($index, $terms, $tableColumns) as $term) {
            $rows[] = [
                'seqno' => count($rows),
                'cid' => $term['cid'],
                'name' => $term['name'],
                'desc' => 0,
                'coll' => 'BINARY',
                'key' => 0,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, seq: int, table: string, from: string, to: string|null, on_update: string, on_delete: string, match: string}>
     */
    public function foreignKeyList(string $tableName): array
    {
        $record = $this->tables[strtolower($tableName)] ?? null;
        if ($record === null || $record->sql === null) {
            return [];
        }

        $body = self::parenthesizedBody($record->sql);
        if ($body === null) {
            return [];
        }

        $rows = [];
        $id = 0;
        foreach (self::splitTopLevel($body, ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }

            $constraint = self::stripLeadingConstraint($definition);
            if (self::startsWithKeyword($constraint, 'FOREIGN')) {
                $foreignKey = self::foreignKeyFromTableConstraint($constraint, $id);
                if ($foreignKey !== []) {
                    array_push($rows, ...$foreignKey);
                    $id++;
                }
                continue;
            }

            $identifier = self::readIdentifier($definition, 0);
            if ($identifier === null) {
                continue;
            }
            $columnForeignKey = self::foreignKeyFromColumnConstraint($identifier['identifier'], substr($definition, $identifier['end']), $id);
            if ($columnForeignKey !== null) {
                $rows[] = $columnForeignKey;
                $id++;
            }
        }

        return $rows;
    }

    /**
     * @return list<array{schema: string, name: string, type: string, ncol: int, wr: int, strict: int}>
     */
    public function tableList(string $schemaName = 'main', ?string $target = null): array
    {
        $rows = [];
        foreach ($this->records as $record) {
            if ($record->type !== 'table' && $record->type !== 'view') {
                continue;
            }
            if ($target !== null && strcasecmp($record->name, $target) !== 0) {
                continue;
            }

            $sql = $record->sql ?? '';
            $rows[] = [
                'schema' => $schemaName,
                'name' => $record->name,
                'type' => $record->type,
                'ncol' => $record->type === 'table' ? count($this->tableInfo($record->name, true)) : $this->viewColumnCount($sql),
                'wr' => self::isWithoutRowidSql($sql) ? 1 : 0,
                'strict' => self::isStrictTableSql($sql) ? 1 : 0,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, builtin: int, type: string, enc: string, narg: int, flags: int}>
     */
    public function functionList(): array
    {
        $rows = [];
        foreach ($this->functions as $function) {
            $name = $function['name'] ?? null;
            if (!is_string($name) || trim($name) === '') {
                throw new InvalidArgumentException('SQLite function_list entries need a function name');
            }
            $type = strtoupper((string) ($function['type'] ?? 's'));
            if (!in_array($type, ['S', 'W', 'A'], true)) {
                throw new InvalidArgumentException("SQLite function_list function {$name} has unsupported type {$type}");
            }
            $enc = strtolower((string) ($function['enc'] ?? 'utf8'));
            if (!in_array($enc, ['utf8', 'utf16le', 'utf16be'], true)) {
                throw new InvalidArgumentException("SQLite function_list function {$name} has unsupported encoding {$enc}");
            }

            $rows[] = [
                'name' => strtolower($name),
                'builtin' => (int) ($function['builtin'] ?? 0),
                'type' => strtolower($type),
                'enc' => $enc,
                'narg' => (int) ($function['narg'] ?? -1),
                'flags' => (int) ($function['flags'] ?? 0),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => [$left['name'], $left['narg'], $left['type']] <=> [$right['name'], $right['narg'], $right['type']]);

        return $rows;
    }

    /**
     * @return list<array{name: string}>
     */
    public function moduleList(): array
    {
        $rows = [];
        foreach ($this->modules as $module) {
            $name = $module['name'] ?? null;
            if (!is_string($name) || trim($name) === '') {
                throw new InvalidArgumentException('SQLite module_list entries need a module name');
            }
            $rows[] = ['name' => strtolower($name)];
        }

        usort($rows, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);

        return array_values($rows);
    }

    /**
     * @return list<array{seq: int, name: string}>
     */
    public function collationList(): array
    {
        $rows = [];
        foreach ($this->collations as $index => $collation) {
            $name = $collation['name'] ?? null;
            if (!is_string($name) || trim($name) === '') {
                throw new InvalidArgumentException('SQLite collation_list entries need a collation name');
            }
            $rows[] = [
                'seq' => (int) ($collation['seq'] ?? $index),
                'name' => strtoupper($name),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => $left['seq'] <=> $right['seq']);

        return array_values($rows);
    }

    /**
     * @return list<array{name: string}>
     */
    public function pragmaList(): array
    {
        $names = [
            'collation_list',
            'foreign_key_list',
            'function_list',
            'index_info',
            'index_list',
            'index_xinfo',
            'module_list',
            'pragma_list',
            'table_info',
            'table_list',
            'table_xinfo',
        ];

        return array_map(static fn (string $name): array => ['name' => $name], $names);
    }

    private function viewColumnCount(string $sql): int
    {
        if (!preg_match('/\bas\s+select\s+(?<projection>.*?)\s+from\s+/is', $sql, $matches)) {
            return 0;
        }

        $projection = trim($matches['projection']);
        if ($projection === '') {
            return 0;
        }

        $knownFunctions = [];
        foreach ($this->functionList() as $function) {
            $knownFunctions[$function['name']] = true;
        }

        if (preg_match_all('/\b(?<name>[A-Za-z_][A-Za-z0-9_]*)\s*\(/', $projection, $functionMatches)) {
            foreach ($functionMatches['name'] as $functionName) {
                if (!isset($knownFunctions[strtolower($functionName)])) {
                    return 0;
                }
            }
        }

        return count(self::splitTopLevel($projection, ','));
    }

    /**
     * @return list<array{name: string, type: string, notNull: bool, default: string|null, primaryKey: int, hidden: int}>
     */
    private function columnsFromCreateView(string $sql): array
    {
        $identifier = '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)';
        if (!preg_match('/^\s*CREATE\s+(?:TEMP(?:ORARY)?\s+)?VIEW\s+(?:IF\s+NOT\s+EXISTS\s+)?' . $identifier . '\s*(?:\((?<aliases>.*?)\))?\s+AS\s+SELECT\s+(?<projection>.*?)\s+FROM\s+(?<source>' . $identifier . ')/is', $sql, $matches)) {
            return [];
        }

        $source = self::unquoteIdentifier($matches['source']);
        $sourceColumns = $this->tableColumns($source);
        if ($sourceColumns === []) {
            return [];
        }

        $aliases = [];
        if (($matches['aliases'] ?? '') !== '') {
            foreach (self::splitTopLevel($matches['aliases'], ',') as $alias) {
                $aliasIdentifier = self::readIdentifier($alias, 0);
                if ($aliasIdentifier !== null) {
                    $aliases[] = $aliasIdentifier['identifier'];
                }
            }
        }

        $columns = [];
        foreach (self::splitTopLevel($matches['projection'], ',') as $offset => $projection) {
            $projection = trim($projection);
            if ($projection === '*') {
                array_push($columns, ...$sourceColumns);
                continue;
            }
            if (preg_match('/^(?<table>' . $identifier . ')\s*\.\s*\*$/i', $projection, $starMatch) === 1) {
                array_push($columns, ...$sourceColumns);
                continue;
            }

            $name = self::viewProjectionName($projection);
            if ($name === null) {
                if (!isset($aliases[$offset])) {
                    return [];
                }
                $columns[] = [
                    'name' => $aliases[$offset],
                    'type' => '',
                    'notNull' => false,
                    'default' => null,
                    'primaryKey' => 0,
                    'hidden' => 0,
                ];
                continue;
            }
            $sourceColumn = self::columnByName($sourceColumns, $name);
            $columns[] = $sourceColumn ?? [
                'name' => $name,
                'type' => '',
                'notNull' => false,
                'default' => null,
                'primaryKey' => 0,
                'hidden' => 0,
            ];
        }

        foreach ($aliases as $offset => $alias) {
            if (!isset($columns[$offset])) {
                break;
            }
            $columns[$offset]['name'] = $alias;
        }

        return $columns;
    }

    private static function viewProjectionName(string $projection): ?string
    {
        if (preg_match('/\s+AS\s+(?<alias>"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s*$/i', $projection, $matches) === 1) {
            return self::unquoteIdentifier($matches['alias']);
        }
        if (preg_match('/^(?<column>"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?:\s+COLLATE\s+[A-Za-z_][A-Za-z0-9_]*)?$/i', $projection, $matches) === 1) {
            return self::unquoteIdentifier($matches['column']);
        }

        return null;
    }

    /**
     * @param list<array{name: string, type: string, notNull: bool, default: string|null, primaryKey: int, hidden: int}> $columns
     * @return array{name: string, type: string, notNull: bool, default: string|null, primaryKey: int, hidden: int}|null
     */
    private static function columnByName(array $columns, string $name): ?array
    {
        foreach ($columns as $column) {
            if (strcasecmp($column['name'], $name) === 0) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return array{pragma: 'table_info'|'table_xinfo'|'index_list'|'index_info'|'index_xinfo'|'foreign_key_list'|'function_list'|'module_list'|'collation_list'|'pragma_list', schema: string|null, target: string}
     */
    public static function parsePragma(string $sql): array
    {
        $trimmed = rtrim(trim($sql), ';');
        $identifier = '(?:\"(?:\"\"|[^\"])+\"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*)';
        if (preg_match('/^pragma\s+(?:(?<schema>' . $identifier . ')\s*\.\s*)?(?<pragma>function_list|module_list|collation_list|pragma_list|table_list)(?:\s*(?:\(\s*(?<target>' . $identifier . ')?\s*\)|=\s*(?<equals>' . $identifier . '))?)?$/i', $trimmed, $matches) === 1) {
            return [
                'pragma' => strtolower($matches['pragma']),
                'schema' => isset($matches['schema']) && $matches['schema'] !== '' ? strtolower(self::unquoteIdentifier($matches['schema'])) : null,
                'target' => self::unquoteIdentifier(($matches['target'] ?? '') !== '' ? $matches['target'] : ($matches['equals'] ?? '')),
            ];
        }
        if (!preg_match('/^pragma\s+(?:(?<schema>' . $identifier . ')\s*\.\s*)?(?<pragma>table_info|table_xinfo|index_list|index_info|index_xinfo|foreign_key_list)\s*(?:\(\s*(?<paren>' . $identifier . ')\s*\)|=\s*(?<equals>' . $identifier . '))$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Only PRAGMA table_info, table_xinfo, index_list, index_info, index_xinfo, foreign_key_list, table_list, function_list, module_list, collation_list, and pragma_list are supported');
        }

        return [
            'pragma' => strtolower($matches['pragma']),
            'schema' => isset($matches['schema']) && $matches['schema'] !== '' ? strtolower(self::unquoteIdentifier($matches['schema'])) : null,
            'target' => self::unquoteIdentifier($matches['paren'] !== '' ? $matches['paren'] : $matches['equals']),
        ];
    }

    /**
     * @return array{pragma: 'table_info'|'table_xinfo'|'index_list'|'index_info'|'index_xinfo'|'foreign_key_list'|'function_list'|'module_list'|'collation_list'|'pragma_list', schema: string|null, target: string}
     */
    public static function parseTableValuedPragma(string $sql): array
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^pragma_(?<pragma>function_list|module_list|collation_list|pragma_list)\s*\(\s*\)$/i', $trimmed, $matches) === 1) {
            return [
                'pragma' => strtolower($matches['pragma']),
                'schema' => null,
                'target' => '',
            ];
        }
        if (!preg_match('/^pragma_(?<pragma>table_info|table_xinfo|index_list|index_info|index_xinfo|foreign_key_list|table_list)\s*\((?<args>.*)\)$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Only table-valued PRAGMA schema functions are supported');
        }

        $pragma = strtolower($matches['pragma']);
        $args = array_map('trim', self::splitTopLevel($matches['args'], ','));
        if ($pragma === 'table_list' && count($args) === 1 && $args[0] === '') {
            return [
                'pragma' => $pragma,
                'schema' => null,
                'target' => '',
            ];
        }
        if (count($args) < 1 || count($args) > 2 || $args[0] === '') {
            throw new InvalidArgumentException("pragma_{$pragma} needs a target argument");
        }
        if (count($args) === 2 && $args[1] === '') {
            throw new InvalidArgumentException("pragma_{$pragma} schema argument cannot be empty");
        }

        return [
            'pragma' => $pragma,
            'schema' => isset($args[1]) ? strtolower(self::unquoteIdentifier($args[1])) : null,
            'target' => self::unquoteIdentifier($args[0]),
        ];
    }

    /**
     * @return list<array{name: string, type: string, notNull: bool, default: string|null, primaryKey: int, hidden: int}>|null
     */
    private static function pragmaVirtualTableColumns(string $tableName): ?array
    {
        $name = strtolower($tableName);
        if (!str_starts_with($name, 'pragma_')) {
            return null;
        }

        return match (substr($name, strlen('pragma_'))) {
            'table_info' => self::plainVirtualColumns(['cid', 'name', 'type', 'notnull', 'dflt_value', 'pk'], ['arg', 'schema']),
            'table_xinfo' => self::plainVirtualColumns(['cid', 'name', 'type', 'notnull', 'dflt_value', 'pk', 'hidden'], ['arg', 'schema']),
            'index_list' => self::plainVirtualColumns(['seq', 'name', 'unique', 'origin', 'partial'], ['arg', 'schema']),
            'index_info' => self::plainVirtualColumns(['seqno', 'cid', 'name'], ['arg', 'schema']),
            'index_xinfo' => self::plainVirtualColumns(['seqno', 'cid', 'name', 'desc', 'coll', 'key'], ['arg', 'schema']),
            'foreign_key_list' => self::plainVirtualColumns(['id', 'seq', 'table', 'from', 'to', 'on_update', 'on_delete', 'match'], ['arg', 'schema']),
            'table_list' => self::plainVirtualColumns(['schema', 'name', 'type', 'ncol', 'wr', 'strict'], ['arg']),
            'function_list' => self::plainVirtualColumns(['name', 'builtin', 'type', 'enc', 'narg', 'flags']),
            'module_list', 'pragma_list' => self::plainVirtualColumns(['name']),
            default => null,
        };
    }

    /**
     * @param list<string> $names
     * @param list<string> $hiddenNames
     * @return list<array{name: string, type: string, notNull: bool, default: string|null, primaryKey: int, hidden: int}>
     */
    private static function plainVirtualColumns(array $names, array $hiddenNames = []): array
    {
        $columns = array_map(
            static fn (string $name): array => [
                'name' => $name,
                'type' => '',
                'notNull' => false,
                'default' => null,
                'primaryKey' => 0,
                'hidden' => 0,
            ],
            $names,
        );
        foreach ($hiddenNames as $name) {
            $columns[] = [
                'name' => $name,
                'type' => '',
                'notNull' => false,
                'default' => null,
                'primaryKey' => 0,
                'hidden' => 1,
            ];
        }

        return $columns;
    }

    /**
     * @param list<array{name: string, type: string, notNull: bool, default: string|null, primaryKey: int, hidden: int}> $columns
     * @return list<array{cid: int, name: string, type: string, notnull: int, dflt_value: string|null, pk: int}|array{cid: int, name: string, type: string, notnull: int, dflt_value: string|null, pk: int, hidden: int}>
     */
    private static function columnsToPragmaTableInfo(array $columns, bool $includeHidden): array
    {
        $rows = [];
        foreach ($columns as $cid => $column) {
            if ($column['hidden'] !== 0 && !$includeHidden) {
                continue;
            }

            $row = [
                'cid' => $cid,
                'name' => $column['name'],
                'type' => $column['type'],
                'notnull' => $column['notNull'] ? 1 : 0,
                'dflt_value' => $column['default'],
                'pk' => $column['primaryKey'],
            ];
            if ($includeHidden) {
                $row['hidden'] = $column['hidden'];
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, type: string, notNull: bool, default: string|null, primaryKey: int, hidden: int}>
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
            $columns[] = [
                'name' => $name,
                'type' => $type,
                'notNull' => self::containsTopLevelKeyword($tail, 'NOT NULL'),
                'default' => self::defaultValue($tail),
                'primaryKey' => self::containsTopLevelKeyword($tail, 'PRIMARY KEY') ? 1 : 0,
                'hidden' => self::generatedHiddenCode($tail),
            ];
        }

        if ($tablePrimaryKeys !== []) {
            foreach ($columns as &$column) {
                $primaryKeyOrdinal = $tablePrimaryKeys[strtolower($column['name'])] ?? 0;
                $column['primaryKey'] = $primaryKeyOrdinal;
            }
        }

        return $columns;
    }

    /**
     * @return list<array{id: int, seq: int, table: string, from: string, to: string|null, on_update: string, on_delete: string, match: string}>
     */
    private static function foreignKeyFromTableConstraint(string $definition, int $id): array
    {
        $foreignOffset = self::findTopLevelKeyword($definition, 'FOREIGN KEY');
        if ($foreignOffset === null) {
            return [];
        }
        $localOpen = strpos($definition, '(', $foreignOffset);
        if ($localOpen === false) {
            return [];
        }
        $localClose = self::matchingParen($definition, $localOpen);
        if ($localClose === null) {
            return [];
        }
        $reference = self::referenceClause(substr($definition, $localClose + 1));
        if ($reference === null) {
            return [];
        }

        $locals = self::identifierList(substr($definition, $localOpen + 1, $localClose - $localOpen - 1));
        $foreigns = $reference['columns'];
        $rows = [];
        foreach ($locals as $seq => $from) {
            $rows[] = [
                'id' => $id,
                'seq' => $seq,
                'table' => $reference['table'],
                'from' => $from,
                'to' => $foreigns[$seq] ?? null,
                'on_update' => $reference['onUpdate'],
                'on_delete' => $reference['onDelete'],
                'match' => $reference['match'],
            ];
        }

        return $rows;
    }

    /**
     * @return array{id: int, seq: int, table: string, from: string, to: string|null, on_update: string, on_delete: string, match: string}|null
     */
    private static function foreignKeyFromColumnConstraint(string $columnName, string $tail, int $id): ?array
    {
        $reference = self::referenceClause($tail);
        if ($reference === null) {
            return null;
        }

        return [
            'id' => $id,
            'seq' => 0,
            'table' => $reference['table'],
            'from' => $columnName,
            'to' => $reference['columns'][0] ?? null,
            'on_update' => $reference['onUpdate'],
            'on_delete' => $reference['onDelete'],
            'match' => $reference['match'],
        ];
    }

    /**
     * @return array{table: string, columns: list<string>, onUpdate: string, onDelete: string, match: string}|null
     */
    private static function referenceClause(string $tail): ?array
    {
        $offset = self::findTopLevelKeyword($tail, 'REFERENCES');
        if ($offset === null) {
            return null;
        }
        $identifier = self::readIdentifier($tail, $offset + strlen('REFERENCES'));
        if ($identifier === null) {
            return null;
        }

        $remainder = ltrim(substr($tail, $identifier['end']));
        $columns = [];
        if ($remainder !== '' && $remainder[0] === '(') {
            $close = self::matchingParen($remainder, 0);
            if ($close !== null) {
                $columns = self::identifierList(substr($remainder, 1, $close - 1));
                $remainder = substr($remainder, $close + 1);
            }
        }

        return [
            'table' => $identifier['identifier'],
            'columns' => $columns,
            'onUpdate' => self::foreignKeyAction($remainder, 'UPDATE'),
            'onDelete' => self::foreignKeyAction($remainder, 'DELETE'),
            'match' => self::foreignKeyMatch($remainder),
        ];
    }

    /**
     * @return list<string>
     */
    private static function identifierList(string $list): array
    {
        $columns = [];
        foreach (self::splitTopLevel($list, ',') as $part) {
            $identifier = self::readIdentifier(trim($part), 0);
            if ($identifier !== null) {
                $columns[] = $identifier['identifier'];
            }
        }

        return $columns;
    }

    private static function foreignKeyAction(string $clause, string $kind): string
    {
        if (!preg_match('/\bON\s+' . $kind . '\s+(?<action>SET\s+NULL|SET\s+DEFAULT|CASCADE|RESTRICT|NO\s+ACTION)\b/i', $clause, $matches)) {
            return 'NO ACTION';
        }

        return strtoupper(preg_replace('/\s+/', ' ', $matches['action']));
    }

    private static function foreignKeyMatch(string $clause): string
    {
        if (!preg_match('/\bMATCH\s+(?<match>[A-Za-z_][A-Za-z0-9_]*)\b/i', $clause, $matches)) {
            return 'NONE';
        }

        return strtoupper($matches['match']);
    }

    /**
     * @return array<string, int>
     */
    private static function tablePrimaryKeyColumns(string $list): array
    {
        $columns = [];
        $ordinal = 1;
        foreach (self::splitTopLevel($list, ',') as $part) {
            $identifier = self::readIdentifier(trim($part), 0);
            if ($identifier !== null) {
                $normalized = strtolower($identifier['identifier']);
                if (!isset($columns[$normalized])) {
                    $columns[$normalized] = $ordinal;
                }
                $ordinal++;
            }
        }

        return $columns;
    }

    /**
     * @return list<array{name: string, expression: bool, collation: string, descending: bool}>
     */
    private static function indexTermsFromCreateIndex(string $sql): array
    {
        $body = self::parenthesizedBody($sql);
        if ($body === null) {
            return [];
        }

        $terms = [];
        foreach (self::splitTopLevel($body, ',') as $term) {
            $term = trim($term);
            $identifier = self::readIdentifier($term, 0);
            $isExpression = $identifier === null || self::termStartsWithExpression($term, $identifier['end']);
            $terms[] = [
                'name' => $isExpression ? $term : $identifier['identifier'],
                'expression' => $isExpression,
                'collation' => self::termCollation($term),
                'descending' => self::termDescending($term),
            ];
        }

        return $terms;
    }

    private static function termStartsWithExpression(string $term, int $identifierEnd): bool
    {
        $tail = ltrim(substr($term, $identifierEnd));
        if ($tail === '') {
            return false;
        }

        return $tail[0] === '(' || preg_match('/^(?:\+|-|\*|\/|%|\|\||->|->>)/', $tail) === 1;
    }

    private static function termCollation(string $term): string
    {
        $offset = self::findTopLevelKeyword($term, 'COLLATE');
        if ($offset === null) {
            return 'BINARY';
        }
        $identifier = self::readIdentifier($term, $offset + strlen('COLLATE'));

        return $identifier === null ? 'BINARY' : strtoupper($identifier['identifier']);
    }

    private static function termDescending(string $term): bool
    {
        $descOffset = self::findTopLevelKeyword($term, 'DESC');
        if ($descOffset === null) {
            return false;
        }
        $ascOffset = self::findTopLevelKeyword($term, 'ASC');

        return $ascOffset === null || $descOffset > $ascOffset;
    }

    /**
     * @return list<array{name: string, expression: false, collation: string, descending: false}>
     */
    private function autoIndexColumnTerms(SQLiteSchemaRecord $index): array
    {
        return array_map(
            static fn (SQLiteIndexColumn $column): array => [
                'name' => $column->columnName,
                'expression' => false,
                'collation' => strtoupper($column->collation),
                'descending' => $column->descending,
            ],
            SQLiteCreateTable::automaticIndexColumnMetadata($this->tables[strtolower($index->tableName)]->sql ?? '')[$this->autoIndexOffset($index)] ?? [],
        );
    }

    private function autoIndexOffset(SQLiteSchemaRecord $index): int
    {
        $offset = 0;
        foreach ($this->indexesByTable[strtolower($index->tableName)] ?? [] as $candidate) {
            if (!str_starts_with($candidate->name, 'sqlite_autoindex_')) {
                continue;
            }
            if (strcasecmp($candidate->name, $index->name) === 0) {
                return $offset;
            }
            $offset++;
        }

        return 0;
    }

    private function findIndex(string $indexName): ?SQLiteSchemaRecord
    {
        foreach ($this->records as $record) {
            if ($record->type === 'index' && strcasecmp($record->name, $indexName) === 0) {
                return $record;
            }
        }

        return null;
    }

    private function indexOrigin(SQLiteSchemaRecord $index): string
    {
        if (!str_starts_with($index->name, 'sqlite_autoindex_')) {
            return 'c';
        }

        $table = $this->tables[strtolower($index->tableName)] ?? null;
        if ($table === null || $table->sql === null) {
            return 'u';
        }

        $primaryKeyColumns = [];
        foreach (self::columnsFromCreateTable($table->sql) as $column) {
            if ($column['primaryKey'] === 0) {
                continue;
            }
            $primaryKeyColumns[$column['primaryKey']] = strtolower($column['name']);
        }
        ksort($primaryKeyColumns);

        $terms = $this->autoIndexColumnTerms($index);
        $termColumns = array_map(static fn (array $term): string => strtolower($term['name']), $terms);

        return $primaryKeyColumns !== [] && array_values($primaryKeyColumns) === $termColumns ? 'pk' : 'u';
    }

    /**
     * @return list<array{cid: int, name: string|null}>
     */
    private function auxiliaryIndexTerms(SQLiteSchemaRecord $index, array $terms, array $tableColumns): array
    {
        $indexedNames = [];
        foreach ($terms as $term) {
            if (!$term['expression']) {
                $indexedNames[strtolower($term['name'])] = true;
            }
        }

        if (!$this->tableWithoutRowid($index->tableName)) {
            return [['cid' => -1, 'name' => null]];
        }

        $auxiliary = [];
        foreach ($tableColumns as $cid => $column) {
            if (!$column['primaryKey'] || isset($indexedNames[strtolower($column['name'])])) {
                continue;
            }
            $auxiliary[] = ['cid' => $cid, 'name' => $column['name']];
        }

        return $auxiliary;
    }

    private function tableWithoutRowid(string $tableName): bool
    {
        $sql = $this->tables[strtolower($tableName)]->sql ?? '';

        return self::isWithoutRowidSql($sql);
    }

    private static function isWithoutRowidSql(string $sql): bool
    {
        return preg_match('/\)\s*(?:STRICT\s*,\s*)?WITHOUT\s+ROWID\b/i', $sql) === 1
            || preg_match('/\)\s*WITHOUT\s+ROWID\s*,\s*STRICT\b/i', $sql) === 1;
    }

    private static function isStrictTableSql(string $sql): bool
    {
        return preg_match('/\)\s*(?:WITHOUT\s+ROWID\s*,\s*)?STRICT\b/i', $sql) === 1
            || preg_match('/\)\s*STRICT\s*,\s*WITHOUT\s+ROWID\b/i', $sql) === 1;
    }

    /**
     * @return list<array{name: string, type: string, notNull: bool, default: string|null, primaryKey: int, hidden: int}>
     */
    private function tableColumns(string $tableName): array
    {
        return self::columnsFromCreateTable($this->tables[strtolower($tableName)]->sql ?? '');
    }

    /**
     * @return list<string>
     */
    private function tableColumnNames(string $tableName): array
    {
        return array_map(
            static fn (array $column): string => strtolower($column['name']),
            $this->tableColumns($tableName),
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
            if ($upper === '' || in_array($upper, ['PRIMARY', 'NOT', 'NULL', 'UNIQUE', 'CHECK', 'DEFAULT', 'COLLATE', 'REFERENCES', 'GENERATED', 'ALWAYS', 'AS'], true)) {
                break;
            }
            $words[] = strtoupper(self::unquoteIdentifier(trim($word)));
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

        $end = self::defaultValueEnd($value);

        $default = trim(substr($value, 0, $end));
        if (str_starts_with($default, '(') && str_ends_with($default, ')')) {
            $close = self::matchingParen($default, 0);
            if ($close === strlen($default) - 1) {
                return self::stripDefaultTrailingComment(trim(substr($default, 1, -1)));
            }
        }

        return self::stripDefaultTrailingComment($default);
    }

    private static function stripDefaultTrailingComment(string $default): string
    {
        $comment = self::topLevelCommentOffset($default);
        if ($comment === null) {
            return $default;
        }

        return rtrim(substr($default, 0, $comment));
    }

    private static function defaultValueEnd(string $value): int
    {
        $length = strlen($value);
        if ($length === 0) {
            return 0;
        }

        $first = $value[0];
        if ($first === "'" || $first === '"' || $first === '`') {
            return self::skipQuoted($value, 0, $first) + 1;
        }
        if ($first === '[') {
            return self::skipBracketQuoted($value, 0) + 1;
        }
        if ($first === '(') {
            $close = self::matchingParen($value, 0);

            return $close === null ? $length : $close + 1;
        }
        if (($first === 'X' || $first === 'x') && isset($value[1]) && $value[1] === "'") {
            return self::skipQuoted($value, 1, "'") + 1;
        }

        $end = $length;
        $comment = self::topLevelCommentOffset($value);
        if ($comment !== null && $comment > 0) {
            $end = min($end, $comment);
        }
        foreach (['COLLATE', 'NOT NULL', 'PRIMARY KEY', 'UNIQUE', 'CHECK', 'REFERENCES', 'GENERATED'] as $keyword) {
            $found = self::findTopLevelKeyword($value, $keyword);
            if ($found !== null && $found > 0) {
                $end = min($end, $found);
            }
        }

        return $end;
    }

    private static function topLevelCommentOffset(string $text): ?int
    {
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
            if ($depth === 0 && $char === '/' && ($text[$i + 1] ?? '') === '*') {
                return $i;
            }
            if ($depth === 0 && $char === '-' && ($text[$i + 1] ?? '') === '-') {
                return $i;
            }
        }

        return null;
    }

    private static function generatedHiddenCode(string $tail): int
    {
        if (self::generatedAsExpressionOffset($tail) === null) {
            return 0;
        }
        if (self::containsTopLevelKeyword($tail, 'STORED')) {
            return 3;
        }

        return 2;
    }

    private static function generatedAsExpressionOffset(string $tail): ?int
    {
        $offset = 0;
        while (true) {
            $asOffset = self::findTopLevelKeyword(substr($tail, $offset), 'AS');
            if ($asOffset === null) {
                return null;
            }

            $asOffset += $offset;
            $after = ltrim(substr($tail, $asOffset + strlen('AS')));
            if ($after !== '' && $after[0] === '(') {
                return $asOffset;
            }

            $offset = $asOffset + strlen('AS');
        }
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
