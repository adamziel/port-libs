<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityTargetArgument
{
    /**
     * @param array<string,array{errors?:list<string>,tables?:list<string>,target_errors?:array<string,list<string>>}> $schemas
     * @return array{source:string,status:string,pragma:string,schema:?string,argument:array{kind:string,raw:?string,value:mixed,quoted:bool},target:?string,limit:int,checked_schemas:list<string>,errors:list<string>,rows:list<array<string,string>>,catchsql:array{0:int,1:mixed},dependencies:list<string>}
     */
    public static function execute(string $sql, array $schemas): array
    {
        $parsed = self::parse($sql);
        $catalog = self::normalizeSchemas($schemas);
        if ($catalog === []) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity target argument needs at least one schema');
        }

        $argument = $parsed['argument'];
        if ($argument['kind'] === 'target') {
            return self::targetResult($parsed, $catalog);
        }

        $limit = $argument['kind'] === 'limit' ? $argument['value'] : 100;
        $targets = self::schemaTargets($parsed['schema'], $catalog);
        $checked = [];
        $errors = [];
        foreach ($targets as $schema => $entry) {
            $checked[] = $entry['name'];
            foreach ($entry['errors'] as $message) {
                $errors[] = $message;
                if (count($errors) >= $limit) {
                    break 2;
                }
            }
        }

        return self::result(
            $parsed['pragma'],
            $parsed['schema'],
            $argument,
            null,
            $limit,
            $checked,
            $errors
        );
    }

    /**
     * @return array{pragma:string,schema:?string,argument:array{kind:string,raw:?string,value:mixed,quoted:bool}}
     */
    public static function parse(string $sql): array
    {
        $identifier = self::identifierPattern();
        $trimmed = trim(rtrim(trim($sql), ';'));
        if (!preg_match('/^PRAGMA\s+(?:(?<schema>' . $identifier . ')\s*\.\s*)?(?<pragma>integrity_check|quick_check)(?:\s*(?:=\s*(?<equals>.+)|\(\s*(?<paren>.+)\s*\)))?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity target argument needs an integrity_check or quick_check PRAGMA');
        }

        $rawArgument = null;
        if (array_key_exists('equals', $matches) && $matches['equals'] !== '') {
            $rawArgument = trim($matches['equals']);
        } elseif (array_key_exists('paren', $matches) && $matches['paren'] !== '') {
            $rawArgument = trim($matches['paren']);
        }

        return [
            'pragma' => strtolower($matches['pragma']),
            'schema' => isset($matches['schema']) && $matches['schema'] !== '' ? self::unquoteIdentifier($matches['schema']) : null,
            'argument' => self::parseArgument($rawArgument),
        ];
    }

    /**
     * @param array{pragma:string,schema:?string,argument:array{kind:string,raw:?string,value:mixed,quoted:bool}} $parsed
     * @param array<string,array{name:string,errors:list<string>,tables:list<string>,target_errors:array<string,list<string>>}> $catalog
     * @return array{source:string,status:string,pragma:string,schema:?string,argument:array{kind:string,raw:?string,value:mixed,quoted:bool},target:?string,limit:int,checked_schemas:list<string>,errors:list<string>,rows:list<array<string,string>>,catchsql:array{0:int,1:mixed},dependencies:list<string>}
     */
    private static function targetResult(array $parsed, array $catalog): array
    {
        $target = (string) $parsed['argument']['value'];
        $schema = $parsed['schema'];
        if ($schema !== null) {
            $targets = self::schemaTargets($schema, $catalog);
            $entry = reset($targets);
            if (strcasecmp($entry['name'], $target) !== 0 && !self::schemaHasTable($entry, $target)) {
                return self::missingTargetResult($parsed, $target);
            }

            $errors = self::targetErrors($entry, $target);

            return self::result($parsed['pragma'], $schema, $parsed['argument'], $target, 100, [$entry['name']], $errors);
        }

        $schemaKey = strtolower($target);
        if (isset($catalog[$schemaKey])) {
            $entry = $catalog[$schemaKey];
            $errors = self::targetErrors($entry, $target);

            return self::result($parsed['pragma'], null, $parsed['argument'], $target, 100, [$entry['name']], $errors);
        }

        $main = $catalog['main'] ?? reset($catalog);
        if (self::schemaHasTable($main, $target)) {
            $errors = self::targetErrors($main, $target);

            return self::result($parsed['pragma'], null, $parsed['argument'], $target, 100, [$main['name']], $errors);
        }

        return self::missingTargetResult($parsed, $target);
    }

    /**
     * @param array{kind:string,raw:?string,value:mixed,quoted:bool} $argument
     * @param list<string> $checked
     * @param list<string> $errors
     * @return array{source:string,status:string,pragma:string,schema:?string,argument:array{kind:string,raw:?string,value:mixed,quoted:bool},target:?string,limit:int,checked_schemas:list<string>,errors:list<string>,rows:list<array<string,string>>,catchsql:array{0:int,1:mixed},dependencies:list<string>}
     */
    private static function result(string $pragma, ?string $schema, array $argument, ?string $target, int $limit, array $checked, array $errors): array
    {
        $rows = $errors === []
            ? [[$pragma => 'ok']]
            : array_map(static fn (string $message): array => [$pragma => $message], $errors);

        return [
            'source' => 'pragma.test pragma-3.5.2 through pragma-3.18',
            'status' => 'ok',
            'pragma' => $pragma,
            'schema' => $schema,
            'argument' => $argument,
            'target' => $target,
            'limit' => $limit,
            'checked_schemas' => $checked,
            'errors' => $errors,
            'rows' => $rows,
            'catchsql' => [0, $errors === [] ? 'ok' : $errors],
            'dependencies' => ['sqlite-pragma-integrity-target-argument'],
        ];
    }

    /**
     * @param array{pragma:string,schema:?string,argument:array{kind:string,raw:?string,value:mixed,quoted:bool}} $parsed
     * @return array{source:string,status:string,pragma:string,schema:?string,argument:array{kind:string,raw:?string,value:mixed,quoted:bool},target:?string,limit:int,checked_schemas:list<string>,errors:list<string>,rows:list<array<string,string>>,catchsql:array{0:int,1:mixed},dependencies:list<string>}
     */
    private static function missingTargetResult(array $parsed, string $target): array
    {
        return [
            'source' => 'pragma.test pragma-3.5.2 through pragma-3.18',
            'status' => 'error',
            'pragma' => $parsed['pragma'],
            'schema' => $parsed['schema'],
            'argument' => $parsed['argument'],
            'target' => $target,
            'limit' => 100,
            'checked_schemas' => [],
            'errors' => ["no such table: {$target}"],
            'rows' => [],
            'catchsql' => [1, "no such table: {$target}"],
            'dependencies' => ['sqlite-pragma-integrity-target-argument'],
        ];
    }

    /**
     * @return array{kind:string,raw:?string,value:mixed,quoted:bool}
     */
    private static function parseArgument(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return ['kind' => 'none', 'raw' => null, 'value' => null, 'quoted' => false];
        }

        $raw = trim($raw);
        $quoted = self::isQuoted($raw);
        $value = self::unquoteIdentifier($raw);
        if (!$quoted && preg_match('/^\d+$/', $value) === 1) {
            $limit = (int) $value;

            return ['kind' => 'limit', 'raw' => $raw, 'value' => $limit <= 0 ? 100 : $limit, 'quoted' => false];
        }

        return ['kind' => 'target', 'raw' => $raw, 'value' => $value, 'quoted' => $quoted];
    }

    /**
     * @param array<string,array{errors?:list<string>,tables?:list<string>,target_errors?:array<string,list<string>>}> $schemas
     * @return array<string,array{name:string,errors:list<string>,tables:list<string>,target_errors:array<string,list<string>>}>
     */
    private static function normalizeSchemas(array $schemas): array
    {
        $normalized = [];
        foreach ($schemas as $name => $entry) {
            if (!is_string($name) || trim($name) === '') {
                throw new InvalidArgumentException('SQLite PRAGMA integrity target argument schema names must be non-empty strings');
            }
            if (!is_array($entry)) {
                throw new InvalidArgumentException('SQLite PRAGMA integrity target argument schema entry must be an array');
            }

            $tables = array_values(array_map('strval', $entry['tables'] ?? ['sqlite_schema']));
            if (!in_array('sqlite_schema', array_map('strtolower', $tables), true)) {
                $tables[] = 'sqlite_schema';
            }

            $targetErrors = [];
            foreach (($entry['target_errors'] ?? []) as $target => $messages) {
                if (!is_array($messages)) {
                    throw new InvalidArgumentException('SQLite PRAGMA integrity target argument target errors must be arrays');
                }
                $targetErrors[strtolower((string) $target)] = array_values(array_map('strval', $messages));
            }

            $normalized[strtolower($name)] = [
                'name' => $name,
                'errors' => array_values(array_map('strval', $entry['errors'] ?? [])),
                'tables' => $tables,
                'target_errors' => $targetErrors,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string,array{name:string,errors:list<string>,tables:list<string>,target_errors:array<string,list<string>>}> $catalog
     * @return array<string,array{name:string,errors:list<string>,tables:list<string>,target_errors:array<string,list<string>>}>
     */
    private static function schemaTargets(?string $schema, array $catalog): array
    {
        if ($schema === null) {
            return $catalog;
        }

        $key = strtolower($schema);
        if (!isset($catalog[$key])) {
            throw new InvalidArgumentException("SQLite PRAGMA integrity target argument schema {$schema} is not attached");
        }

        return [$key => $catalog[$key]];
    }

    /**
     * @param array{name:string,errors:list<string>,tables:list<string>,target_errors:array<string,list<string>>} $entry
     */
    private static function schemaHasTable(array $entry, string $target): bool
    {
        foreach ($entry['tables'] as $table) {
            if (strcasecmp($table, $target) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{name:string,errors:list<string>,tables:list<string>,target_errors:array<string,list<string>>} $entry
     * @return list<string>
     */
    private static function targetErrors(array $entry, string $target): array
    {
        $key = strtolower($target);
        if (isset($entry['target_errors'][$key])) {
            return $entry['target_errors'][$key];
        }

        if (strcasecmp($target, 'sqlite_schema') === 0) {
            return [];
        }

        return $entry['errors'];
    }

    private static function identifierPattern(): string
    {
        return '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*)';
    }

    private static function isQuoted(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];

        return ($first === '"' && $last === '"')
            || ($first === '\'' && $last === '\'')
            || ($first === '`' && $last === '`')
            || ($first === '[' && $last === ']');
    }

    private static function unquoteIdentifier(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];
        if ($first === '"' && $last === '"') {
            return str_replace('""', '"', substr($value, 1, -1));
        }
        if ($first === '\'' && $last === '\'') {
            return str_replace("''", "'", substr($value, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return substr($value, 1, -1);
        }
        if ($first === '`' && $last === '`') {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
