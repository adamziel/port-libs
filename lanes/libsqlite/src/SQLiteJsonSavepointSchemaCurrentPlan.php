<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonSavepointSchemaCurrentPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array{name?:string,json:mixed,path?:string,release?:bool,schema_changes?:list<array<string,mixed>>}> $batches
     * @param array{database_path?:string,page_size?:int,schema_version?:int,data_version?:int,schema?:array<string,mixed>} $options
     * @return array<string,mixed>
     */
    public static function plan(array $currentRows, array $batches, array $options = []): array
    {
        if ($batches === []) {
            throw new \InvalidArgumentException('SQLite Application JSON savepoint schema-current plan requires at least one batch');
        }

        $schemaVersion = self::nonNegativeInt($options['schema_version'] ?? 1, 'schema_version');
        $dataVersion = self::nonNegativeInt($options['data_version'] ?? 1, 'data_version');
        $visibleSchema = self::schemaRows($options['schema']['rows'] ?? []);
        $releasedSchema = $visibleSchema;
        $visibleRows = self::rowsById($currentRows);
        $releasedRows = $visibleRows;
        $currentWalFrame = 0;
        $walFrames = [];
        $plans = [];
        $released = [];
        $rolledBack = [];
        $schemaRejected = [];
        $schemaVersionBefore = $schemaVersion;
        $dataVersionBefore = $dataVersion;

        foreach (array_values($batches) as $index => $batch) {
            $name = self::savepointName($batch, $index);
            $beforeRows = $visibleRows;
            $beforeSchema = $visibleSchema;
            $beforeSchemaVersion = $schemaVersion;
            $beforeDataVersion = $dataVersion;
            $beforeWalFrame = $currentWalFrame;

            $schemaPlan = self::applySchemaChanges($visibleSchema, $batch['schema_changes'] ?? []);
            if (!$schemaPlan['valid']) {
                $rolledBack[] = $name;
                $schemaRejected[] = $name;
                $plans[] = self::rolledBackBatch(
                    $name,
                    (string) $schemaPlan['error'],
                    $beforeRows,
                    $beforeSchema,
                    $schemaPlan,
                    $beforeSchemaVersion,
                    $beforeDataVersion,
                    $beforeWalFrame
                );
                continue;
            }

            $importOptions = $options;
            unset($importOptions['schema'], $importOptions['schema_version'], $importOptions['data_version']);
            $subPlan = SQLiteSchemaJsonSavepointWalPlan::plan(array_values($visibleRows), [$batch], $importOptions);
            $subBatch = $subPlan['batches'][0];
            if ($subBatch['status'] === 'rolled_back') {
                $rolledBack[] = $name;
                $plans[] = self::rolledBackBatch(
                    $name,
                    (string) $subBatch['error'],
                    $beforeRows,
                    $beforeSchema,
                    $schemaPlan,
                    $beforeSchemaVersion,
                    $beforeDataVersion,
                    $beforeWalFrame
                );
                continue;
            }

            $visibleRows = self::rowsById($subPlan['final_rows']);
            $visibleSchema = $schemaPlan['schema_rows'];
            $schemaDelta = count($schemaPlan['created']) + count($schemaPlan['dropped']);
            $rowDelta = count($subBatch['dirty_pages']);
            $schemaVersion += $schemaDelta;
            $dataVersion += $schemaDelta + $rowDelta;

            $offsetFrames = [];
            foreach ($subBatch['wal_frames'] as $frame) {
                $currentWalFrame++;
                $offset = $frame;
                $offset['frame_index'] = $currentWalFrame;
                $offset['savepoint'] = $name;
                $offsetFrames[] = $offset;
                $walFrames[] = $offset;
            }

            $schemaCookieFrame = null;
            if ($schemaDelta > 0) {
                $currentWalFrame++;
                $schemaCookieFrame = [
                    'frame_index' => $currentWalFrame,
                    'page_number' => 1,
                    'commit_frame' => true,
                    'savepoint' => $name,
                    'schema_cookie' => $schemaVersion,
                    'data_version' => $dataVersion,
                ];
                $walFrames[] = $schemaCookieFrame;
                $offsetFrames[] = $schemaCookieFrame;
            }

            $shouldRelease = (bool) ($batch['release'] ?? true);
            if ($shouldRelease) {
                $released[] = $name;
                $releasedRows = $visibleRows;
                $releasedSchema = $visibleSchema;
            }

            $plans[] = [
                'name' => $name,
                'status' => $shouldRelease ? 'released' : 'open',
                'error' => null,
                'schema' => $schemaPlan,
                'before_schema_version' => $beforeSchemaVersion,
                'after_schema_version' => $schemaVersion,
                'before_data_version' => $beforeDataVersion,
                'after_data_version' => $dataVersion,
                'schema_cookie_frame' => $schemaCookieFrame,
                'wal_start_frame' => $beforeWalFrame,
                'wal_current_frame' => $currentWalFrame,
                'wal_frames' => $offsetFrames,
                'before_schema_names' => array_keys($beforeSchema),
                'after_schema_names' => array_keys($visibleSchema),
                'before_key_names' => array_column(array_values($beforeRows), 'key_name'),
                'after_key_names' => array_column(array_values($visibleRows), 'key_name'),
                'released' => $shouldRelease,
            ];
        }

        return [
            'status' => 'planned',
            'schema_current' => true,
            'database_path' => (string) ($options['database_path'] ?? '/tmp/app-json-schema-current.sqlite'),
            'batch_count' => count($plans),
            'released_batches' => $released,
            'rolled_back_batches' => $rolledBack,
            'schema_rejected_batches' => $schemaRejected,
            'batches' => $plans,
            'schema_version_before' => $schemaVersionBefore,
            'schema_version' => $schemaVersion,
            'data_version_before' => $dataVersionBefore,
            'data_version' => $dataVersion,
            'current_rows' => array_values($currentRows),
            'final_rows' => array_values($visibleRows),
            'released_rows' => array_values($releasedRows),
            'final_key_names' => array_column(array_values($visibleRows), 'key_name'),
            'released_key_names' => array_column(array_values($releasedRows), 'key_name'),
            'schema_rows' => array_values($visibleSchema),
            'released_schema_rows' => array_values($releasedSchema),
            'schema_names' => array_keys($visibleSchema),
            'released_schema_names' => array_keys($releasedSchema),
            'wal' => [
                'path' => (string) ($options['database_path'] ?? '/tmp/app-json-schema-current.sqlite') . '-wal',
                'current_frame' => $currentWalFrame,
                'frame_count' => count($walFrames),
                'frames' => $walFrames,
                'schema_current' => true,
            ],
            'dependencies' => [
                'sqlite-application-json-savepoint-schema-current',
                'sqlite-application-schema-json-savepoint-wal',
                'sqlite-application-json-import-wal-savepoint',
                'sqlite-schema-cookie-current-source',
            ],
        ];
    }

    private static function nonNegativeInt(mixed $value, string $name): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite Application JSON schema-current {$name} must be a non-negative integer");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function rowsById(array $rows): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $id = $row['setting_id'] ?? null;
            if (!is_int($id)) {
                throw new \InvalidArgumentException('SQLite Application JSON schema-current rows require integer setting_id values');
            }
            $byId[$id] = $row;
        }
        ksort($byId);

        return $byId;
    }

    /**
     * @param mixed $rows
     * @return array<string,array<string,mixed>>
     */
    private static function schemaRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            throw new \InvalidArgumentException('SQLite Application JSON schema-current schema rows must be a list');
        }

        $byName = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite Application JSON schema-current schema rows must be arrays');
            }
            $normalized = self::normalizeSchemaRow($row);
            if (isset($byName[$normalized['name']])) {
                throw new \InvalidArgumentException("Duplicate SQLite schema row {$normalized['name']}");
            }
            $byName[$normalized['name']] = $normalized;
        }
        ksort($byName);

        return $byName;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{type:string,name:string,tbl_name:string,rootpage:int,sql:string}
     */
    private static function normalizeSchemaRow(array $row): array
    {
        $type = $row['type'] ?? 'table';
        $name = $row['name'] ?? null;
        $table = $row['tbl_name'] ?? $name;
        $rootpage = $row['rootpage'] ?? 0;
        $sql = $row['sql'] ?? '';
        if (!is_string($type) || !in_array($type, ['table', 'index', 'trigger', 'view'], true)) {
            throw new \InvalidArgumentException('SQLite schema row type must be table, index, trigger, or view');
        }
        if (!is_string($name) || $name === '' || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('SQLite schema row name must be non-empty text');
        }
        if (!is_string($table) || $table === '' || str_contains($table, "\0")) {
            throw new \InvalidArgumentException('SQLite schema row table name must be non-empty text');
        }
        if (!is_int($rootpage) || $rootpage < 0) {
            throw new \InvalidArgumentException('SQLite schema row rootpage must be a non-negative integer');
        }
        if (!is_string($sql) || !preg_match('/^\s*CREATE\s+/i', $sql)) {
            throw new \InvalidArgumentException('SQLite schema row SQL must be CREATE text');
        }

        return [
            'type' => $type,
            'name' => $name,
            'tbl_name' => $table,
            'rootpage' => $rootpage,
            'sql' => $sql,
        ];
    }

    /**
     * @param array<string,mixed> $batch
     */
    private static function savepointName(array $batch, int $index): string
    {
        $name = (string) ($batch['name'] ?? 'app_json_schema_' . ($index + 1));
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('SQLite Application JSON schema-current savepoint names must be SQL identifiers');
        }

        return $name;
    }

    /**
     * @param array<string,array<string,mixed>> $schemaRows
     * @param mixed $changes
     * @return array{valid:bool,error:?string,created:list<string>,dropped:list<string>,schema_rows:array<string,array<string,mixed>>,violations:list<array<string,mixed>>}
     */
    private static function applySchemaChanges(array $schemaRows, mixed $changes): array
    {
        if ($changes === null) {
            $changes = [];
        }
        if (!is_array($changes)) {
            return self::invalidSchemaChange('schema_changes must be a list', []);
        }

        $working = $schemaRows;
        $created = [];
        $dropped = [];
        $violations = [];
        foreach (array_values($changes) as $index => $change) {
            if (!is_array($change)) {
                return self::invalidSchemaChange('schema change must be an array', [['change' => $index, 'rule' => 'array']]);
            }
            $action = strtolower((string) ($change['action'] ?? 'create'));
            if ($action === 'drop') {
                $name = $change['name'] ?? null;
                if (!is_string($name) || $name === '' || !isset($working[$name])) {
                    $violations[] = ['change' => $index, 'name' => is_string($name) ? $name : null, 'rule' => 'drop_existing'];
                    continue;
                }
                unset($working[$name]);
                $dropped[] = $name;
                continue;
            }
            if ($action !== 'create') {
                $violations[] = ['change' => $index, 'rule' => 'action'];
                continue;
            }

            try {
                $row = self::normalizeSchemaRow($change);
            } catch (\Throwable $throwable) {
                $violations[] = ['change' => $index, 'rule' => 'schema_row', 'error' => $throwable->getMessage()];
                continue;
            }
            if (isset($working[$row['name']])) {
                $violations[] = ['change' => $index, 'name' => $row['name'], 'rule' => 'duplicate_schema_name'];
                continue;
            }
            if ($row['type'] === 'table' || $row['type'] === 'index') {
                if ($row['rootpage'] <= 0) {
                    $violations[] = ['change' => $index, 'name' => $row['name'], 'rule' => 'rootpage_positive'];
                    continue;
                }
            }
            $working[$row['name']] = $row;
            $created[] = $row['name'];
        }
        ksort($working);

        if ($violations !== []) {
            return self::invalidSchemaChange('SQLite Application JSON schema-current DDL failed schema validation', $violations);
        }

        return [
            'valid' => true,
            'error' => null,
            'created' => $created,
            'dropped' => $dropped,
            'schema_rows' => $working,
            'violations' => [],
        ];
    }

    /**
     * @param list<array<string,mixed>> $violations
     * @return array{valid:bool,error:string,created:list<string>,dropped:list<string>,schema_rows:array<string,array<string,mixed>>,violations:list<array<string,mixed>>}
     */
    private static function invalidSchemaChange(string $error, array $violations): array
    {
        return [
            'valid' => false,
            'error' => $error,
            'created' => [],
            'dropped' => [],
            'schema_rows' => [],
            'violations' => $violations,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $beforeRows
     * @param array<string,array<string,mixed>> $beforeSchema
     * @param array<string,mixed> $schemaPlan
     * @return array<string,mixed>
     */
    private static function rolledBackBatch(string $name, string $error, array $beforeRows, array $beforeSchema, array $schemaPlan, int $schemaVersion, int $dataVersion, int $walFrame): array
    {
        return [
            'name' => $name,
            'status' => 'rolled_back',
            'error' => $error,
            'schema' => $schemaPlan,
            'before_schema_version' => $schemaVersion,
            'after_schema_version' => $schemaVersion,
            'before_data_version' => $dataVersion,
            'after_data_version' => $dataVersion,
            'schema_cookie_frame' => null,
            'wal_start_frame' => $walFrame,
            'wal_current_frame' => $walFrame,
            'wal_rollback_to_frame' => $walFrame,
            'discarded_wal_frames' => [],
            'before_schema_names' => array_keys($beforeSchema),
            'after_schema_names' => array_keys($beforeSchema),
            'before_key_names' => array_column(array_values($beforeRows), 'key_name'),
            'after_key_names' => array_column(array_values($beforeRows), 'key_name'),
            'released' => false,
        ];
    }
}
