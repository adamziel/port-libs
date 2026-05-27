<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachedSchemaCatalog
{
    /** @var array<string,array{name: string, file: string|null, records: list<SQLiteSchemaRecord>, sequence: int}> */
    private array $schemas = [];

    /** @var list<string> */
    private array $attachedOrder = [];

    /**
     * @param list<SQLiteSchemaRecord> $mainRecords
     * @param list<SQLiteSchemaRecord> $tempRecords
     */
    public function __construct(array $mainRecords = [], array $tempRecords = [])
    {
        $this->schemas['main'] = [
            'name' => 'main',
            'file' => null,
            'records' => $mainRecords,
            'sequence' => 0,
        ];
        $this->schemas['temp'] = [
            'name' => 'temp',
            'file' => '',
            'records' => $tempRecords,
            'sequence' => 1,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    public function attach(string $schemaName, string $fileName, array $records): void
    {
        $name = self::normalizeSchemaName($schemaName);
        if ($name === 'main' || $name === 'temp') {
            throw new \InvalidArgumentException('SQLite ATTACH schema name cannot be main or temp');
        }
        if (isset($this->schemas[$name])) {
            throw new \InvalidArgumentException("SQLite ATTACH schema {$name} is already in use");
        }

        $this->attachedOrder[] = $name;
        $this->schemas[$name] = [
            'name' => $name,
            'file' => $fileName,
            'records' => array_values($records),
            'sequence' => count($this->attachedOrder) + 1,
        ];
    }

    public function detach(string $schemaName): void
    {
        $name = self::normalizeSchemaName($schemaName);
        if ($name === 'main' || $name === 'temp') {
            throw new \InvalidArgumentException('SQLite DETACH cannot detach main or temp');
        }
        if (!isset($this->schemas[$name])) {
            throw new \InvalidArgumentException("SQLite DETACH schema {$name} is not attached");
        }

        unset($this->schemas[$name]);
        $this->attachedOrder = array_values(array_filter(
            $this->attachedOrder,
            static fn (string $attached): bool => $attached !== $name,
        ));

        foreach ($this->attachedOrder as $index => $attached) {
            $this->schemas[$attached]['sequence'] = $index + 2;
        }
    }

    /**
     * @return list<array{seq: int, name: string, file: string|null}>
     */
    public function databaseList(): array
    {
        $rows = [
            ['seq' => 0, 'name' => 'main', 'file' => $this->schemas['main']['file']],
            ['seq' => 1, 'name' => 'temp', 'file' => $this->schemas['temp']['file']],
        ];

        foreach ($this->attachedOrder as $attached) {
            $schema = $this->schemas[$attached];
            $rows[] = ['seq' => $schema['sequence'], 'name' => $schema['name'], 'file' => $schema['file']];
        }

        return $rows;
    }

    /**
     * @return array{schema: string, record: SQLiteSchemaRecord}|null
     */
    public function resolveTable(string $name): ?array
    {
        return $this->resolveObject($name, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'table' || $record->type === 'view');
    }

    /**
     * @return array{schema: string, record: SQLiteSchemaRecord}|null
     */
    public function resolveIndex(string $name): ?array
    {
        return $this->resolveObject($name, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'index');
    }

    /**
     * @return list<SQLiteSchemaRecord>
     */
    public function schemaRecords(string $schemaName): array
    {
        $name = self::normalizeSchemaName($schemaName);
        if (!isset($this->schemas[$name])) {
            throw new \InvalidArgumentException("SQLite schema {$name} is not attached");
        }

        return $this->schemas[$name]['records'];
    }

    public function pragmaCatalog(string $schemaName): SQLitePragmaSchemaCatalog
    {
        return new SQLitePragmaSchemaCatalog($this->schemaRecords($schemaName));
    }

    /**
     * @return list<string>
     */
    public function searchOrder(): array
    {
        return array_merge(['temp', 'main'], $this->attachedOrder);
    }

    /**
     * @return array{schema: string, name: string}
     */
    private static function splitQualifiedName(string $name): array
    {
        $parts = preg_split('/\s*\.\s*/', trim($name), 2);
        if ($parts === false || $parts === []) {
            throw new \InvalidArgumentException('SQLite schema object name cannot be empty');
        }
        if (count($parts) === 1) {
            return ['schema' => '', 'name' => self::unquoteIdentifier($parts[0])];
        }

        return ['schema' => self::normalizeSchemaName($parts[0]), 'name' => self::unquoteIdentifier($parts[1])];
    }

    /**
     * @return array{schema: string, record: SQLiteSchemaRecord}|null
     */
    private function resolveObject(string $name, callable $accept): ?array
    {
        $qualified = self::splitQualifiedName($name);
        $schemas = $qualified['schema'] !== '' ? [$qualified['schema']] : $this->searchOrder();

        foreach ($schemas as $schemaName) {
            if (!isset($this->schemas[$schemaName])) {
                throw new \InvalidArgumentException("SQLite schema {$schemaName} is not attached");
            }
            foreach ($this->schemas[$schemaName]['records'] as $record) {
                if ($record->name === $qualified['name'] && $accept($record)) {
                    return ['schema' => $schemaName, 'record' => $record];
                }
            }
        }

        return null;
    }

    private static function normalizeSchemaName(string $name): string
    {
        $normalized = strtolower(self::unquoteIdentifier($name));
        if ($normalized === '') {
            throw new \InvalidArgumentException('SQLite schema name cannot be empty');
        }

        return $normalized;
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return '';
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
}
