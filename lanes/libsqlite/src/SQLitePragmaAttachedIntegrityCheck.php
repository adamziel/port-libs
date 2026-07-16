<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaAttachedIntegrityCheck
{
    /**
     * @param array<string,string|SQLiteDatabase> $databases
     * @return array{pragma:string,schema:string|null,limit:int,rows:list<array<string,string>>,errors:list<string>,checked_schemas:list<string>}
     */
    public static function execute(string $sql, array $databases): array
    {
        if ($databases === []) {
            throw new InvalidArgumentException('SQLite attached integrity check requires at least one database image');
        }

        [$schema, $pragma, $limit] = self::parsePragmaSql($sql);
        $normalized = [];
        foreach ($databases as $name => $database) {
            if (!is_string($name) || $name === '') {
                throw new InvalidArgumentException('SQLite attached integrity check schema names must be non-empty strings');
            }
            $normalized[strtolower($name)] = ['name' => $name, 'database' => $database];
        }

        if ($schema !== null && !isset($normalized[strtolower($schema)])) {
            throw new InvalidArgumentException("SQLite attached integrity check schema {$schema} is not attached");
        }

        $targets = $schema === null
            ? $normalized
            : [strtolower($schema) => $normalized[strtolower($schema)]];

        $errors = [];
        $checked = [];
        foreach ($targets as $entry) {
            $checked[] = $entry['name'];
            $result = SQLitePragmaIntegrityCheck::execute("PRAGMA {$pragma}({$limit})", $entry['database']);
            foreach ($result['errors'] as $message) {
                $errors[] = "*** in database {$entry['name']} ***\n{$message}";
            }
        }

        $rows = $errors === []
            ? [[$pragma => 'ok']]
            : array_map(static fn (string $error): array => [$pragma => $error], $errors);

        return [
            'pragma' => $pragma,
            'schema' => $schema,
            'limit' => $limit,
            'rows' => $rows,
            'errors' => $errors,
            'checked_schemas' => $checked,
        ];
    }

    /**
     * @return array{0:string|null,1:'integrity_check'|'quick_check',2:int}
     */
    private static function parsePragmaSql(string $sql): array
    {
        $trimmed = trim(rtrim(trim($sql), ';'));
        $identifier = '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*)';
        if (!preg_match('/^PRAGMA\s+(?:(?<schema>' . $identifier . ')\s*\.\s*)?(?<pragma>integrity_check|quick_check)(?:\s*(?:\(\s*(?<paren>\d+)\s*\)|=\s*(?<equals>\d+)))?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Unsupported SQLite attached integrity PRAGMA SQL');
        }

        $limit = 100;
        if (($matches['paren'] ?? '') !== '') {
            $limit = (int) $matches['paren'];
        } elseif (($matches['equals'] ?? '') !== '') {
            $limit = (int) $matches['equals'];
        }

        $schema = null;
        if (($matches['schema'] ?? '') !== '') {
            $schema = self::unquoteIdentifier($matches['schema']);
        }

        return [$schema, strtolower($matches['pragma']), max(1, $limit)];
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return $identifier;
        }

        $first = $identifier[0];
        $last = $identifier[strlen($identifier) - 1];
        if ($first === '"' && $last === '"') {
            return str_replace('""', '"', substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return substr($identifier, 1, -1);
        }
        if ($first === '`' && $last === '`') {
            return substr($identifier, 1, -1);
        }
        if ($first === "'" && $last === "'") {
            return str_replace("''", "'", substr($identifier, 1, -1));
        }

        return $identifier;
    }
}
