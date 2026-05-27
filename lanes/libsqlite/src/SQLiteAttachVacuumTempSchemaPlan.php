<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachVacuumTempSchemaPlan
{
    /**
     * @param array<string, SQLiteDatabase> $databases
     * @return array{
     *     status:string,
     *     schema:string,
     *     source_file:string|null,
     *     target_path:string|null,
     *     source_page_size:int,
     *     target_page_size:int,
     *     source_page_count:int,
     *     target_page_count:int,
     *     source_auto_vacuum:string,
     *     target_auto_vacuum:string,
     *     temp_schema_preserved:bool,
     *     schema_generation:int,
     *     cache_invalidated:bool,
     *     database_list:list<array{seq:int,name:string,file:string|null}>,
     *     operations:list<array<string,mixed>>,
     *     dependencies:list<string>
     * }
     */
    public static function planSql(
        string $sql,
        SQLiteAttachedSchemaCatalog $catalog,
        array $databases,
        int|string|null $pageSize = null,
        int|string|null $autoVacuum = null,
    ): array {
        $parsed = self::parseVacuumSql($sql);
        $schema = $parsed['schema'] ?? 'main';
        $databaseList = $catalog->databaseList();
        $sourceFile = self::sourceFile($databaseList, $schema);
        $database = self::databaseForSchema($databases, $schema);

        if ($parsed['into'] !== null) {
            $vacuum = SQLiteVacuumBackupSerializePlan::vacuumInto(
                $database,
                $parsed['into'],
                true,
                $pageSize,
                $autoVacuum,
            );
            $operations = $vacuum['operations'];
            $targetPageSize = $vacuum['page_size'];
            $targetPageCount = $vacuum['page_count'];
            $sourceAutoVacuum = $vacuum['source_auto_vacuum'];
            $targetAutoVacuum = $vacuum['target_auto_vacuum'];
        } else {
            $vacuum = SQLiteVacuumPageSizeAutoVacuumPlan::plan($database, $pageSize, $autoVacuum);
            $operations = [
                [
                    'op' => 'replace_schema_database_image',
                    'schema' => $schema,
                    'file' => $sourceFile,
                    'bytes' => strlen($vacuum['bytes']),
                    'reason' => 'vacuum_attached_schema_in_place',
                ],
                ...$vacuum['operations'],
            ];
            $targetPageSize = $vacuum['target_page_size'];
            $targetPageCount = $vacuum['target_page_count'];
            $sourceAutoVacuum = $vacuum['source_auto_vacuum'];
            $targetAutoVacuum = $vacuum['target_auto_vacuum'];
        }

        return [
            'status' => 'ready',
            'schema' => $schema,
            'source_file' => $sourceFile,
            'target_path' => $parsed['into'],
            'source_page_size' => $database->header->pageSize,
            'target_page_size' => $targetPageSize,
            'source_page_count' => $database->pageCount(),
            'target_page_count' => $targetPageCount,
            'source_auto_vacuum' => $sourceAutoVacuum,
            'target_auto_vacuum' => $targetAutoVacuum,
            'temp_schema_preserved' => true,
            'schema_generation' => $catalog->schemaGeneration(),
            'cache_invalidated' => false,
            'database_list' => $databaseList,
            'operations' => $operations,
            'dependencies' => ['sqlite-vacuum-attached-schema', 'sqlite-temp-schema-preservation'],
        ];
    }

    /**
     * @return array{schema:string|null,into:string|null}
     */
    private static function parseVacuumSql(string $sql): array
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        if (preg_match('/\Avacuum(?:\s+(.+))?\z/is', $trimmed, $matches) !== 1) {
            throw new \InvalidArgumentException('SQLite VACUUM plan requires a VACUUM statement');
        }

        $tail = trim($matches[1] ?? '');
        $schema = null;
        $into = null;
        if ($tail !== '' && preg_match('/\Ainto\s+(.+)\z/is', $tail, $intoMatches) === 1) {
            $into = self::parseStringOrPath(trim($intoMatches[1]));
        } elseif ($tail !== '' && preg_match('/\A(.+?)\s+into\s+(.+)\z/is', $tail, $intoMatches) === 1) {
            $schema = self::normalizeSchemaName($intoMatches[1]);
            $into = self::parseStringOrPath(trim($intoMatches[2]));
        } elseif ($tail !== '') {
            $schema = self::normalizeSchemaName($tail);
        }

        if ($schema !== null) {
            if ($schema === 'temp') {
                throw new \InvalidArgumentException('SQLite VACUUM cannot target the temp schema');
            }
        }

        return ['schema' => $schema, 'into' => $into];
    }

    /**
     * @param list<array{seq:int,name:string,file:string|null}> $databaseList
     */
    private static function sourceFile(array $databaseList, string $schema): ?string
    {
        foreach ($databaseList as $row) {
            if ($row['name'] === $schema) {
                return $row['file'];
            }
        }

        throw new \InvalidArgumentException("SQLite VACUUM schema {$schema} is not attached");
    }

    /**
     * @param array<string, SQLiteDatabase> $databases
     */
    private static function databaseForSchema(array $databases, string $schema): SQLiteDatabase
    {
        if (!isset($databases[$schema])) {
            throw new \InvalidArgumentException("SQLite VACUUM database image for schema {$schema} is not available");
        }

        return $databases[$schema];
    }

    private static function normalizeSchemaName(string $name): string
    {
        $name = trim($name);
        if (str_contains(strtolower($name), ' into ')) {
            throw new \InvalidArgumentException('SQLite VACUUM schema name is malformed');
        }

        return strtolower(self::unquoteIdentifier($name));
    }

    private static function parseStringOrPath(string $value): string
    {
        $quote = $value[0] ?? '';
        if (($quote === "'" || $quote === '"') && substr($value, -1) === $quote) {
            $body = substr($value, 1, -1);
            $value = str_replace($quote . $quote, $quote, $body);
        } elseif (preg_match('/\A[A-Za-z0-9_\/.\-]+\z/', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite VACUUM INTO target must be a bounded string literal or path token');
        }
        if ($value === '') {
            throw new \InvalidArgumentException('SQLite VACUUM INTO target path cannot be empty');
        }

        return $value;
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new \InvalidArgumentException('SQLite VACUUM schema name cannot be empty');
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
}
