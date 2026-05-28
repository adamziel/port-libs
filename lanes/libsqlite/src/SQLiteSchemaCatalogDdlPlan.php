<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteSchemaCatalogDdlPlan
{
    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array{
     *     status:string,
     *     operation:string,
     *     statement_count:int,
     *     applied_count:int,
     *     skipped_count:int,
     *     schema_version_before:int,
     *     schema_version_after:int,
     *     data_version_before:int,
     *     data_version_after:int,
     *     current:list<array{type:string,name:string,tbl_name:string,rootpage:int|null,sql:string|null,rowid:int}>,
     *     next:list<array{type:string,name:string,tbl_name:string,rootpage:int|null,sql:string|null,rowid:int}>,
     *     applied:list<array<string, mixed>>,
     *     skipped:list<array{statement:string,reason:string}>,
     *     warnings:list<array{statement:string,reason:string}>,
     *     dropped:list<string>,
     *     renamed:list<array{from:string,to:string,type:string}>,
     *     dependencies:list<string>
     * }
     */
    public static function currentNext(array $records, string $ddlSql, array $options = []): array
    {
        $schemaVersion = self::nonNegativeInt($options['schema_version'] ?? 0, 'schema_version');
        $dataVersion = self::nonNegativeInt($options['data_version'] ?? 1, 'data_version');
        $nextRootPage = max(2, self::nonNegativeInt($options['next_rootpage'] ?? self::nextRootPage($records), 'next_rootpage'));
        $nextRowId = max(1, self::nonNegativeInt($options['next_rowid'] ?? self::nextRowId($records), 'next_rowid'));
        $honorIfExists = (bool) ($options['honor_if_exists'] ?? true);
        $honorIfNotExists = (bool) ($options['honor_if_not_exists'] ?? true);

        $current = self::recordRows($records);
        $catalog = [];
        foreach ($records as $record) {
            $catalog[strtolower($record->name)] = $record;
        }

        $applied = [];
        $skipped = [];
        $warnings = [];
        $dropped = [];
        $renamed = [];
        foreach (self::splitStatements($ddlSql) as $statement) {
            $parsed = self::parseDdlStatement($statement);
            if ($parsed === null) {
                $warnings[] = ['statement' => $statement, 'reason' => 'unsupported_or_non_schema_statement'];
                continue;
            }

            $nameKey = strtolower($parsed['name']);
            if ($parsed['action'] === 'create') {
                if (isset($catalog[$nameKey])) {
                    if ($parsed['if_not_exists'] && $honorIfNotExists) {
                        $skipped[] = ['statement' => $statement, 'reason' => 'already_exists_if_not_exists'];
                        continue;
                    }
                    throw new InvalidArgumentException("Schema object already exists: {$parsed['name']}");
                }

                $rootPage = in_array($parsed['type'], ['table', 'index'], true) ? $nextRootPage++ : 0;
                $record = new SQLiteSchemaRecord(
                    $parsed['type'],
                    $parsed['name'],
                    $parsed['table'] ?? $parsed['name'],
                    $rootPage,
                    $statement,
                    $nextRowId++,
                );
                $catalog[$nameKey] = $record;
                $applied[] = [
                    'action' => 'create',
                    'type' => $record->type,
                    'name' => $record->name,
                    'rootpage' => $record->rootPage,
                    'rowid' => $record->rowId,
                    'dependencies' => self::createDependencies($record),
                ];
                continue;
            }

            if ($parsed['action'] === 'drop') {
                if (!isset($catalog[$nameKey])) {
                    if ($parsed['if_exists'] && $honorIfExists) {
                        $skipped[] = ['statement' => $statement, 'reason' => 'missing_if_exists'];
                        continue;
                    }
                    throw new InvalidArgumentException("Schema object does not exist: {$parsed['name']}");
                }
                if ($catalog[$nameKey]->type !== $parsed['type']) {
                    throw new InvalidArgumentException("Schema object {$parsed['name']} is not a {$parsed['type']}");
                }

                unset($catalog[$nameKey]);
                $dropped[] = $parsed['name'];
                $applied[] = [
                    'action' => 'drop',
                    'type' => $parsed['type'],
                    'name' => $parsed['name'],
                    'dependencies' => ['sqlite-schema-catalog-ddl', 'sqlite-schema-record-delete'],
                ];
                continue;
            }

            if (!isset($catalog[$nameKey])) {
                throw new InvalidArgumentException("Schema table does not exist: {$parsed['name']}");
            }
            $record = $catalog[$nameKey];
            if ($record->type !== 'table') {
                throw new InvalidArgumentException("Schema object {$parsed['name']} is not a table");
            }
            $newName = $parsed['new_name'];
            $newKey = strtolower($newName);
            if (isset($catalog[$newKey])) {
                throw new InvalidArgumentException("Schema object already exists: {$newName}");
            }

            unset($catalog[$nameKey]);
            $renamedSql = self::rewriteCreateObjectName($record->sql, $record->name, $newName);
            $renamedRecord = new SQLiteSchemaRecord('table', $newName, $newName, $record->rootPage, $renamedSql, $record->rowId);
            $catalog[$newKey] = $renamedRecord;
            foreach ($catalog as $key => $candidate) {
                if (strcasecmp($candidate->tableName, $record->name) !== 0) {
                    continue;
                }
                $catalog[$key] = new SQLiteSchemaRecord(
                    $candidate->type,
                    $candidate->name,
                    $newName,
                    $candidate->rootPage,
                    self::rewriteObjectReferences($candidate->sql, $record->name, $newName),
                    $candidate->rowId,
                );
            }
            $renamed[] = ['from' => $record->name, 'to' => $newName, 'type' => 'table'];
            $applied[] = [
                'action' => 'rename_table',
                'type' => 'table',
                'name' => $record->name,
                'new_name' => $newName,
                'dependencies' => ['sqlite-schema-catalog-ddl', 'sqlite-alter-table-rename-reparse'],
            ];
        }

        $next = self::recordRows(array_values($catalog));
        usort($next, static fn (array $a, array $b): int => $a['rowid'] <=> $b['rowid']);

        return [
            'status' => 'ok',
            'operation' => 'schema-catalog-ddl-current-next',
            'statement_count' => count(self::splitStatements($ddlSql)),
            'applied_count' => count($applied),
            'skipped_count' => count($skipped),
            'schema_version_before' => $schemaVersion,
            'schema_version_after' => $schemaVersion + count($applied),
            'data_version_before' => $dataVersion,
            'data_version_after' => $dataVersion + (count($applied) > 0 ? 1 : 0),
            'current' => $current,
            'next' => $next,
            'applied' => $applied,
            'skipped' => $skipped,
            'warnings' => $warnings,
            'dropped' => $dropped,
            'renamed' => $renamed,
            'dependencies' => [
                'sqlite-schema-catalog-ddl',
                'sqlite-schema-cookie-update',
                'sqlite-schema-record-delete',
                'sqlite-alter-table-rename-reparse',
            ],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array{type:string,name:string,tbl_name:string,rootpage:int|null,sql:string|null,rowid:int}>
     */
    private static function recordRows(array $records): array
    {
        $rows = array_map(static fn (SQLiteSchemaRecord $record): array => [
            'type' => $record->type,
            'name' => $record->name,
            'tbl_name' => $record->tableName,
            'rootpage' => $record->rootPage,
            'sql' => $record->sql,
            'rowid' => $record->rowId,
        ], $records);
        usort($rows, static fn (array $a, array $b): int => $a['rowid'] <=> $b['rowid']);

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function splitStatements(string $sql): array
    {
        $statements = [];
        $start = 0;
        $depth = 0;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === '-' && ($sql[$i + 1] ?? '') === '-') {
                $end = strpos($sql, "\n", $i + 2);
                $i = $end === false ? $length : $end;
                continue;
            }
            if ($char === '/' && ($sql[$i + 1] ?? '') === '*') {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    throw new InvalidArgumentException('Unterminated SQL block comment');
                }
                $i = $end + 1;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($sql, $i, $char);
                continue;
            }
            if ($char === '[') {
                $end = strpos($sql, ']', $i + 1);
                if ($end === false) {
                    throw new InvalidArgumentException('Unterminated bracket quoted identifier');
                }
                $i = $end;
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
            if ($char === ';' && $depth === 0 && !self::insideCreateTriggerBody(substr($sql, $start, $i - $start))) {
                $statement = trim(substr($sql, $start, $i - $start));
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $start = $i + 1;
            }
        }

        $tail = trim(substr($sql, $start));
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function parseDdlStatement(string $statement): ?array
    {
        $normalized = preg_replace('/\s+/', ' ', trim(self::stripLeadingComments($statement)));
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }
        $identifier = '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)';
        $qualifiedIdentifier = '(?:' . $identifier . '\s*\.\s*)?' . $identifier;

        if (preg_match('/^CREATE\s+(?:TEMP(?:ORARY)?\s+)?TABLE\s+(IF\s+NOT\s+EXISTS\s+)?(?<name>' . $qualifiedIdentifier . ')\s*\(/i', $normalized, $matches) === 1) {
            $name = self::unquoteQualifiedIdentifier($matches['name']);

            return ['action' => 'create', 'type' => 'table', 'schema' => $name['schema'], 'name' => $name['name'], 'table' => null, 'if_not_exists' => ($matches[1] ?? '') !== ''];
        }
        if (preg_match('/^CREATE\s+(?:UNIQUE\s+)?INDEX\s+(IF\s+NOT\s+EXISTS\s+)?(?<name>' . $qualifiedIdentifier . ')\s+ON\s+(?<table>' . $qualifiedIdentifier . ')\s*\(/i', $normalized, $matches) === 1) {
            $name = self::unquoteQualifiedIdentifier($matches['name']);
            $table = self::unquoteQualifiedIdentifier($matches['table']);

            return ['action' => 'create', 'type' => 'index', 'schema' => $name['schema'], 'name' => $name['name'], 'table_schema' => $table['schema'], 'table' => $table['name'], 'if_not_exists' => ($matches[1] ?? '') !== ''];
        }
        if (preg_match('/^CREATE\s+(?:TEMP(?:ORARY)?\s+)?VIEW\s+(IF\s+NOT\s+EXISTS\s+)?(?<name>' . $qualifiedIdentifier . ')\s+/i', $normalized, $matches) === 1) {
            $name = self::unquoteQualifiedIdentifier($matches['name']);

            return ['action' => 'create', 'type' => 'view', 'schema' => $name['schema'], 'name' => $name['name'], 'table' => null, 'if_not_exists' => ($matches[1] ?? '') !== ''];
        }
        if (preg_match('/^CREATE\s+(?:TEMP(?:ORARY)?\s+)?TRIGGER\s+(IF\s+NOT\s+EXISTS\s+)?(?<name>' . $qualifiedIdentifier . ')\s+/i', $normalized, $matches) === 1) {
            $name = self::unquoteQualifiedIdentifier($matches['name']);
            $target = self::triggerTable($normalized);

            return ['action' => 'create', 'type' => 'trigger', 'schema' => $name['schema'], 'name' => $name['name'], 'table_schema' => $target['schema'], 'table' => $target['name'], 'if_not_exists' => ($matches[1] ?? '') !== ''];
        }
        if (preg_match('/^DROP\s+(?<type>TABLE|INDEX|VIEW|TRIGGER)\s+(IF\s+EXISTS\s+)?(?<name>' . $qualifiedIdentifier . ')\s*$/i', $normalized, $matches) === 1) {
            $name = self::unquoteQualifiedIdentifier($matches['name']);

            return ['action' => 'drop', 'type' => strtolower($matches['type']), 'schema' => $name['schema'], 'name' => $name['name'], 'if_exists' => ($matches[2] ?? '') !== ''];
        }
        if (preg_match('/^ALTER\s+TABLE\s+(?<name>' . $qualifiedIdentifier . ')\s+RENAME\s+TO\s+(?<new>' . $identifier . ')\s*$/i', $normalized, $matches) === 1) {
            $name = self::unquoteQualifiedIdentifier($matches['name']);

            return ['action' => 'rename_table', 'type' => 'table', 'schema' => $name['schema'], 'name' => $name['name'], 'new_name' => self::unquoteIdentifier($matches['new'])];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function createDependencies(SQLiteSchemaRecord $record): array
    {
        $dependencies = ['sqlite-schema-catalog-ddl'];
        if ($record->type === 'table') {
            $dependencies[] = 'sqlite-schema-record-table-rootpage';
        }
        if ($record->type === 'index') {
            $dependencies[] = 'sqlite-schema-record-index-rootpage';
        }

        return $dependencies;
    }

    /**
     * @return array{schema:?string,name:?string}
     */
    private static function triggerTable(string $sql): array
    {
        $identifier = '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)';
        $qualifiedIdentifier = '(?:' . $identifier . '\s*\.\s*)?' . $identifier;
        if (preg_match('/\sON\s+(?<table>' . $qualifiedIdentifier . ')\s/i', $sql, $matches) !== 1) {
            return ['schema' => null, 'name' => null];
        }

        return self::unquoteQualifiedIdentifier($matches['table']);
    }

    private static function rewriteCreateObjectName(?string $sql, string $oldName, string $newName): ?string
    {
        if ($sql === null) {
            return null;
        }

        $pattern = '/^(\s*CREATE\s+(?:TEMP(?:ORARY)?\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?)(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)/i';
        $rewritten = preg_replace($pattern, '$1' . self::quoteIdentifier($newName), $sql, 1);

        return is_string($rewritten) ? $rewritten : $sql;
    }

    private static function rewriteObjectReferences(?string $sql, string $oldName, string $newName): ?string
    {
        if ($sql === null) {
            return null;
        }

        return preg_replace('/\b' . preg_quote($oldName, '/') . '\b/i', self::quoteIdentifier($newName), $sql) ?? $sql;
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private static function insideCreateTriggerBody(string $statement): bool
    {
        $normalized = preg_replace('/\s+/', ' ', self::stripLeadingComments($statement));
        if (!is_string($normalized) || preg_match('/^CREATE\s+(?:TEMP(?:ORARY)?\s+)?TRIGGER\s+/i', trim($normalized)) !== 1) {
            return false;
        }

        return preg_match('/\sEND\s*$/i', trim($normalized)) !== 1;
    }

    private static function stripLeadingComments(string $sql): string
    {
        $sql = ltrim($sql);
        while ($sql !== '') {
            if (str_starts_with($sql, '--')) {
                $end = strpos($sql, "\n");
                $sql = $end === false ? '' : ltrim(substr($sql, $end + 1));
                continue;
            }
            if (str_starts_with($sql, '/*')) {
                $end = strpos($sql, '*/');
                if ($end === false) {
                    throw new InvalidArgumentException('Unterminated SQL block comment');
                }
                $sql = ltrim(substr($sql, $end + 2));
                continue;
            }
            break;
        }

        return $sql;
    }

    private static function skipQuoted(string $text, int $offset, string $quote): int
    {
        $length = strlen($text);
        for ($i = $offset + 1; $i < $length; $i++) {
            if ($text[$i] !== $quote) {
                continue;
            }
            if (($text[$i + 1] ?? '') === $quote) {
                $i++;
                continue;
            }

            return $i;
        }

        throw new InvalidArgumentException('Unterminated SQL quoted text');
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new InvalidArgumentException('Schema object name cannot be empty');
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

    /**
     * @return array{schema:?string,name:string}
     */
    private static function unquoteQualifiedIdentifier(string $identifier): array
    {
        $parts = self::splitQualifiedIdentifier($identifier);
        if (count($parts) === 1) {
            return ['schema' => null, 'name' => self::unquoteIdentifier($parts[0])];
        }
        if (count($parts) === 2) {
            return ['schema' => self::unquoteIdentifier($parts[0]), 'name' => self::unquoteIdentifier($parts[1])];
        }

        throw new InvalidArgumentException('Schema object name has too many qualifiers');
    }

    /**
     * @return list<string>
     */
    private static function splitQualifiedIdentifier(string $identifier): array
    {
        $parts = [];
        $start = 0;
        $length = strlen($identifier);
        for ($i = 0; $i < $length; $i++) {
            $char = $identifier[$i];
            if ($char === '"' || $char === '`') {
                $i = self::skipQuoted($identifier, $i, $char);
                continue;
            }
            if ($char === '[') {
                $end = strpos($identifier, ']', $i + 1);
                if ($end === false) {
                    throw new InvalidArgumentException('Unterminated bracket quoted identifier');
                }
                $i = $end;
                continue;
            }
            if ($char !== '.') {
                continue;
            }
            $parts[] = trim(substr($identifier, $start, $i - $start));
            $start = $i + 1;
        }
        $parts[] = trim(substr($identifier, $start));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function nextRootPage(array $records): int
    {
        $max = 1;
        foreach ($records as $record) {
            if ($record->rootPage !== null) {
                $max = max($max, $record->rootPage);
            }
        }

        return $max + 1;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function nextRowId(array $records): int
    {
        $max = 0;
        foreach ($records as $record) {
            $max = max($max, $record->rowId);
        }

        return $max + 1;
    }

    private static function nonNegativeInt(mixed $value, string $name): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new InvalidArgumentException("{$name} must be a non-negative integer");
        }
        if ((int) $value < 0) {
            throw new InvalidArgumentException("{$name} must be a non-negative integer");
        }

        return (int) $value;
    }
}
