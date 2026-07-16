<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteSchemaBulkImportPlan
{
    /**
     * @param array<string, array{sql?:string, type?:string}> $existingObjects
     * @return array{
     *     status:string,
     *     statement_count:int,
     *     applied_count:int,
     *     skipped_count:int,
     *     schema_version_before:int,
     *     schema_version_after:int,
     *     data_version_before:int,
     *     data_version_after:int,
     *     transaction:array{begin:string,commit:string,atomic:bool},
     *     objects:list<array{name:string,type:string,table:string|null,sql:string,rootpage:int,autoindex_count:int,dependencies:list<string>}>,
     *     skipped:list<array{name:string,type:string,reason:string}>,
     *     warnings:list<array{statement:string,reason:string}>,
     *     ordered_names:list<string>,
     *     dependencies:list<string>
     * }
     */
    public static function plan(string $dumpSql, array $existingObjects = [], array $options = []): array
    {
        $schemaVersion = self::nonNegativeInt($options['schema_version'] ?? 0, 'schema_version');
        $dataVersion = self::nonNegativeInt($options['data_version'] ?? 1, 'data_version');
        $nextRootPage = max(2, self::nonNegativeInt($options['next_rootpage'] ?? 2, 'next_rootpage'));
        $ifNotExists = (bool) ($options['honor_if_not_exists'] ?? true);
        $statements = self::splitStatements($dumpSql);
        $existing = self::normalizeExisting($existingObjects);
        $objects = [];
        $skipped = [];
        $warnings = [];

        foreach ($statements as $statement) {
            $parsed = self::parseSchemaStatement($statement);
            if ($parsed === null) {
                $warnings[] = [
                    'statement' => $statement,
                    'reason' => 'unsupported_or_non_schema_statement',
                ];
                continue;
            }

            $key = strtolower($parsed['name']);
            if (isset($existing[$key]) || self::containsObject($objects, $key)) {
                if ($parsed['if_not_exists'] && $ifNotExists) {
                    $skipped[] = [
                        'name' => $parsed['name'],
                        'type' => $parsed['type'],
                        'reason' => 'already_exists_if_not_exists',
                    ];
                    continue;
                }

                throw new InvalidArgumentException("Schema object already exists: {$parsed['name']}");
            }

            $autoindexCount = 0;
            $dependencies = ['sqlite-schema-bulk-import'];
            if ($parsed['type'] === 'table') {
                $autoindexCount = count(SQLiteCreateTable::automaticIndexColumnMetadata($parsed['sql']));
                if ($autoindexCount > 0) {
                    $dependencies[] = 'sqlite-create-table-autoindex';
                }
            }
            if ($parsed['type'] === 'index') {
                $columns = SQLiteCreateIndex::columns($parsed['sql']);
                if ($columns !== null && $columns !== []) {
                    $dependencies[] = 'sqlite-create-index-columns';
                }
            }

            $objects[] = [
                'name' => $parsed['name'],
                'type' => $parsed['type'],
                'table' => $parsed['table'],
                'sql' => $parsed['sql'],
                'rootpage' => in_array($parsed['type'], ['table', 'index'], true) ? $nextRootPage++ : 0,
                'autoindex_count' => $autoindexCount,
                'dependencies' => $dependencies,
            ];
        }

        $objects = self::dependencyOrder($objects);
        $applied = count($objects);

        return [
            'status' => 'ok',
            'statement_count' => count($statements),
            'applied_count' => $applied,
            'skipped_count' => count($skipped),
            'schema_version_before' => $schemaVersion,
            'schema_version_after' => $schemaVersion + $applied,
            'data_version_before' => $dataVersion,
            'data_version_after' => $dataVersion + ($applied > 0 ? 1 : 0),
            'transaction' => [
                'begin' => 'BEGIN IMMEDIATE',
                'commit' => 'COMMIT',
                'atomic' => true,
            ],
            'objects' => $objects,
            'skipped' => $skipped,
            'warnings' => $warnings,
            'ordered_names' => array_column($objects, 'name'),
            'dependencies' => [
                'sqlite-schema-bulk-import',
                'sqlite-create-table-autoindex',
                'sqlite-create-index-columns',
                'sqlite-schema-cookie-update',
            ],
        ];
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
     * @return array{name:string,type:string,table:string|null,sql:string,if_not_exists:bool}|null
     */
    private static function parseSchemaStatement(string $statement): ?array
    {
        $statement = self::stripLeadingComments($statement);
        $normalized = preg_replace('/\s+/', ' ', trim($statement));
        if (!is_string($normalized)) {
            return null;
        }

        if (preg_match('/^CREATE\s+(TEMP(?:ORARY)?\s+)?TABLE\s+(IF\s+NOT\s+EXISTS\s+)?(?<name>"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s*\(/i', $normalized, $matches) === 1) {
            return [
                'name' => self::unquoteIdentifier($matches['name']),
                'type' => 'table',
                'table' => null,
                'sql' => $statement,
                'if_not_exists' => ($matches[2] ?? '') !== '',
            ];
        }

        if (preg_match('/^CREATE\s+(UNIQUE\s+)?INDEX\s+(IF\s+NOT\s+EXISTS\s+)?(?<name>"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s+ON\s+(?<table>"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s*\(/i', $normalized, $matches) === 1) {
            return [
                'name' => self::unquoteIdentifier($matches['name']),
                'type' => 'index',
                'table' => self::unquoteIdentifier($matches['table']),
                'sql' => $statement,
                'if_not_exists' => ($matches[2] ?? '') !== '',
            ];
        }

        if (preg_match('/^CREATE\s+TRIGGER\s+(IF\s+NOT\s+EXISTS\s+)?(?<name>"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s+/i', $normalized, $matches) === 1) {
            return [
                'name' => self::unquoteIdentifier($matches['name']),
                'type' => 'trigger',
                'table' => self::triggerTable($normalized),
                'sql' => $statement,
                'if_not_exists' => ($matches[1] ?? '') !== '',
            ];
        }

        if (preg_match('/^CREATE\s+VIEW\s+(IF\s+NOT\s+EXISTS\s+)?(?<name>"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s+/i', $normalized, $matches) === 1) {
            return [
                'name' => self::unquoteIdentifier($matches['name']),
                'type' => 'view',
                'table' => null,
                'sql' => $statement,
                'if_not_exists' => ($matches[1] ?? '') !== '',
            ];
        }

        return null;
    }

    private static function triggerTable(string $sql): ?string
    {
        if (preg_match('/\sON\s+(?<table>"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s/i', $sql, $matches) !== 1) {
            return null;
        }

        return self::unquoteIdentifier($matches['table']);
    }

    private static function insideCreateTriggerBody(string $statement): bool
    {
        $normalized = preg_replace('/\s+/', ' ', self::stripLeadingComments($statement));
        if (!is_string($normalized) || preg_match('/^CREATE\s+TRIGGER\s+/i', trim($normalized)) !== 1) {
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

    /**
     * @param list<array{name:string,type:string,table:string|null,sql:string,rootpage:int,autoindex_count:int,dependencies:list<string>}> $objects
     * @return list<array{name:string,type:string,table:string|null,sql:string,rootpage:int,autoindex_count:int,dependencies:list<string>}>
     */
    private static function dependencyOrder(array $objects): array
    {
        $weight = static fn (array $object): int => match ($object['type']) {
            'table' => 0,
            'index' => 1,
            'view' => 2,
            'trigger' => 3,
            default => 9,
        };

        usort($objects, static function (array $a, array $b) use ($weight): int {
            return $weight($a) <=> $weight($b) ?: strcmp($a['name'], $b['name']);
        });

        return array_values($objects);
    }

    /**
     * @param array<string, array{sql?:string, type?:string}> $existingObjects
     * @return array<string, true>
     */
    private static function normalizeExisting(array $existingObjects): array
    {
        $existing = [];
        foreach ($existingObjects as $name => $_object) {
            $normalized = strtolower(trim((string) $name));
            if ($normalized !== '') {
                $existing[$normalized] = true;
            }
        }

        return $existing;
    }

    private static function containsObject(array $objects, string $lowerName): bool
    {
        foreach ($objects as $object) {
            if (strtolower($object['name']) === $lowerName) {
                return true;
            }
        }

        return false;
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
