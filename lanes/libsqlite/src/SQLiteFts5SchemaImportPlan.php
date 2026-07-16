<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteFts5SchemaImportPlan
{
    /**
     * @param list<string> $availableTables
     * @return array<string, mixed>
     */
    public static function fromSql(string $sql, array $availableTables = []): array
    {
        $normalized = trim(rtrim($sql, " \t\r\n;"));
        $identifier = '(?:"(?:""|[^"])+"|`(?:``|[^`])+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)';
        if (!preg_match('/^CREATE\s+VIRTUAL\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?<name>' . $identifier . '(?:\s*\.\s*' . $identifier . ')?)\s+USING\s+(?<module>[A-Za-z_][A-Za-z0-9_]*)\s*\((?<args>.*)\)$/is', $normalized, $matches)) {
            throw new \InvalidArgumentException('FTS5 schema import expects CREATE VIRTUAL TABLE ... USING fts5(...) SQL');
        }

        $module = strtolower($matches['module']);
        if ($module !== 'fts5') {
            throw new \InvalidArgumentException('FTS5 schema import only supports the fts5 module');
        }

        [$schema, $table] = self::splitQualifiedName($matches['name']);
        $columns = [];
        $options = [];
        foreach (self::splitArguments($matches['args']) as $argument) {
            if (preg_match('/^(?<name>[A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?<value>.+)$/s', $argument, $optionMatch)) {
                $option = strtolower($optionMatch['name']);
                if (!in_array($option, ['tokenize', 'prefix', 'content', 'content_rowid', 'detail', 'columnsize', 'contentless_delete', 'tokendata'], true)) {
                    throw new \InvalidArgumentException("Unsupported FTS5 option: {$option}");
                }
                $options[$option] = self::unquote(trim($optionMatch['value']));
                continue;
            }

            $columns[] = self::parseColumn($argument);
        }

        if ($columns === []) {
            throw new \InvalidArgumentException('FTS5 schema import requires at least one indexed column definition');
        }

        $names = array_map(static fn (array $column): string => strtolower($column['name']), $columns);
        if (count($names) !== count(array_unique($names))) {
            throw new \InvalidArgumentException('FTS5 schema import column names must be unique');
        }

        $content = $options['content'] ?? null;
        $externalContent = $content !== null && $content !== '';
        $contentless = $content === '';
        $contentTablePresent = $externalContent ? in_array(strtolower($content), array_map('strtolower', $availableTables), true) : null;
        $contentlessDelete = self::booleanOption((string) ($options['contentless_delete'] ?? '0'), 'contentless_delete');
        if ($contentlessDelete && !$contentless) {
            throw new \InvalidArgumentException('FTS5 contentless_delete=1 requires content=\'\'');
        }
        $tokenizer = self::tokenizerPlan((string) ($options['tokenize'] ?? 'unicode61'));
        $prefixes = self::prefixPlan((string) ($options['prefix'] ?? ''));
        $detail = strtolower((string) ($options['detail'] ?? 'full'));
        if (!in_array($detail, ['full', 'column', 'none'], true)) {
            throw new \InvalidArgumentException('FTS5 detail must be full, column, or none');
        }
        $columnsize = self::integerOption((string) ($options['columnsize'] ?? '1'), 'columnsize');
        if ($columnsize < 0 || $columnsize > 1) {
            throw new \InvalidArgumentException('FTS5 columnsize must be 0 or 1');
        }
        $tokendata = self::booleanOption((string) ($options['tokendata'] ?? '0'), 'tokendata');

        $indexedColumns = array_values(array_map(
            static fn (array $column): string => $column['name'],
            array_filter($columns, static fn (array $column): bool => !$column['unindexed'])
        ));

        return [
            'status' => 'ok',
            'module' => 'fts5',
            'schema' => $schema,
            'table' => $table,
            'qualifiedName' => $schema . '.' . $table,
            'columns' => $columns,
            'indexedColumns' => $indexedColumns,
            'unindexedColumns' => array_values(array_map(
                static fn (array $column): string => $column['name'],
                array_filter($columns, static fn (array $column): bool => $column['unindexed'])
            )),
            'options' => [
                'tokenize' => $tokenizer,
                'prefix' => $prefixes,
                'content' => $content,
                'contentless' => $contentless,
                'externalContent' => $externalContent,
                'contentTablePresent' => $contentTablePresent,
                'contentRowid' => (string) ($options['content_rowid'] ?? 'rowid'),
                'detail' => $detail,
                'columnsize' => $columnsize,
                'contentlessDelete' => $contentlessDelete,
                'tokendata' => $tokendata,
            ],
            'shadowTables' => self::shadowTables($schema, $table, $contentless),
            'importActions' => self::importActions($externalContent, $contentTablePresent, $contentless),
            'schemaRecords' => self::schemaRecords($schema, $table, $columns, $contentless, $normalized),
            'externalContentSql' => self::externalContentSql($schema, $table, $content, (string) ($options['content_rowid'] ?? 'rowid'), $columns, $externalContent, $contentTablePresent),
            'jsonSchema' => self::jsonSchema($schema, $table, $columns, $tokenizer, $prefixes, $content, $externalContent, $contentTablePresent, $contentless, $contentlessDelete, $tokendata, $detail, $columnsize),
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitQualifiedName(string $name): array
    {
        $parts = array_map('trim', preg_split('/\s*\.\s*/', $name) ?: []);
        if (count($parts) === 1) {
            return ['main', self::unquoteIdentifier($parts[0])];
        }

        return [self::unquoteIdentifier($parts[0]), self::unquoteIdentifier($parts[1])];
    }

    /**
     * @return list<string>
     */
    private static function splitArguments(string $args): array
    {
        $parts = [];
        $buffer = '';
        $quote = null;
        $depth = 0;
        $length = strlen($args);
        for ($i = 0; $i < $length; $i++) {
            $char = $args[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote) {
                    if ($i + 1 < $length && $args[$i + 1] === $quote) {
                        $buffer .= $args[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === '\'' || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                $buffer .= $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $part = trim($buffer);
                if ($part !== '') {
                    $parts[] = $part;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }

        $part = trim($buffer);
        if ($part !== '') {
            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * @return array{name:string,unindexed:bool,raw:string}
     */
    private static function parseColumn(string $argument): array
    {
        if (!preg_match('/^(?<name>"(?:""|[^"])+"|`(?:``|[^`])+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?<tail>.*)$/s', trim($argument), $matches)) {
            throw new \InvalidArgumentException('Invalid FTS5 column definition');
        }

        $tail = trim($matches['tail']);
        $allowedTail = $tail === '' || preg_match('/^UNINDEXED$/i', $tail) === 1;
        if (!$allowedTail) {
            throw new \InvalidArgumentException('FTS5 column definitions only support optional UNINDEXED in this importer');
        }

        return [
            'name' => self::unquoteIdentifier($matches['name']),
            'unindexed' => $tail !== '',
            'raw' => trim($argument),
        ];
    }

    /**
     * @return array{name:string,args:list<string>,removeDiacritics:int|null,tokenchars:string|null,separators:string|null}
     */
    private static function tokenizerPlan(string $value): array
    {
        $tokens = self::shellWords($value);
        $name = strtolower($tokens[0] ?? 'unicode61');
        if (!in_array($name, ['unicode61', 'ascii', 'porter', 'trigram'], true)) {
            throw new \InvalidArgumentException("Unsupported FTS5 tokenizer: {$name}");
        }

        $plan = [
            'name' => $name,
            'args' => array_slice($tokens, 1),
            'removeDiacritics' => null,
            'tokenchars' => null,
            'separators' => null,
        ];
        for ($i = 1, $count = count($tokens); $i < $count; $i++) {
            $key = strtolower($tokens[$i]);
            $next = $tokens[$i + 1] ?? null;
            if ($key === 'remove_diacritics' && $next !== null) {
                $plan['removeDiacritics'] = (int) $next;
                $i++;
            } elseif ($key === 'tokenchars' && $next !== null) {
                $plan['tokenchars'] = $next;
                $i++;
            } elseif ($key === 'separators' && $next !== null) {
                $plan['separators'] = $next;
                $i++;
            }
        }

        return $plan;
    }

    /**
     * @return list<int>
     */
    private static function prefixPlan(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $prefixes = [];
        foreach (preg_split('/[\s,]+/', trim($value)) ?: [] as $part) {
            if ($part === '') {
                continue;
            }
            if (!ctype_digit($part) || (int) $part < 1) {
                throw new \InvalidArgumentException('FTS5 prefix lengths must be positive integers');
            }
            $prefixes[] = (int) $part;
        }

        return array_values(array_unique($prefixes));
    }

    /**
     * @return list<string>
     */
    private static function shadowTables(string $schema, string $table, bool $contentless): array
    {
        $suffixes = ['_data', '_idx', '_docsize', '_config'];
        if (!$contentless) {
            array_splice($suffixes, 2, 0, ['_content']);
        }

        return array_map(static fn (string $suffix): string => $schema . '.' . $table . $suffix, $suffixes);
    }

    /**
     * @param list<array{name:string,unindexed:bool,raw:string}> $columns
     * @return list<array{type:string,name:string,tbl_name:string,rootpage:int,sql:string,shadow:bool}>
     */
    private static function schemaRecords(string $schema, string $table, array $columns, bool $contentless, string $virtualSql): array
    {
        $records = [[
            'type' => 'table',
            'name' => $schema . '.' . $table,
            'tbl_name' => $table,
            'rootpage' => 0,
            'sql' => $virtualSql,
            'shadow' => false,
        ]];

        foreach (self::shadowTableSql($schema, $table, $columns, $contentless) as $name => $sql) {
            $records[] = [
                'type' => 'table',
                'name' => $name,
                'tbl_name' => substr($name, strlen($schema) + 1),
                'rootpage' => 0,
                'sql' => $sql,
                'shadow' => true,
            ];
        }

        return $records;
    }

    /**
     * @param list<array{name:string,unindexed:bool,raw:string}> $columns
     * @return array<string,string>
     */
    private static function shadowTableSql(string $schema, string $table, array $columns, bool $contentless): array
    {
        $qualified = static fn (string $suffix): string => self::quoteQualifiedName($schema, $table . $suffix);
        $sql = [
            $schema . '.' . $table . '_data' => 'CREATE TABLE ' . $qualified('_data') . '(id INTEGER PRIMARY KEY, block BLOB)',
            $schema . '.' . $table . '_idx' => 'CREATE TABLE ' . $qualified('_idx') . '(segid, term, pgno, PRIMARY KEY(segid, term)) WITHOUT ROWID',
        ];

        if (!$contentless) {
            $contentColumns = ['id INTEGER PRIMARY KEY'];
            foreach ($columns as $column) {
                $contentColumns[] = self::quoteIdentifier($column['name']);
            }
            $sql[$schema . '.' . $table . '_content'] = 'CREATE TABLE ' . $qualified('_content') . '(' . implode(', ', $contentColumns) . ')';
        }

        $sql[$schema . '.' . $table . '_docsize'] = 'CREATE TABLE ' . $qualified('_docsize') . '(id INTEGER PRIMARY KEY, sz BLOB)';
        $sql[$schema . '.' . $table . '_config'] = 'CREATE TABLE ' . $qualified('_config') . '(k PRIMARY KEY, v) WITHOUT ROWID';

        return $sql;
    }

    /**
     * @param list<array{name:string,unindexed:bool,raw:string}> $columns
     * @return array{rebuild:?string,deleteAll:?string,insertSelect:?string,blockedReason:?string}
     */
    private static function externalContentSql(string $schema, string $table, ?string $content, string $contentRowid, array $columns, bool $externalContent, ?bool $contentTablePresent): array
    {
        if (!$externalContent) {
            return [
                'rebuild' => null,
                'deleteAll' => null,
                'insertSelect' => null,
                'blockedReason' => null,
            ];
        }

        if ($contentTablePresent !== true) {
            return [
                'rebuild' => null,
                'deleteAll' => null,
                'insertSelect' => null,
                'blockedReason' => 'missing external content table',
            ];
        }

        $target = self::quoteQualifiedName($schema, $table);
        $columnNames = array_map(static fn (array $column): string => $column['name'], $columns);
        $insertColumns = array_merge(['rowid'], $columnNames);
        $selectColumns = array_merge([$contentRowid], $columnNames);

        return [
            'rebuild' => "INSERT INTO {$target}({$target}) VALUES('rebuild')",
            'deleteAll' => "INSERT INTO {$target}({$target}) VALUES('delete-all')",
            'insertSelect' => 'INSERT INTO ' . $target . '(' . implode(', ', array_map([self::class, 'quoteIdentifier'], $insertColumns)) . ') SELECT ' . implode(', ', array_map([self::class, 'quoteIdentifier'], $selectColumns)) . ' FROM ' . self::quoteIdentifier((string) $content),
            'blockedReason' => null,
        ];
    }

    /**
     * @param list<array{name:string,unindexed:bool,raw:string}> $columns
     * @param array{name:string,args:list<string>,removeDiacritics:int|null,tokenchars:string|null,separators:string|null} $tokenizer
     * @param list<int> $prefixes
     * @return array<string,mixed>
     */
    private static function jsonSchema(string $schema, string $table, array $columns, array $tokenizer, array $prefixes, ?string $content, bool $externalContent, ?bool $contentTablePresent, bool $contentless, bool $contentlessDelete, bool $tokendata, string $detail, int $columnsize): array
    {
        return [
            'kind' => 'sqlite-fts5-import-schema',
            'schema' => $schema,
            'table' => $table,
            'columns' => array_map(static fn (array $column): array => [
                'name' => $column['name'],
                'indexed' => !$column['unindexed'],
            ], $columns),
            'tokenizer' => $tokenizer,
            'prefix' => $prefixes,
            'content' => $content,
            'externalContent' => $externalContent,
            'contentTablePresent' => $contentTablePresent,
            'contentless' => $contentless,
            'contentlessDelete' => $contentlessDelete,
            'tokendata' => $tokendata,
            'detail' => $detail,
            'columnsize' => $columnsize,
        ];
    }

    /**
     * @return list<string>
     */
    private static function importActions(bool $externalContent, ?bool $contentTablePresent, bool $contentless): array
    {
        $actions = ['registerModule', 'createVirtualTable', 'createShadowTables'];
        if ($externalContent) {
            $actions[] = $contentTablePresent ? 'scheduleExternalContentRebuild' : 'blockMissingContentTable';
        } elseif ($contentless) {
            $actions[] = 'skipContentShadowRows';
        } else {
            $actions[] = 'copyInlineContentRows';
        }

        return $actions;
    }

    private static function integerOption(string $value, string $option): int
    {
        $value = trim($value);
        if (!preg_match('/^-?\d+$/', $value)) {
            throw new \InvalidArgumentException("FTS5 {$option} must be an integer");
        }

        return (int) $value;
    }

    private static function booleanOption(string $value, string $option): bool
    {
        $value = strtolower(trim($value));
        if ($value === '1' || $value === 'true' || $value === 'on') {
            return true;
        }
        if ($value === '0' || $value === 'false' || $value === 'off') {
            return false;
        }

        throw new \InvalidArgumentException("FTS5 {$option} must be a boolean");
    }

    /**
     * @return list<string>
     */
    private static function shellWords(string $value): array
    {
        preg_match_all('/\'((?:\'\'|[^\'])*)\'|"((?:""|[^"])*)"|(\S+)/', $value, $matches, PREG_SET_ORDER);
        $words = [];
        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $words[] = str_replace("''", "'", $match[1]);
            } elseif (($match[2] ?? '') !== '') {
                $words[] = str_replace('""', '"', $match[2]);
            } else {
                $words[] = $match[3];
            }
        }

        return $words;
    }

    private static function unquote(string $value): string
    {
        $value = trim($value);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '\'' && $last === '\'') || ($first === '"' && $last === '"')) {
                return str_replace($first . $first, $first, substr($value, 1, -1));
            }
        }

        return $value;
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if (str_starts_with($identifier, '[') && str_ends_with($identifier, ']')) {
            return substr($identifier, 1, -1);
        }
        if ((str_starts_with($identifier, '"') && str_ends_with($identifier, '"')) || (str_starts_with($identifier, '`') && str_ends_with($identifier, '`'))) {
            return str_replace($identifier[0] . $identifier[0], $identifier[0], substr($identifier, 1, -1));
        }

        return $identifier;
    }

    private static function quoteQualifiedName(string $schema, string $table): string
    {
        return self::quoteIdentifier($schema) . '.' . self::quoteIdentifier($table);
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
