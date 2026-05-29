<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWordPressImportJsonSchemaSavepointPlan
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
            throw new \InvalidArgumentException('SQLite WordPress import JSON schema savepoint plan requires at least one import');
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
            $beforeRows = $visibleRows;
            $beforeWalFrame = $currentWalFrame;
            $jsonPlan = self::jsonRows($import['json'] ?? null, (string) ($import['path'] ?? '$'));

            if (!$jsonPlan['valid']) {
                $rolledBack[] = $name;
                $batches[] = self::rolledBackBatch($name, (string) $jsonPlan['error'], $jsonPlan, $beforeRows, $beforeWalFrame, $currentWalFrame);
                continue;
            }

            $schemaPlan = self::normalizeRows($jsonPlan['rows'], $batchSchema, $beforeRows);
            $jsonPlan['schema'] = $schemaPlan;
            if (!$schemaPlan['valid']) {
                $rolledBack[] = $name;
                $schemaRejected[] = $name;
                $batches[] = self::rolledBackBatch($name, (string) $schemaPlan['error'], $jsonPlan, $beforeRows, $beforeWalFrame, $currentWalFrame);
                continue;
            }

            $normalizedImport = $import;
            $normalizedImport['json'] = json_encode(['rows' => $schemaPlan['rows']], JSON_THROW_ON_ERROR);
            $normalizedImport['path'] = '$.rows';
            $subPlan = SQLiteWordPressJsonImportWalSavepointPlan::plan(array_values($visibleRows), [$normalizedImport], $options);
            $subBatch = $subPlan['batches'][0];

            if ($subBatch['status'] === 'rolled_back') {
                $rolledBack[] = $name;
                $subBatch['json'] = $jsonPlan;
                $subBatch['wal_start_frame'] = $beforeWalFrame;
                $subBatch['wal_current_frame'] = $currentWalFrame;
                $subBatch['current_savepoint'] = self::savepointSnapshot($name, $beforeWalFrame, array_values($beforeRows));
                $subBatch['next_savepoint'] = self::nextSavepointSnapshot($name, $currentWalFrame, array_values($beforeRows));
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
            $subBatch['schema_defaulted_fields'] = $schemaPlan['defaulted_fields'];
            $subBatch['schema_generated_ids'] = self::generatedIds($schemaPlan['rows'], $subPlan['final_rows']);
            $subBatch['schema_conflicts'] = self::conflictRows(array_values($beforeRows), $subPlan['final_rows'], $schemaPlan['rows']);
            $subBatch['wal_start_frame'] = $beforeWalFrame;
            $subBatch['wal_current_frame'] = $currentWalFrame;
            $subBatch['wal_frames'] = $offsetFrames;
            $subBatch['current_savepoint'] = self::savepointSnapshot($name, $beforeWalFrame, array_values($beforeRows));
            $subBatch['next_savepoint'] = self::nextSavepointSnapshot($name, $currentWalFrame, $subPlan['final_rows']);
            $batches[] = $subBatch;

            if ((bool) ($import['release'] ?? true)) {
                $released[] = $name;
                $releasedRows = $visibleRows;
            }
        }

        return [
            'status' => 'planned',
            'schema_savepoint_import' => true,
            'database_path' => (string) ($options['database_path'] ?? '/tmp/wp-import-json-schema-savepoint.sqlite'),
            'schema' => $schema,
            'batch_count' => count($batches),
            'released_batches' => $released,
            'rolled_back_batches' => $rolledBack,
            'schema_rejected_batches' => $schemaRejected,
            'batches' => $batches,
            'current_rows' => array_values($currentRows),
            'final_rows' => array_values($visibleRows),
            'released_rows' => array_values($releasedRows),
            'final_option_names' => array_column(array_values($visibleRows), 'option_name'),
            'released_option_names' => array_column(array_values($releasedRows), 'option_name'),
            'wal' => [
                'path' => (string) ($options['database_path'] ?? '/tmp/wp-import-json-schema-savepoint.sqlite') . '-wal',
                'current_frame' => $currentWalFrame,
                'frame_count' => count($walFrames),
                'frames' => $walFrames,
                'schema_savepoint_import' => true,
            ],
            'dependencies' => [
                'sqlite-wordpress-import-json-schema-savepoint',
                'sqlite-wordpress-json-import-wal-savepoint',
                'sqlite-wordpress-import-transaction-current',
                'sqlite-json-extract',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $schema
     * @return array{required:list<string>,allowed:list<string>,autoload:list<string>,defaults:array<string,mixed>,json_option_patterns:list<string>,reject_unknown:bool,generate_option_id:bool}
     */
    private static function schema(array $schema): array
    {
        return [
            'required' => self::stringList($schema['required'] ?? ['option_name', 'option_value']),
            'allowed' => self::stringList($schema['allowed'] ?? ['option_id', 'option_name', 'name', 'option_value', 'value', 'autoload']),
            'autoload' => self::stringList($schema['autoload'] ?? ['yes', 'no', 'auto', 'on', 'off']),
            'defaults' => is_array($schema['defaults'] ?? null) ? $schema['defaults'] : ['autoload' => 'no'],
            'json_option_patterns' => self::stringList($schema['json_option_patterns'] ?? ['/^theme_mods_/', '/_settings$/', '/^widget_/']),
            'reject_unknown' => (bool) ($schema['reject_unknown'] ?? true),
            'generate_option_id' => (bool) ($schema['generate_option_id'] ?? true),
        ];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite WordPress import JSON schema options require string lists');
        }

        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException('SQLite WordPress import JSON schema options require non-empty strings');
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
        $name = (string) ($import['name'] ?? 'wp_import_schema_' . ($index + 1));
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('SQLite WordPress import JSON schema savepoint names must be SQL identifiers');
        }

        return $name;
    }

    /**
     * @return array{valid:bool,path:string,rows:list<array<string,mixed>>,option_names:list<string>,row_count:int,error:?string}
     */
    private static function jsonRows(mixed $json, string $path): array
    {
        if (!is_string($json) && !$json instanceof SQLiteBlobValue && !$json instanceof SQLiteJsonSubtypeValue) {
            return self::invalidJson($path, 'SQLite WordPress import JSON schema source requires text, JSON subtype, or JSONB blob input');
        }

        try {
            $value = $json instanceof SQLiteJsonSubtypeValue ? $json->json : $json;
            $valid = $value instanceof SQLiteBlobValue
                ? SQLiteJsonValidity::jsonValid($value, SQLiteJsonValidity::FLAG_STRICT_JSONB | SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB)
                : SQLiteJsonValidity::jsonValid((string) $value, SQLiteJsonValidity::FLAG_STRICT_TEXT | SQLiteJsonValidity::FLAG_JSON5_TEXT);
            if ($valid !== true) {
                return self::invalidJson($path, 'SQLite WordPress import JSON schema source is malformed JSON');
            }

            $extracted = SQLiteJsonExtract::extract($value, $path);
            if ($extracted === null) {
                return self::invalidJson($path, 'SQLite WordPress import JSON schema path did not match any rows');
            }

            $decoded = is_string($extracted) ? json_decode($extracted, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR) : $extracted;
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('SQLite WordPress import JSON schema path must resolve to an object or array of objects');
            }

            $rows = array_is_list($decoded) ? $decoded : [$decoded];
            $normalized = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite WordPress import JSON schema rows must be objects');
                }
                $normalized[] = $row;
            }

            return [
                'valid' => true,
                'path' => $path,
                'rows' => $normalized,
                'option_names' => array_values(array_filter(array_map(static fn (array $row): mixed => $row['option_name'] ?? $row['name'] ?? null, $normalized), 'is_string')),
                'row_count' => count($normalized),
                'error' => null,
            ];
        } catch (\Throwable $throwable) {
            return self::invalidJson($path, $throwable->getMessage());
        }
    }

    /**
     * @return array{valid:bool,path:string,rows:list<array<string,mixed>>,option_names:list<string>,row_count:int,error:string}
     */
    private static function invalidJson(string $path, string $error): array
    {
        return [
            'valid' => false,
            'path' => $path,
            'rows' => [],
            'option_names' => [],
            'row_count' => 0,
            'error' => $error,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{required:list<string>,allowed:list<string>,autoload:list<string>,defaults:array<string,mixed>,json_option_patterns:list<string>,reject_unknown:bool,generate_option_id:bool} $schema
     * @param array<int,array<string,mixed>> $beforeRows
     * @return array{valid:bool,rows:list<array<string,mixed>>,accepted_rows:int,rejected_rows:int,violations:list<array<string,mixed>>,defaulted_fields:list<array<string,mixed>>,error:?string}
     */
    private static function normalizeRows(array $rows, array $schema, array $beforeRows): array
    {
        $violations = [];
        $normalized = [];
        $defaulted = [];
        $maxId = $beforeRows === [] ? 0 : max(array_keys($beforeRows));

        foreach ($rows as $index => $row) {
            if ($schema['reject_unknown']) {
                foreach (array_keys($row) as $field) {
                    if (!in_array((string) $field, $schema['allowed'], true)) {
                        $violations[] = ['row' => $index, 'field' => (string) $field, 'rule' => 'unknown'];
                    }
                }
            }

            $candidate = [];
            $candidate['option_name'] = $row['option_name'] ?? $row['name'] ?? null;
            $candidate['option_value'] = $row['option_value'] ?? $row['value'] ?? null;
            if (array_key_exists('autoload', $row)) {
                $candidate['autoload'] = $row['autoload'];
            }
            if (array_key_exists('option_id', $row)) {
                $candidate['option_id'] = $row['option_id'];
                if (is_int($row['option_id']) && $row['option_id'] > $maxId) {
                    $maxId = $row['option_id'];
                }
            } elseif ($schema['generate_option_id']) {
                $candidate['option_id'] = ++$maxId;
                $defaulted[] = ['row' => $index, 'field' => 'option_id', 'value' => $candidate['option_id'], 'source' => 'generated'];
            }

            foreach ($schema['defaults'] as $field => $value) {
                if (!array_key_exists((string) $field, $candidate) || $candidate[(string) $field] === null) {
                    $candidate[(string) $field] = $value;
                    $defaulted[] = ['row' => $index, 'field' => (string) $field, 'value' => $value, 'source' => 'schema_default'];
                }
            }

            foreach ($schema['required'] as $field) {
                if (!array_key_exists($field, $candidate) || $candidate[$field] === null) {
                    $violations[] = ['row' => $index, 'field' => $field, 'rule' => 'required'];
                }
            }

            if (!is_string($candidate['option_name'] ?? null) || $candidate['option_name'] === '' || str_contains((string) $candidate['option_name'], "\0")) {
                $violations[] = ['row' => $index, 'field' => 'option_name', 'rule' => 'non_empty_text'];
            }

            if (isset($candidate['option_id']) && (!is_int($candidate['option_id']) || $candidate['option_id'] <= 0)) {
                $violations[] = ['row' => $index, 'field' => 'option_id', 'rule' => 'positive_integer'];
            }

            if (!is_string($candidate['autoload'] ?? null) || !in_array($candidate['autoload'], $schema['autoload'], true)) {
                $violations[] = ['row' => $index, 'field' => 'autoload', 'rule' => 'enum'];
            }

            if (self::expectsJsonValue(is_string($candidate['option_name'] ?? null) ? $candidate['option_name'] : '', $schema) && !self::isJsonText($candidate['option_value'] ?? null)) {
                $violations[] = ['row' => $index, 'field' => 'option_value', 'rule' => 'json_text'];
            }

            $normalized[] = $candidate;
        }

        return [
            'valid' => $violations === [],
            'rows' => $violations === [] ? $normalized : [],
            'accepted_rows' => $violations === [] ? count($rows) : 0,
            'rejected_rows' => $violations === [] ? 0 : count($rows),
            'violations' => $violations,
            'defaulted_fields' => $defaulted,
            'error' => $violations === [] ? null : 'SQLite WordPress import JSON schema row failed schema/default validation',
        ];
    }

    /**
     * @param array{json_option_patterns:list<string>} $schema
     */
    private static function expectsJsonValue(string $name, array $schema): bool
    {
        foreach ($schema['json_option_patterns'] as $pattern) {
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
     * @param list<array<string,mixed>> $schemaRows
     * @param list<array<string,mixed>> $finalRows
     * @return list<array<string,mixed>>
     */
    private static function generatedIds(array $schemaRows, array $finalRows): array
    {
        $byName = [];
        foreach ($finalRows as $row) {
            if (isset($row['option_name'], $row['option_id']) && is_string($row['option_name'])) {
                $byName[$row['option_name']] = $row['option_id'];
            }
        }

        $generated = [];
        foreach ($schemaRows as $row) {
            if (isset($row['option_name'], $row['option_id']) && is_string($row['option_name']) && isset($byName[$row['option_name']])) {
                $generated[] = ['option_name' => $row['option_name'], 'option_id' => $byName[$row['option_name']]];
            }
        }

        return $generated;
    }

    /**
     * @param list<array<string,mixed>> $beforeRows
     * @param list<array<string,mixed>> $finalRows
     * @param list<array<string,mixed>> $schemaRows
     * @return list<array<string,mixed>>
     */
    private static function conflictRows(array $beforeRows, array $finalRows, array $schemaRows): array
    {
        $incomingByName = [];
        foreach ($schemaRows as $row) {
            if (isset($row['option_name']) && is_string($row['option_name'])) {
                $incomingByName[$row['option_name']] = true;
            }
        }

        $finalNames = [];
        foreach ($finalRows as $row) {
            if (isset($row['option_name']) && is_string($row['option_name'])) {
                $finalNames[$row['option_name']] = true;
            }
        }

        $conflicts = [];
        foreach ($beforeRows as $row) {
            $name = $row['option_name'] ?? null;
            $id = $row['option_id'] ?? null;
            if (!is_string($name) || !isset($incomingByName[$name])) {
                continue;
            }

            foreach ($schemaRows as $incoming) {
                if (($incoming['option_name'] ?? null) === $name && isset($incoming['option_id']) && $incoming['option_id'] !== $id) {
                    $conflicts[] = ['option_name' => $name, 'action' => 'delete_conflicting_current'];
                    continue 2;
                }
            }

            if (!isset($finalNames[$name])) {
                $conflicts[] = ['option_name' => $name, 'action' => 'delete_conflicting_current'];
            }
        }

        return $conflicts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function savepointSnapshot(string $name, int $walFrame, array $rows): array
    {
        return [
            'name' => $name,
            'wal_frame' => $walFrame,
            'option_names' => array_column($rows, 'option_name'),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function nextSavepointSnapshot(string $name, int $walFrame, array $rows): array
    {
        return [
            'name' => $name . '_next',
            'wal_frame' => $walFrame,
            'option_names' => array_column($rows, 'option_name'),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function rowsById(array $rows): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $id = $row['option_id'] ?? null;
            if (!is_int($id)) {
                throw new \InvalidArgumentException('SQLite WordPress import JSON schema current rows require integer option_id values');
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
            'before_option_names' => array_column(array_values($beforeRows), 'option_name'),
            'after_option_names' => array_column(array_values($beforeRows), 'option_name'),
            'updated' => 0,
            'inserted' => 0,
            'deleted' => 0,
            'dirty_pages' => [],
            'wal_start_frame' => $beforeWalFrame,
            'wal_current_frame' => $currentWalFrame,
            'wal_rollback_to_frame' => $beforeWalFrame,
            'discarded_wal_frames' => [],
            'released' => false,
            'current_savepoint' => self::savepointSnapshot($name, $beforeWalFrame, array_values($beforeRows)),
            'next_savepoint' => self::nextSavepointSnapshot($name, $currentWalFrame, array_values($beforeRows)),
        ];
    }
}
