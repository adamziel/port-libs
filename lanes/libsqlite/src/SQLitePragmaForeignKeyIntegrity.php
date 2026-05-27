<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyIntegrity
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,pragma:string,schema:string,target:string|null,rows:list<array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int}>}
     */
    public static function execute(string $sql, array $schemas, ?SQLiteAttachedSchemaCatalog $catalog = null): array
    {
        $parsed = self::parsePragma($sql);
        $schema = $parsed['schema'];
        $target = $parsed['target'];

        if ($schema === null && $target !== null && $catalog !== null) {
            $resolved = $catalog->resolveTable($target);
            $schema = $resolved['schema'] ?? 'main';
        }
        $schema ??= 'main';

        if (!isset($schemas[$schema])) {
            throw new InvalidArgumentException("SQLite foreign_key_check schema {$schema} is not available");
        }

        $rows = [];
        foreach (SQLitePragmaForeignKeyCheck::check($schemas[$schema]['tables'], $schemas[$schema]['foreignKeys'], $target) as $row) {
            $row['schema'] = $schema;
            $rows[] = [
                'schema' => $row['schema'],
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
            ];
        }

        return [
            'status' => 'ok',
            'pragma' => 'foreign_key_check',
            'schema' => $schema,
            'target' => $target,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,pragma:string,schemas:list<string>,rows:list<array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int}>}
     */
    public static function executeAllSchemas(array $schemas): array
    {
        $rows = [];
        foreach (array_values(array_unique(array_merge(['temp', 'main'], array_keys($schemas)))) as $schema) {
            if (!is_string($schema) || !isset($schemas[$schema])) {
                continue;
            }
            foreach (SQLitePragmaForeignKeyCheck::check($schemas[$schema]['tables'], $schemas[$schema]['foreignKeys']) as $row) {
                $rows[] = [
                    'schema' => $schema,
                    'table' => $row['table'],
                    'rowid' => $row['rowid'],
                    'parent' => $row['parent'],
                    'fkid' => $row['fkid'],
                ];
            }
        }

        return [
            'status' => 'ok',
            'pragma' => 'foreign_key_check',
            'schemas' => array_values(array_keys($schemas)),
            'rows' => $rows,
        ];
    }

    /**
     * @return array{schema:string|null,target:string|null}
     */
    private static function parsePragma(string $sql): array
    {
        $trimmed = rtrim(trim($sql), ';');
        if (!preg_match('/^pragma\s+(?:(?<schema>[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?foreign_key_check\s*(?:\(\s*(?<target>(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*))\s*\))?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Only PRAGMA foreign_key_check[(table)] is supported');
        }

        return [
            'schema' => isset($matches['schema']) && $matches['schema'] !== '' ? strtolower($matches['schema']) : null,
            'target' => isset($matches['target']) && $matches['target'] !== '' ? self::unquoteIdentifier($matches['target']) : null,
        ];
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        $first = $identifier[0] ?? '';
        $last = substr($identifier, -1);
        if ($first === '"' && $last === '"') {
            return str_replace('""', '"', substr($identifier, 1, -1));
        }
        if ($first === "'" && $last === "'") {
            return str_replace("''", "'", substr($identifier, 1, -1));
        }
        if (($first === '`' && $last === '`') || ($first === '[' && $last === ']')) {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }
}
