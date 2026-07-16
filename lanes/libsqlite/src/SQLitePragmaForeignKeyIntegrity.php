<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyIntegrity
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,pragma:string,schema:string,target_schema:string,target:string|null,target_source:string,rows:list<array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int}>}
     */
    public static function execute(string $sql, array $schemas, ?SQLiteAttachedSchemaCatalog $catalog = null): array
    {
        $parsed = self::parsePragma($sql);

        return self::executeParsed($parsed, $schemas, $catalog);
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,pragma:string,schema:string,target_schema:string,target:string|null,target_source:string,rows:list<array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int}>}
     */
    public static function executeTableValued(string $sql, array $schemas, ?SQLiteAttachedSchemaCatalog $catalog = null): array
    {
        $parsed = self::parseTableValuedPragma($sql);

        return self::executeParsed($parsed, $schemas, $catalog);
    }

    /**
     * @param array{schema:string|null,target_schema:string|null,target:string|null} $parsed
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,pragma:string,schema:string,target_schema:string,target:string|null,target_source:string,rows:list<array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int}>}
     */
    private static function executeParsed(array $parsed, array $schemas, ?SQLiteAttachedSchemaCatalog $catalog = null): array
    {
        $schema = $parsed['schema'];
        $target = $parsed['target'];
        $targetSchema = $parsed['target_schema'];
        $targetSource = 'default';

        if ($targetSchema !== null) {
            if ($schema !== null && $schema !== $targetSchema) {
                throw new InvalidArgumentException('SQLite foreign_key_check target schema does not match PRAGMA schema');
            }
            $schema = $targetSchema;
            $targetSource = 'qualified-target';
        } elseif ($schema === null && $target !== null && $catalog !== null) {
            $resolved = $catalog->resolveTable($target);
            if ($resolved !== null) {
                $schema = $resolved['schema'];
                $targetSource = 'catalog-current';
            }
        }
        if ($schema === null) {
            $schema = 'main';
        } elseif ($targetSource === 'default') {
            $targetSource = 'pragma-schema';
        }

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
            'target_schema' => $schema,
            'target' => $target,
            'target_source' => $targetSource,
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
     * @return array{schema:string|null,target_schema:string|null,target:string|null}
     */
    private static function parsePragma(string $sql): array
    {
        $trimmed = rtrim(trim($sql), ';');
        $identifier = self::identifierPattern();
        if (!preg_match('/^pragma\s+(?:(?<schema>' . $identifier . ')\s*\.\s*)?foreign_key_check\s*(?:\(\s*(?<target>.+?)\s*\))?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Only PRAGMA foreign_key_check[(table)] is supported');
        }

        $target = null;
        $targetSchema = null;
        if (isset($matches['target']) && $matches['target'] !== '') {
            [$targetSchema, $target] = self::splitTargetIdentifier($matches['target']);
        }

        return [
            'schema' => isset($matches['schema']) && $matches['schema'] !== '' ? self::normalizeSchemaIdentifier($matches['schema']) : null,
            'target_schema' => $targetSchema,
            'target' => $target,
        ];
    }

    /**
     * @return array{schema:string|null,target_schema:string|null,target:string|null}
     */
    private static function parseTableValuedPragma(string $sql): array
    {
        $trimmed = rtrim(trim($sql), ';');
        $identifier = self::identifierPattern();
        if (!preg_match('/^(?:select\s+\*\s+from\s+)?(?:(?<schema>' . $identifier . ')\s*\.\s*)?pragma_foreign_key_check\s*(?:\(\s*(?<target>.*?)\s*\))?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Only pragma_foreign_key_check[(table)] table-valued calls are supported');
        }

        $target = null;
        $targetSchema = null;
        if (isset($matches['target']) && trim($matches['target']) !== '') {
            [$targetSchema, $target] = self::splitTargetIdentifier($matches['target']);
        }

        return [
            'schema' => isset($matches['schema']) && $matches['schema'] !== '' ? self::normalizeSchemaIdentifier($matches['schema']) : null,
            'target_schema' => $targetSchema,
            'target' => $target,
        ];
    }

    /**
     * @return array{0:string|null,1:string}
     */
    private static function splitTargetIdentifier(string $identifier): array
    {
        $parts = self::splitQualifiedIdentifier($identifier);
        if (count($parts) === 1) {
            $target = self::unquoteIdentifier($parts[0]);
            if (str_contains($target, '.')) {
                $qualified = self::splitQualifiedIdentifier($target);
                if (count($qualified) === 2) {
                    $schema = strtolower(self::unquoteIdentifier($qualified[0]));
                    $target = self::unquoteIdentifier($qualified[1]);
                    self::validateIdentifier($schema, 'target schema');
                    self::validateIdentifier($target, 'target table');

                    return [$schema, $target];
                }
            }
            self::validateIdentifier($target, 'target table');

            return [null, $target];
        }
        if (count($parts) === 2) {
            $schema = strtolower(self::unquoteIdentifier($parts[0]));
            $target = self::unquoteIdentifier($parts[1]);
            self::validateIdentifier($schema, 'target schema');
            self::validateIdentifier($target, 'target table');

            return [$schema, $target];
        }

        throw new InvalidArgumentException('SQLite foreign_key_check target identifier is malformed');
    }

    /**
     * @return list<string>
     */
    private static function splitQualifiedIdentifier(string $identifier): array
    {
        $parts = [];
        $buffer = '';
        $quote = null;
        $length = strlen($identifier);

        for ($i = 0; $i < $length; $i++) {
            $char = $identifier[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($quote === ']' && $char === ']') {
                    $quote = null;
                    continue;
                }
                if ($quote !== ']' && $char === $quote) {
                    if ($i + 1 < $length && $identifier[$i + 1] === $quote && ($quote === '"' || $quote === "'")) {
                        $buffer .= $identifier[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                $buffer .= $char;
                continue;
            }
            if ($char === '.') {
                $parts[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }

        if ($quote !== null) {
            throw new InvalidArgumentException('SQLite foreign_key_check target identifier has unterminated quote');
        }
        $parts[] = trim($buffer);

        return $parts;
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

    private static function identifierPattern(): string
    {
        return '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*)';
    }

    private static function normalizeSchemaIdentifier(string $identifier): string
    {
        $schema = strtolower(self::unquoteIdentifier($identifier));
        self::validateIdentifier($schema, 'target schema');

        return $schema;
    }

    private static function validateIdentifier(string $identifier, string $label): void
    {
        if ($label === 'target schema' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z0-9_]+)*$/', $identifier) === 1) {
            return;
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException("SQLite foreign_key_check {$label} is malformed");
        }
    }
}
