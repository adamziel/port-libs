<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWordPressJsonSchemaWalSavepointPlan
{
    /**
     * @param list<array{name:string,type:string,sql:string,rootpage?:int}> $schema
     * @param list<array{name:string,sql:string,page_number?:int,wal_frame_index?:int,release?:bool,fail?:bool}> $steps
     * @param array{schema_cookie?:int,data_version?:int,page_size?:int,database_bytes?:string,savepoint?:string,transaction?:string} $options
     * @return array<string,mixed>
     */
    public static function plan(array $schema, array $steps, array $options = []): array
    {
        $pageSize = (int) ($options['page_size'] ?? 512);
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite WordPress JSON schema WAL savepoint page size must be a power of two at least 512');
        }

        $schemaCookie = self::positiveInt($options['schema_cookie'] ?? 1, 'schema cookie');
        $dataVersion = self::positiveInt($options['data_version'] ?? 1, 'data version');
        $transaction = self::nonEmptyString($options['transaction'] ?? 'wordpress_json_schema_import', 'transaction');
        $savepoint = self::nonEmptyString($options['savepoint'] ?? 'json_schema_batch', 'savepoint');

        $currentSchema = self::normalizeSchema($schema);
        $database = (string) ($options['database_bytes'] ?? self::databaseImage($pageSize, 3));
        if ($database === '' || strlen($database) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WordPress JSON schema WAL savepoint database image must be aligned to page size');
        }

        $savepoints = new SQLiteSavepointStack();
        $savepoints->beginTransaction($transaction);
        $savepoints->savepoint($savepoint);

        $applied = [];
        $failed = [];
        $released = [];
        $nextFrame = 1;
        $workingSchema = $currentSchema;
        $workingDatabase = $database;
        $workingCookie = $schemaCookie;
        $workingDataVersion = $dataVersion;

        foreach ($steps as $index => $step) {
            $name = self::nonEmptyString($step['name'] ?? ('schema_step_' . ($index + 1)), 'step name');
            $sql = self::nonEmptyString($step['sql'] ?? null, 'step sql');
            $pageNumber = self::positiveInt($step['page_number'] ?? 1, 'page number');
            $frame = self::walFrame($step['wal_frame_index'] ?? $nextFrame, $nextFrame);
            $beforeImage = self::pageFromDatabase($workingDatabase, $pageSize, $pageNumber) ?? str_repeat("\0", $pageSize);

            $savepoints->beginStatementJournal($name);
            $savepoints->recordStatementPageImageWrite($name, $pageNumber, $beforeImage);
            $savepoints->recordStatementWalFrameWrite($name, $frame, $pageNumber, true);

            try {
                if (($step['fail'] ?? false) === true) {
                    throw new \LogicException("SQLite schema step failed: {$name}");
                }

                $record = self::schemaRecord($step, $sql);
                $savepoints->recordPageImageWrite($pageNumber, $beforeImage);
                $workingSchema[$record['name']] = $record;
                $workingCookie++;
                $workingDataVersion++;
                $workingDatabase = self::writePage(
                    $workingDatabase,
                    $pageSize,
                    $pageNumber,
                    self::pageImage($pageSize, $pageNumber, $name, $workingCookie, $workingDataVersion)
                );

                $applied[] = [
                    'name' => $name,
                    'sql' => $sql,
                    'page_number' => $pageNumber,
                    'wal_frame_index' => $frame,
                    'schema_cookie' => $workingCookie,
                    'data_version' => $workingDataVersion,
                    'schema_names' => array_keys($workingSchema),
                ];

                if (($step['release'] ?? false) === true) {
                    $released[] = $savepoints->releaseWithPlan($savepoint);
                    $savepoints->savepoint($savepoint);
                }
            } catch (\Throwable $exception) {
                $workingDatabase = $savepoints->rollbackStatementDatabaseImage($name, $workingDatabase, $pageSize);
                $rollback = $savepoints->rollbackStatementOnErrorWithPlan($name, $pageSize);
                $failed[] = [
                    'name' => $name,
                    'sql' => $sql,
                    'error' => $exception->getMessage(),
                    'rollback' => $rollback,
                    'schema_cookie' => $workingCookie,
                    'data_version' => $workingDataVersion,
                    'schema_names' => array_keys($workingSchema),
                ];
            }
        }

        $rollbackPlan = $savepoints->rollbackToImagePlan($savepoint, $pageSize);
        $walRollback = $savepoints->walRollbackToPlan($savepoint);
        $commitPlan = $savepoints->commitPlan();

        return [
            'status' => $failed === [] ? 'ready' : 'partial_rollback',
            'transaction' => $transaction,
            'savepoint' => $savepoint,
            'page_size' => $pageSize,
            'initial_schema_cookie' => $schemaCookie,
            'final_schema_cookie' => $workingCookie,
            'initial_data_version' => $dataVersion,
            'final_data_version' => $workingDataVersion,
            'initial_schema_names' => array_keys($currentSchema),
            'final_schema_names' => array_keys($workingSchema),
            'applied' => $applied,
            'failed' => $failed,
            'released_savepoints' => $released,
            'database_bytes' => $workingDatabase,
            'database_changed' => $workingDatabase !== $database,
            'rollback_to_savepoint' => $rollbackPlan,
            'wal_rollback_to_savepoint' => $walRollback,
            'commit' => $commitPlan,
            'savepoint_state' => $savepoints->toArray(),
            'dependencies' => [
                'sqlite-schema-cookie-current',
                'sqlite-wal-savepoint-current',
                'sqlite-wordpress-json-schema-wal-savepoint',
            ],
        ];
    }

    /**
     * @param list<array{name:string,type:string,sql:string,rootpage?:int}> $schema
     * @return array<string,array{name:string,type:string,sql:string,rootpage:int}>
     */
    private static function normalizeSchema(array $schema): array
    {
        $records = [];
        foreach ($schema as $record) {
            $name = self::nonEmptyString($record['name'] ?? null, 'schema name');
            if (isset($records[$name])) {
                throw new \InvalidArgumentException("Duplicate SQLite schema object: {$name}");
            }
            $records[$name] = self::schemaRecord($record, self::nonEmptyString($record['sql'] ?? null, 'schema sql'));
        }

        return $records;
    }

    /**
     * @param array<string,mixed> $record
     * @return array{name:string,type:string,sql:string,rootpage:int}
     */
    private static function schemaRecord(array $record, string $sql): array
    {
        $name = self::nonEmptyString($record['name'] ?? null, 'schema name');
        $type = strtolower(self::nonEmptyString($record['type'] ?? 'table', 'schema type'));
        if (!in_array($type, ['table', 'index', 'trigger', 'view'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite schema object type: {$type}");
        }

        return [
            'name' => $name,
            'type' => $type,
            'sql' => $sql,
            'rootpage' => self::positiveInt($record['rootpage'] ?? 0, 'schema rootpage', true),
        ];
    }

    private static function positiveInt(mixed $value, string $label, bool $allowZero = false): int
    {
        if (!is_int($value) || ($allowZero ? $value < 0 : $value <= 0)) {
            throw new \InvalidArgumentException("SQLite WordPress JSON schema WAL savepoint {$label} must be " . ($allowZero ? 'non-negative' : 'positive') . ' integer');
        }

        return $value;
    }

    private static function nonEmptyString(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite WordPress JSON schema WAL savepoint {$label} must be non-empty text");
        }

        return $value;
    }

    private static function walFrame(mixed $value, int &$nextFrame): int
    {
        if (!is_int($value) || $value < $nextFrame) {
            throw new \InvalidArgumentException('SQLite WordPress JSON schema WAL savepoint frame indexes must increase');
        }
        $nextFrame = $value + 1;

        return $value;
    }

    private static function databaseImage(int $pageSize, int $pages): string
    {
        $database = str_repeat("\0", $pageSize * $pages);
        for ($page = 1; $page <= $pages; $page++) {
            $database = self::writePage($database, $pageSize, $page, self::pageImage($pageSize, $page, 'initial', 1, 1));
        }

        return $database;
    }

    private static function pageFromDatabase(string $database, int $pageSize, int $pageNumber): ?string
    {
        $offset = ($pageNumber - 1) * $pageSize;
        if ($offset + $pageSize > strlen($database)) {
            return null;
        }

        return substr($database, $offset, $pageSize);
    }

    private static function writePage(string $database, int $pageSize, int $pageNumber, string $page): string
    {
        if (strlen($page) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite WordPress JSON schema WAL savepoint page image has the wrong size');
        }
        $offset = ($pageNumber - 1) * $pageSize;
        if ($offset + $pageSize > strlen($database)) {
            $database = str_pad($database, $offset + $pageSize, "\0");
        }

        return substr_replace($database, $page, $offset, $pageSize);
    }

    private static function pageImage(int $pageSize, int $pageNumber, string $label, int $schemaCookie, int $dataVersion): string
    {
        return str_pad("wp-json-schema:{$pageNumber}:{$label}:schema={$schemaCookie}:data={$dataVersion}", $pageSize, "\0");
    }
}
