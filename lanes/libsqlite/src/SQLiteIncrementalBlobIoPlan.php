<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIncrementalBlobIoPlan
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param array{database?:string,table:string,column:string,rowid:int,readonly?:bool,foreign_key_columns?:list<string>} $request
     * @return array{status:string,database:string,table:string,column:string,rowid:int,readonly:bool,bytes:int,payload:SQLiteBlobValue,dependencies:list<string>}
     */
    public static function open(array $rows, array $request): array
    {
        $table = self::identifier($request['table'] ?? null, 'table');
        $column = self::identifier($request['column'] ?? null, 'column');
        $rowid = self::rowid($request['rowid'] ?? null);
        $database = self::schema($request['database'] ?? 'main');
        $readonly = (bool) ($request['readonly'] ?? false);
        $foreignKeyColumns = self::foreignKeyColumns($request['foreign_key_columns'] ?? []);
        $row = self::findRow($rows, $rowid);

        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite incremental blob column {$column} is not present");
        }
        if (!$readonly && in_array($column, $foreignKeyColumns, true)) {
            throw new \RuntimeException('SQLite incremental blob cannot open foreign key column for writing');
        }
        if (!$row[$column] instanceof SQLiteBlobValue) {
            throw new \InvalidArgumentException('SQLite incremental blob handles require a BLOB storage value');
        }

        return [
            'status' => 'open',
            'database' => $database,
            'table' => $table,
            'column' => $column,
            'rowid' => $rowid,
            'readonly' => $readonly,
            'bytes' => strlen($row[$column]->bytes),
            'payload' => $row[$column],
            'dependencies' => array_values(array_filter([
                'sqlite3-blob-open',
                'sqlite3-blob-bytes',
                $foreignKeyColumns === [] ? null : 'sqlite-fkey2-incremental-blob-foreign-key-column-guard',
            ])),
        ];
    }

    /**
     * @param array{status:string,rowid:int,payload:SQLiteBlobValue} $handle
     * @return array{status:string,rowid:int,offset:int,amount:int,bytes:SQLiteBlobValue,eof:bool,dependencies:list<string>}
     */
    public static function read(array $handle, int $offset, int $amount): array
    {
        self::assertOpen($handle);
        if ($offset < 0 || $amount < 0) {
            throw new \InvalidArgumentException('SQLite incremental blob read offset and amount must be non-negative');
        }

        $length = strlen($handle['payload']->bytes);
        if ($offset > $length) {
            throw new \InvalidArgumentException('SQLite incremental blob read starts past end of blob');
        }

        $bytes = substr($handle['payload']->bytes, $offset, $amount);

        return [
            'status' => 'read',
            'rowid' => $handle['rowid'],
            'offset' => $offset,
            'amount' => $amount,
            'bytes' => new SQLiteBlobValue($bytes),
            'eof' => $offset + $amount >= $length,
            'dependencies' => ['sqlite3-blob-read'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array{status:string,table:string,column:string,rowid:int,readonly:bool,payload:SQLiteBlobValue} $handle
     * @return array{status:string,rowid:int,offset:int,written:int,rows:list<array<string,mixed>>,payload:SQLiteBlobValue,dependencies:list<string>}
     */
    public static function write(array $rows, array $handle, int $offset, SQLiteBlobValue|string $bytes): array
    {
        self::assertOpen($handle);
        if (($handle['readonly'] ?? false) === true) {
            throw new \RuntimeException('SQLite incremental blob handle is read-only');
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite incremental blob write offset must be non-negative');
        }

        $writeBytes = $bytes instanceof SQLiteBlobValue ? $bytes->bytes : $bytes;
        $payload = $handle['payload']->bytes;
        $end = $offset + strlen($writeBytes);
        if ($end > strlen($payload)) {
            throw new \InvalidArgumentException('SQLite incremental blob writes cannot change blob size');
        }

        $newPayload = substr_replace($payload, $writeBytes, $offset, strlen($writeBytes));
        $updated = false;
        $updatedRows = [];
        foreach ($rows as $row) {
            if (($row['rowid'] ?? $row['id'] ?? null) === $handle['rowid']) {
                $row[$handle['column']] = new SQLiteBlobValue($newPayload);
                $updated = true;
            }
            $updatedRows[] = $row;
        }
        if (!$updated) {
            throw new \InvalidArgumentException("SQLite incremental blob row {$handle['rowid']} is not present");
        }

        return [
            'status' => 'written',
            'rowid' => $handle['rowid'],
            'offset' => $offset,
            'written' => strlen($writeBytes),
            'rows' => $updatedRows,
            'payload' => new SQLiteBlobValue($newPayload),
            'dependencies' => ['sqlite3-blob-write'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array{status:string,database:string,table:string,column:string,readonly:bool} $handle
     * @return array{status:string,database:string,table:string,column:string,rowid:int,readonly:bool,bytes:int,payload:SQLiteBlobValue,dependencies:list<string>}
     */
    public static function reopen(array $rows, array $handle, int $rowid): array
    {
        self::assertOpen($handle);
        $opened = self::open($rows, [
            'database' => $handle['database'],
            'table' => $handle['table'],
            'column' => $handle['column'],
            'rowid' => $rowid,
            'readonly' => $handle['readonly'],
        ]);
        $opened['dependencies'][] = 'sqlite3-blob-reopen';

        return $opened;
    }

    /**
     * @param array{status:string,rowid:int} $handle
     * @return array{status:string,rowid:int,dependencies:list<string>}
     */
    public static function close(array $handle): array
    {
        self::assertOpen($handle);

        return [
            'status' => 'closed',
            'rowid' => $handle['rowid'],
            'dependencies' => ['sqlite3-blob-close'],
        ];
    }

    private static function assertOpen(array $handle): void
    {
        if (($handle['status'] ?? null) !== 'open') {
            throw new \RuntimeException('SQLite incremental blob handle is not open');
        }
        if (!($handle['payload'] ?? null) instanceof SQLiteBlobValue) {
            throw new \RuntimeException('SQLite incremental blob handle payload is missing');
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private static function findRow(array $rows, int $rowid): array
    {
        foreach ($rows as $row) {
            if (($row['rowid'] ?? $row['id'] ?? null) === $rowid) {
                return $row;
            }
        }

        throw new \InvalidArgumentException("SQLite incremental blob row {$rowid} is not present");
    }

    private static function identifier(mixed $value, string $kind): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new \InvalidArgumentException("SQLite incremental blob {$kind} name is invalid");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function foreignKeyColumns(mixed $columns): array
    {
        if (!is_array($columns)) {
            throw new \InvalidArgumentException('SQLite incremental blob foreign key columns must be a list');
        }

        $normalized = [];
        foreach ($columns as $column) {
            $normalized[] = self::identifier($column, 'foreign key column');
        }

        return array_values(array_unique($normalized));
    }

    private static function schema(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new \InvalidArgumentException('SQLite incremental blob schema name is invalid');
        }

        return $value;
    }

    private static function rowid(mixed $value): int
    {
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException('SQLite incremental blob rowid must be a positive integer');
        }

        return $value;
    }
}
