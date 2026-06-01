<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSchemaJsonSavepointWalPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array{name?:string,json:mixed,path?:string,release?:bool,on_conflict?:string,schema?:array<string,mixed>}> $imports
     * @param array{database_path?:string,journal_mode?:string,page_size?:int,replace_conflicts?:bool,sync_mode?:string,schema?:array<string,mixed>} $options
     * @return array<string,mixed>
     */
    public static function plan(array $currentRows, array $imports, array $options = []): array
    {
        if ($imports === []) {
            throw new \InvalidArgumentException('SQLite Application schema JSON savepoint WAL plan requires at least one import');
        }

        $schema = self::schema($options['schema'] ?? []);
        $visibleRows = self::rowsById($currentRows);
        $releasedRows = $visibleRows;
        $batches = [];
        $released = [];
        $rolledBack = [];
        $schemaRejected = [];
        $walFrames = [];
        $currentWalFrame = 0;

        foreach (array_values($imports) as $index => $import) {
            $name = self::savepointName($import, $index);
            $batchSchema = self::schema($import['schema'] ?? $schema);
            $jsonPlan = self::jsonRows($import['json'] ?? null, (string) ($import['path'] ?? '$'));
            $beforeRows = $visibleRows;
            $beforeWalFrame = $currentWalFrame;

            if ($jsonPlan['valid']) {
                $schemaPlan = self::validateRows($jsonPlan['rows'], $batchSchema);
                if (!$schemaPlan['valid']) {
                    $jsonPlan['schema'] = $schemaPlan;
                    $rolledBack[] = $name;
                    $schemaRejected[] = $name;
                    $batches[] = self::rolledBackBatch($name, (string) $schemaPlan['error'], $jsonPlan, $beforeRows, $beforeWalFrame, $currentWalFrame);
                    continue;
                }
                $jsonPlan['schema'] = $schemaPlan;
            }

            $normalizedImport = $import;
            if ($jsonPlan['valid']) {
                $normalizedImport['json'] = json_encode(['rows' => $jsonPlan['rows']], JSON_THROW_ON_ERROR);
                $normalizedImport['path'] = '$.rows';
            }

            $subPlan = SQLiteJsonImportWalSavepointPlan::plan(array_values($visibleRows), [$normalizedImport], $options);
            $subBatch = $subPlan['batches'][0];

            if ($subBatch['status'] === 'rolled_back') {
                $rolledBack[] = $name;
                $subBatch['json']['schema'] = $jsonPlan['schema'] ?? [
                    'valid' => false,
                    'error' => $subBatch['error'],
                    'accepted_rows' => 0,
                    'rejected_rows' => $jsonPlan['row_count'] ?? 0,
                    'violations' => [],
                ];
                $subBatch['wal_start_frame'] = $beforeWalFrame;
                $subBatch['wal_current_frame'] = $currentWalFrame;
                $batches[] = $subBatch;
                continue;
            }

            $offsetFrames = [];
            foreach ($subBatch['wal_frames'] as $frame) {
                $currentWalFrame++;
                $offset = $frame;
                $offset['frame_index'] = $currentWalFrame;
                $offset['savepoint'] = $name;
                $offsetFrames[] = $offset;
                $walFrames[] = $offset;
            }

            $visibleRows = self::rowsById($subPlan['final_rows']);
            $subBatch['json'] = $jsonPlan;
            $subBatch['wal_start_frame'] = $beforeWalFrame;
            $subBatch['wal_current_frame'] = $currentWalFrame;
            $subBatch['wal_frames'] = $offsetFrames;
            $batches[] = $subBatch;

            if ((bool) ($import['release'] ?? true)) {
                $released[] = $name;
                $releasedRows = $visibleRows;
            }
        }

        return [
            'status' => 'planned',
            'schema_json_savepoint_wal' => true,
            'database_path' => (string) ($options['database_path'] ?? '/tmp/app-schema-json-savepoint.sqlite'),
            'schema' => $schema,
            'batch_count' => count($batches),
            'released_batches' => $released,
            'rolled_back_batches' => $rolledBack,
            'schema_rejected_batches' => $schemaRejected,
            'batches' => $batches,
            'current_rows' => array_values($currentRows),
            'final_rows' => array_values($visibleRows),
            'released_rows' => array_values($releasedRows),
            'final_key_names' => array_column(array_values($visibleRows), 'key_name'),
            'released_key_names' => array_column(array_values($releasedRows), 'key_name'),
            'wal' => [
                'path' => (string) ($options['database_path'] ?? '/tmp/app-schema-json-savepoint.sqlite') . '-wal',
                'current_frame' => $currentWalFrame,
                'frame_count' => count($walFrames),
                'frames' => $walFrames,
                'schema_json_savepoint_wal' => true,
            ],
            'dependencies' => [
                'sqlite-application-schema-json-savepoint-wal',
                'sqlite-application-json-import-wal-savepoint',
                'sqlite-application-import-transaction-current',
                'sqlite-json-extract',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $schema
     * @return array{required:list<string>,allowed:list<string>,load_policy:list<string>,json_key_patterns:list<string>,reject_unknown:bool}
     */
    private static function schema(array $schema): array
    {
        $required = self::stringList($schema['required'] ?? ['key_name', 'key_value']);
        $allowed = self::stringList($schema['allowed'] ?? ['setting_id', 'key_name', 'key_value', 'load_policy']);
        $load_policy = self::stringList($schema['load_policy'] ?? ['yes', 'no', 'auto', 'on', 'off']);
        $patterns = self::stringList($schema['json_key_patterns'] ?? ['/^module_/', '/_settings$/', '/^component_/']);

        return [
            'required' => $required,
            'allowed' => $allowed,
            'load_policy' => $load_policy,
            'json_key_patterns' => $patterns,
            'reject_unknown' => (bool) ($schema['reject_unknown'] ?? true),
        ];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite Application schema JSON options require string lists');
        }

        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException('SQLite Application schema JSON options require non-empty strings');
            }
            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @param array<string,mixed> $import
     */
    private static function savepointName(array $import, int $index): string
    {
        $name = (string) ($import['name'] ?? 'app_schema_json_' . ($index + 1));
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('SQLite Application schema JSON savepoint names must be SQL identifiers');
        }

        return $name;
    }

    /**
     * @return array{valid:bool,path:string,rows:list<array<string,mixed>>,key_names:list<string>,row_count:int,error:?string}
     */
    private static function jsonRows(mixed $json, string $path): array
    {
        if (!is_string($json) && !$json instanceof SQLiteBlobValue && !$json instanceof SQLiteJsonSubtypeValue) {
            return self::invalidJson($path, 'SQLite Application schema JSON import requires text, JSON subtype, or JSONB blob input');
        }

        try {
            $value = $json instanceof SQLiteJsonSubtypeValue ? $json->json : $json;
            $valid = $value instanceof SQLiteBlobValue
                ? SQLiteJsonValidity::jsonValid($value, SQLiteJsonValidity::FLAG_STRICT_JSONB | SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB)
                : SQLiteJsonValidity::jsonValid((string) $value, SQLiteJsonValidity::FLAG_STRICT_TEXT | SQLiteJsonValidity::FLAG_JSON5_TEXT);
            if ($valid !== true) {
                return self::invalidJson($path, 'SQLite Application schema JSON import source is malformed JSON');
            }

            $extracted = SQLiteJsonExtract::extract($value, $path);
            if ($extracted === null) {
                return self::invalidJson($path, 'SQLite Application schema JSON import path did not match any rows');
            }

            $decoded = is_string($extracted) ? json_decode($extracted, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR) : $extracted;
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('SQLite Application schema JSON import path must resolve to an object or array of objects');
            }

            $rows = array_is_list($decoded) ? $decoded : [$decoded];
            $normalized = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite Application schema JSON import rows must be objects');
                }
                $normalized[] = $row;
            }

            return [
                'valid' => true,
                'path' => $path,
                'rows' => $normalized,
                'key_names' => array_values(array_filter(array_map(static fn (array $row): mixed => $row['key_name'] ?? $row['name'] ?? null, $normalized), 'is_string')),
                'row_count' => count($normalized),
                'error' => null,
            ];
        } catch (\Throwable $throwable) {
            return self::invalidJson($path, $throwable->getMessage());
        }
    }

    /**
     * @return array{valid:bool,path:string,rows:list<array<string,mixed>>,key_names:list<string>,row_count:int,error:string}
     */
    private static function invalidJson(string $path, string $error): array
    {
        return [
            'valid' => false,
            'path' => $path,
            'rows' => [],
            'key_names' => [],
            'row_count' => 0,
            'error' => $error,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{required:list<string>,allowed:list<string>,load_policy:list<string>,json_key_patterns:list<string>,reject_unknown:bool} $schema
     * @return array{valid:bool,accepted_rows:int,rejected_rows:int,violations:list<array<string,mixed>>,error:?string}
     */
    private static function validateRows(array $rows, array $schema): array
    {
        $violations = [];
        foreach ($rows as $index => $row) {
            foreach ($schema['required'] as $field) {
                if (!array_key_exists($field, $row) && !($field === 'key_name' && array_key_exists('name', $row))) {
                    $violations[] = ['row' => $index, 'field' => $field, 'rule' => 'required'];
                }
            }
            if ($schema['reject_unknown']) {
                foreach (array_keys($row) as $field) {
                    if (!in_array((string) $field, $schema['allowed'], true) && !($field === 'name' && in_array('key_name', $schema['allowed'], true))) {
                        $violations[] = ['row' => $index, 'field' => (string) $field, 'rule' => 'unknown'];
                    }
                }
            }

            $name = $row['key_name'] ?? $row['name'] ?? null;
            if (!is_string($name) || $name === '' || str_contains($name, "\0")) {
                $violations[] = ['row' => $index, 'field' => 'key_name', 'rule' => 'non_empty_text'];
            }

            $load_policy = $row['load_policy'] ?? 'no';
            if (!is_string($load_policy) || !in_array($load_policy, $schema['load_policy'], true)) {
                $violations[] = ['row' => $index, 'field' => 'load_policy', 'rule' => 'enum'];
            }

            $value = $row['key_value'] ?? $row['value'] ?? '';
            if (self::expectsJsonValue(is_string($name) ? $name : '', $schema) && !self::isJsonText($value)) {
                $violations[] = ['row' => $index, 'field' => 'key_value', 'rule' => 'json_text'];
            }
        }

        return [
            'valid' => $violations === [],
            'accepted_rows' => $violations === [] ? count($rows) : 0,
            'rejected_rows' => $violations === [] ? 0 : count($rows),
            'violations' => $violations,
            'error' => $violations === [] ? null : 'SQLite Application schema JSON import row failed schema validation',
        ];
    }

    /**
     * @param array{json_key_patterns:list<string>} $schema
     */
    private static function expectsJsonValue(string $name, array $schema): bool
    {
        foreach ($schema['json_key_patterns'] as $pattern) {
            if (@preg_match($pattern, $name) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function isJsonText(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE;
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
                throw new \InvalidArgumentException('SQLite Application schema JSON import rows require integer setting_id values');
            }
            $byId[$id] = $row;
        }
        ksort($byId);

        return $byId;
    }

    /**
     * @param array<string,mixed> $jsonPlan
     * @param array<int,array<string,mixed>> $beforeRows
     * @return array<string,mixed>
     */
    private static function rolledBackBatch(string $name, string $error, array $jsonPlan, array $beforeRows, int $beforeWalFrame, int $currentWalFrame): array
    {
        return [
            'name' => $name,
            'status' => 'rolled_back',
            'error' => $error,
            'json' => $jsonPlan,
            'before_key_names' => array_column(array_values($beforeRows), 'key_name'),
            'after_key_names' => array_column(array_values($beforeRows), 'key_name'),
            'updated' => 0,
            'inserted' => 0,
            'deleted' => 0,
            'dirty_pages' => [],
            'wal_start_frame' => $beforeWalFrame,
            'wal_current_frame' => $currentWalFrame,
            'wal_rollback_to_frame' => $beforeWalFrame,
            'discarded_wal_frames' => [],
            'released' => false,
        ];
    }
}
