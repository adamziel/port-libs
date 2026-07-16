<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteReturningWritableSchemaPlan
{
    /**
     * @param array<string,mixed> $defaults
     * @return array{schema:string,row:array<string,mixed>,returning:array<string,mixed>,changes:int,source:string}
     */
    public static function insertDefaultValuesReturning(string $schema, string $returning, array $defaults = []): array
    {
        $schema = self::normalizeSchema($schema);
        $returningColumn = self::normalizeReturning($schema, $returning);
        $row = [
            'type' => $defaults['type'] ?? null,
            'name' => $defaults['name'] ?? null,
            'tbl_name' => $defaults['tbl_name'] ?? null,
            'rootpage' => $defaults['rootpage'] ?? null,
            'sql' => $defaults['sql'] ?? null,
        ];

        return [
            'schema' => $schema,
            'row' => $row,
            'returning' => [$returningColumn => $row[$returningColumn]],
            'changes' => 1,
            'source' => $schema === 'sqlite_temp_schema' ? 'returning1.test-21.1' : 'returning1.test-21.0',
        ];
    }

    /**
     * @return array{ok:false,error:string,source:string}
     */
    public static function tempSchemaSubqueryAliasError(string $alias = 'sqlite_master', string $referencedColumn = 'name'): array
    {
        self::assertIdentifier($alias, 'subquery alias');
        self::assertIdentifier($referencedColumn, 'subquery referenced column');

        return [
            'ok' => false,
            'error' => "no such column: {$alias}.{$referencedColumn}",
            'source' => 'returning1.test-22.1',
        ];
    }

    private static function normalizeSchema(string $schema): string
    {
        $lower = strtolower(trim($schema));
        return match ($lower) {
            'main', 'sqlite_schema' => 'sqlite_schema',
            'temp', 'sqlite_temp_schema' => 'sqlite_temp_schema',
            default => throw new InvalidArgumentException('SQLite RETURNING writable schema target must be sqlite_schema or sqlite_temp_schema'),
        };
    }

    private static function normalizeReturning(string $schema, string $returning): string
    {
        $returning = trim($returning);
        if (str_contains($returning, '.')) {
            [$qualifier, $column] = array_map('trim', explode('.', $returning, 2));
            if (self::normalizeSchema($qualifier) !== $schema) {
                throw new InvalidArgumentException('SQLite RETURNING schema qualifier does not match target schema');
            }
        } else {
            $column = $returning;
        }

        self::assertIdentifier($column, 'RETURNING column');
        if (!in_array($column, ['type', 'name', 'tbl_name', 'rootpage', 'sql'], true)) {
            throw new InvalidArgumentException("SQLite RETURNING writable schema column {$column} is missing");
        }

        return $column;
    }

    private static function assertIdentifier(string $identifier, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException("SQLite RETURNING invalid {$label}");
        }
    }
}
