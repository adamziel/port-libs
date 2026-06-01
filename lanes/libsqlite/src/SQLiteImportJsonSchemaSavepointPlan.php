<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteImportJsonSchemaSavepointPlan
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
            throw new \InvalidArgumentException('SQLite Application import JSON schema savepoint plan requires at least one import');
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

            $stagePlan = self::applyRows(array_values($visibleRows), $schemaPlan['rows'], (bool) ($options['replace_conflicts'] ?? true));
            if (!$stagePlan['valid']) {
                $rolledBack[] = $name;
                $batches[] = self::rolledBackBatch($name, (string) $stagePlan['error'], $jsonPlan, $beforeRows, $beforeWalFrame, $currentWalFrame);
                continue;
            }

            $offsetFrames = [];
            foreach ($stagePlan['dirty_pages'] as $pageNumber) {
                $currentWalFrame++;
                $offset = [
                    'frame_index' => $currentWalFrame,
                    'page_number' => $pageNumber,
                    'commit_frame' => $pageNumber === end($stagePlan['dirty_pages']),
                    'savepoint' => $name,
                ];
                $offsetFrames[] = $offset;
                $walFrames[] = $offset;
            }

            $visibleRows = self::rowsById($stagePlan['final_rows']);
            $batches[] = [
                'name' => $name,
                'status' => (bool) ($import['release'] ?? true) ? 'released' : 'open',
                'error' => null,
                'json' => $jsonPlan,
                'before_key_names' => array_column(array_values($beforeRows), 'key_name'),
                'after_key_names' => array_column(array_values($visibleRows), 'key_name'),
                'updated' => count($stagePlan['updated']),
                'inserted' => count($stagePlan['inserted']),
                'deleted' => count($stagePlan['deleted']),
                'dirty_pages' => $stagePlan['dirty_pages'],
                'schema_defaulted_fields' => $schemaPlan['defaulted_fields'],
                'schema_generated_ids' => self::generatedIds($schemaPlan['rows'], $stagePlan['final_rows']),
                'schema_conflicts' => $stagePlan['conflicts'],
                'wal_start_frame' => $beforeWalFrame,
                'wal_current_frame' => $currentWalFrame,
                'wal_frames' => $offsetFrames,
                'released' => (bool) ($import['release'] ?? true),
                'current_savepoint' => self::savepointSnapshot($name, $beforeWalFrame, array_values($beforeRows)),
                'next_savepoint' => self::nextSavepointSnapshot($name, $currentWalFrame, $stagePlan['final_rows']),
            ];

            if ((bool) ($import['release'] ?? true)) {
                $released[] = $name;
                $releasedRows = $visibleRows;
            }
        }

        return [
            'status' => 'planned',
            'schema_savepoint_import' => true,
            'database_path' => (string) ($options['database_path'] ?? '/tmp/app-import-json-schema-savepoint.sqlite'),
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
                'path' => (string) ($options['database_path'] ?? '/tmp/app-import-json-schema-savepoint.sqlite') . '-wal',
                'current_frame' => $currentWalFrame,
                'frame_count' => count($walFrames),
                'frames' => $walFrames,
                'schema_savepoint_import' => true,
            ],
            'dependencies' => [
                'sqlite-application-import-json-schema-savepoint',
                'sqlite-application-json-import-wal-savepoint',
                'sqlite-application-import-transaction-current',
                'sqlite-json-extract',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $schema
     * @return array{required:list<string>,allowed:list<string>,load_policy:list<string>,defaults:array<string,mixed>,json_key_patterns:list<string>,reject_unknown:bool,generate_setting_id:bool}
     */
    private static function schema(array $schema): array
    {
        return [
            'required' => self::stringList($schema['required'] ?? ['key_name', 'key_value']),
            'allowed' => self::stringList($schema['allowed'] ?? ['setting_id', 'key_name', 'name', 'key_value', 'value', 'load_policy']),
            'load_policy' => self::stringList($schema['load_policy'] ?? ['yes', 'no', 'auto', 'on', 'off']),
            'defaults' => is_array($schema['defaults'] ?? null) ? $schema['defaults'] : ['load_policy' => 'no'],
            'json_key_patterns' => self::stringList($schema['json_key_patterns'] ?? ['/^module_/', '/_settings$/', '/^component_/']),
            'reject_unknown' => (bool) ($schema['reject_unknown'] ?? true),
            'generate_setting_id' => (bool) ($schema['generate_setting_id'] ?? true),
        ];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite Application import JSON schema options require string lists');
        }

        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException('SQLite Application import JSON schema options require non-empty strings');
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
        $name = (string) ($import['name'] ?? 'app_import_schema_' . ($index + 1));
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('SQLite Application import JSON schema savepoint names must be SQL identifiers');
        }

        return $name;
    }

    /**
     * @return array{valid:bool,path:string,rows:list<array<string,mixed>>,key_names:list<string>,row_count:int,error:?string}
     */
    private static function jsonRows(mixed $json, string $path): array
    {
        if (!is_string($json) && !$json instanceof SQLiteBlobValue && !$json instanceof SQLiteJsonSubtypeValue) {
            return self::invalidJson($path, 'SQLite Application import JSON schema source requires text, JSON subtype, or JSONB blob input');
        }

        try {
            $value = $json instanceof SQLiteJsonSubtypeValue ? $json->json : $json;
            $valid = $value instanceof SQLiteBlobValue
                ? SQLiteJsonValidity::jsonValid($value, SQLiteJsonValidity::FLAG_STRICT_JSONB | SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB)
                : SQLiteJsonValidity::jsonValid((string) $value, SQLiteJsonValidity::FLAG_STRICT_TEXT | SQLiteJsonValidity::FLAG_JSON5_TEXT);
            if ($valid !== true) {
                return self::invalidJson($path, 'SQLite Application import JSON schema source is malformed JSON');
            }

            $extracted = SQLiteJsonExtract::extract($value, $path);
            if ($extracted === null) {
                return self::invalidJson($path, 'SQLite Application import JSON schema path did not match any rows');
            }

            $decoded = is_string($extracted) ? json_decode($extracted, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR) : $extracted;
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('SQLite Application import JSON schema path must resolve to an object or array of objects');
            }

            $rows = array_is_list($decoded) ? $decoded : [$decoded];
            $normalized = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite Application import JSON schema rows must be objects');
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
     * @param array{required:list<string>,allowed:list<string>,load_policy:list<string>,defaults:array<string,mixed>,json_key_patterns:list<string>,reject_unknown:bool,generate_setting_id:bool} $schema
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
            $candidate['key_name'] = $row['key_name'] ?? $row['name'] ?? null;
            $candidate['key_value'] = $row['key_value'] ?? $row['value'] ?? null;
            if (array_key_exists('load_policy', $row)) {
                $candidate['load_policy'] = $row['load_policy'];
            }
            if (array_key_exists('setting_id', $row)) {
                $candidate['setting_id'] = $row['setting_id'];
                if (is_int($row['setting_id']) && $row['setting_id'] > $maxId) {
                    $maxId = $row['setting_id'];
                }
            } elseif ($schema['generate_setting_id']) {
                $candidate['setting_id'] = ++$maxId;
                $defaulted[] = ['row' => $index, 'field' => 'setting_id', 'value' => $candidate['setting_id'], 'source' => 'generated'];
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

            if (!is_string($candidate['key_name'] ?? null) || $candidate['key_name'] === '' || str_contains((string) $candidate['key_name'], "\0")) {
                $violations[] = ['row' => $index, 'field' => 'key_name', 'rule' => 'non_empty_text'];
            }

            if (isset($candidate['setting_id']) && (!is_int($candidate['setting_id']) || $candidate['setting_id'] <= 0)) {
                $violations[] = ['row' => $index, 'field' => 'setting_id', 'rule' => 'positive_integer'];
            }

            if (!is_string($candidate['load_policy'] ?? null) || !in_array($candidate['load_policy'], $schema['load_policy'], true)) {
                $violations[] = ['row' => $index, 'field' => 'load_policy', 'rule' => 'enum'];
            }

            if (self::expectsJsonValue(is_string($candidate['key_name'] ?? null) ? $candidate['key_name'] : '', $schema) && !self::isJsonText($candidate['key_value'] ?? null)) {
                $violations[] = ['row' => $index, 'field' => 'key_value', 'rule' => 'json_text'];
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
            'error' => $violations === [] ? null : 'SQLite Application import JSON schema row failed schema/default validation',
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
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $incomingRows
     * @return array{valid:bool,final_rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,deleted:list<array<string,mixed>>,dirty_pages:list<int>,conflicts:list<array<string,string>>,error:?string}
     */
    private static function applyRows(array $currentRows, array $incomingRows, bool $replaceConflicts): array
    {
        $byId = self::rowsById($currentRows);
        $idByName = [];
        foreach ($byId as $id => $row) {
            if (isset($row['key_name']) && is_string($row['key_name'])) {
                $idByName[$row['key_name']] = $id;
            }
        }

        $inserted = [];
        $updated = [];
        $deleted = [];
        $conflicts = [];
        $dirtyPages = [];

        foreach ($incomingRows as $row) {
            $id = $row['setting_id'] ?? null;
            $name = $row['key_name'] ?? null;
            if (!is_int($id) || !is_string($name)) {
                return self::invalidApply('SQLite Application import JSON schema rows require setting_id and key_name');
            }

            $conflictId = $idByName[$name] ?? null;
            if ($conflictId !== null && $conflictId !== $id) {
                if (!$replaceConflicts) {
                    return self::invalidApply('SQLite Application import JSON schema duplicate key_name conflict');
                }

                $deleted[] = $byId[$conflictId];
                unset($byId[$conflictId]);
                $dirtyPages[self::pageNumber($conflictId)] = true;
                $conflicts[] = ['key_name' => $name, 'action' => 'delete_conflicting_current'];
            }

            if (isset($byId[$id])) {
                $updated[] = $row;
            } else {
                $inserted[] = $row;
            }

            $byId[$id] = $row;
            $idByName[$name] = $id;
            $dirtyPages[self::pageNumber($id)] = true;
        }

        ksort($byId);
        ksort($dirtyPages);

        return [
            'valid' => true,
            'final_rows' => array_values($byId),
            'inserted' => $inserted,
            'updated' => $updated,
            'deleted' => $deleted,
            'dirty_pages' => array_map('intval', array_keys($dirtyPages)),
            'conflicts' => $conflicts,
            'error' => null,
        ];
    }

    /**
     * @return array{valid:bool,final_rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,deleted:list<array<string,mixed>>,dirty_pages:list<int>,conflicts:list<array<string,string>>,error:string}
     */
    private static function invalidApply(string $error): array
    {
        return [
            'valid' => false,
            'final_rows' => [],
            'inserted' => [],
            'updated' => [],
            'deleted' => [],
            'dirty_pages' => [],
            'conflicts' => [],
            'error' => $error,
        ];
    }

    private static function pageNumber(int $settingId): int
    {
        return 1 + intdiv(max(1, $settingId) + 63, 64);
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
            if (isset($row['key_name'], $row['setting_id']) && is_string($row['key_name'])) {
                $byName[$row['key_name']] = $row['setting_id'];
            }
        }

        $generated = [];
        foreach ($schemaRows as $row) {
            if (isset($row['key_name'], $row['setting_id']) && is_string($row['key_name']) && isset($byName[$row['key_name']])) {
                $generated[] = ['key_name' => $row['key_name'], 'setting_id' => $byName[$row['key_name']]];
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
            if (isset($row['key_name']) && is_string($row['key_name'])) {
                $incomingByName[$row['key_name']] = true;
            }
        }

        $finalNames = [];
        foreach ($finalRows as $row) {
            if (isset($row['key_name']) && is_string($row['key_name'])) {
                $finalNames[$row['key_name']] = true;
            }
        }

        $conflicts = [];
        foreach ($beforeRows as $row) {
            $name = $row['key_name'] ?? null;
            $id = $row['setting_id'] ?? null;
            if (!is_string($name) || !isset($incomingByName[$name])) {
                continue;
            }

            foreach ($schemaRows as $incoming) {
                if (($incoming['key_name'] ?? null) === $name && isset($incoming['setting_id']) && $incoming['setting_id'] !== $id) {
                    $conflicts[] = ['key_name' => $name, 'action' => 'delete_conflicting_current'];
                    continue 2;
                }
            }

            if (!isset($finalNames[$name])) {
                $conflicts[] = ['key_name' => $name, 'action' => 'delete_conflicting_current'];
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
            'key_names' => array_column($rows, 'key_name'),
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
            'key_names' => array_column($rows, 'key_name'),
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
            $id = $row['setting_id'] ?? null;
            if (!is_int($id)) {
                throw new \InvalidArgumentException('SQLite Application import JSON schema current rows require integer setting_id values');
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
            'current_savepoint' => self::savepointSnapshot($name, $beforeWalFrame, array_values($beforeRows)),
            'next_savepoint' => self::nextSavepointSnapshot($name, $currentWalFrame, array_values($beforeRows)),
        ];
    }
}
