<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTenantJsonWalImportPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $imports
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function plan(array $currentRows, array $imports, array $options = []): array
    {
        if ($imports === []) {
            throw new \InvalidArgumentException('SQLite application tenant JSON WAL import requires at least one batch');
        }

        $pageSize = (int) ($options['page_size'] ?? 1024);
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite application tenant JSON WAL import page size must be a power of two at least 512');
        }

        $databasePath = (string) ($options['database_path'] ?? '/tmp/app-tenant-json-wal.sqlite');
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite application tenant JSON WAL import requires a database path');
        }

        $state = self::stateByScope($currentRows);
        $releasedState = $state;
        $batches = [];
        $released = [];
        $rolledBack = [];
        $walFrames = [];
        $nextFrame = 1;

        foreach (array_values($imports) as $index => $import) {
            $name = self::batchName($import, $index);
            $scope = self::scope($import);
            $beforeState = $state;
            $rows = self::jsonRows($import['json'] ?? null, (string) ($import['path'] ?? '$.rows'), $scope, $import);

            if ($rows['valid'] !== true) {
                $rolledBack[] = $name;
                $batches[] = self::rolledBackBatch($name, $scope, $rows['error'], $rows, [], $nextFrame, $nextFrame - 1);
                continue;
            }

            $writes = [];
            $dirtyPages = [];
            $conflicts = [];
            foreach ($rows['rows'] as $rowIndex => $row) {
                try {
                    $normalized = self::normalizeImportRow($row, $scope, $state);
                    $key = self::rowKey($normalized);
                    $conflict = isset($state[$scope][$key]);
                    if ($conflict && ($import['on_conflict'] ?? 'replace') === 'abort') {
                        throw new \InvalidArgumentException("Duplicate tenant import key {$key}");
                    }

                    $state[$scope][$key] = $normalized;
                    $pageNumber = self::pageNumber($normalized, $scope);
                    $dirtyPages[$pageNumber] = $pageNumber;
                    $writes[] = [
                        'row_index' => $rowIndex,
                        'key' => $key,
                        'scope' => $scope,
                        'table' => self::tableName($normalized),
                        'tenant_id' => $normalized['tenant_id'],
                        'group_id' => $normalized['group_id'],
                        'key_name' => $normalized['key_name'],
                        'page_number' => $pageNumber,
                        'conflict' => $conflict ? 'replace' : 'insert',
                    ];
                    if ($conflict) {
                        $conflicts[] = $key;
                    }
                } catch (\Throwable $throwable) {
                    $state = $beforeState;
                    $rolledBack[] = $name;
                    $batches[] = self::rolledBackBatch($name, $scope, $throwable->getMessage(), $rows, $writes, $nextFrame, $nextFrame - 1);
                    continue 2;
                }
            }

            $frames = [];
            foreach (array_values($dirtyPages) as $pageNumber) {
                $frames[] = [
                    'frame_index' => $nextFrame,
                    'page_number' => $pageNumber,
                    'savepoint' => $name,
                    'scope' => $scope,
                    'database_path' => $databasePath,
                    'wal_path' => $databasePath . '-wal',
                ];
                $walFrames[] = $frames[array_key_last($frames)];
                $nextFrame++;
            }

            $status = (bool) ($import['release'] ?? true) ? 'released' : 'open';
            if ($status === 'released') {
                $released[] = $name;
                foreach ($writes as $write) {
                    $writeScope = (string) $write['scope'];
                    $key = (string) $write['key'];
                    $releasedState[$writeScope][$key] = $state[$writeScope][$key];
                }
            }

            $batches[] = [
                'name' => $name,
                'status' => $status,
                'scope' => $scope,
                'json' => $rows,
                'writes' => $writes,
                'conflicts' => $conflicts,
                'dirty_pages' => array_values($dirtyPages),
                'wal_start_frame' => $frames === [] ? $nextFrame : $frames[0]['frame_index'],
                'wal_current_frame' => $nextFrame - 1,
                'wal_frames' => $frames,
            ];
        }

        return [
            'status' => 'planned',
            'current_next54' => true,
            'database_path' => $databasePath,
            'current_rows' => array_values($currentRows),
            'final_rows' => self::flattenState($state),
            'released_rows' => self::flattenState($releasedState),
            'final_keys' => self::keys($state),
            'released_keys' => self::keys($releasedState),
            'batches' => $batches,
            'released_batches' => $released,
            'rolled_back_batches' => $rolledBack,
            'wal' => [
                'path' => $databasePath . '-wal',
                'frame_count' => count($walFrames),
                'current_frame' => $nextFrame - 1,
                'frames' => $walFrames,
            ],
            'dependencies' => [
                'sqlite-application-tenant-json-wal-import',
                'sqlite-application-schema-json-savepoint-wal',
                'sqlite-application-json-import-wal-savepoint',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,array<string,mixed>>>
     */
    private static function stateByScope(array $rows): array
    {
        $state = [];
        foreach ($rows as $row) {
            $normalized = self::normalizeCurrentRow($row);
            $state[self::rowScope($normalized)][self::rowKey($normalized)] = $normalized;
        }

        ksort($state);
        return $state;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function normalizeCurrentRow(array $row): array
    {
        $scope = self::scope($row);
        return self::normalizeImportRow($row, $scope, []);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,array<string,array<string,mixed>>> $state
     * @return array<string,mixed>
     */
    private static function normalizeImportRow(array $row, string $scope, array $state): array
    {
        $global = $scope === 'global';
        $groupId = self::positiveInt($row['group_id'] ?? 1, 'group_id');
        $tenantId = $global ? 0 : self::positiveInt($row['tenant_id'] ?? ($row['group_id'] ?? 1), 'tenant_id');
        $name = $row['key_name'] ?? null;
        if (!is_string($name) || $name === '' || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('SQLite application tenant JSON import key_name must be non-empty text');
        }

        $value = $row['key_value'] ?? null;
        if (!is_string($value) && !$value instanceof SQLiteBlobValue && !$value instanceof SQLiteJsonSubtypeValue) {
            throw new \InvalidArgumentException('SQLite application tenant JSON import value must be JSON text, JSON subtype, or JSONB blob');
        }
        if (self::requiresJsonValidation($name) && self::jsonValid($value) !== true) {
            throw new \InvalidArgumentException("SQLite application tenant JSON import value for {$name} is malformed JSON");
        }

        $settingId = isset($row['setting_id']) ? self::positiveInt($row['setting_id'], 'setting_id') : self::nextId($state[$scope] ?? []);
        $loadPolicy = $global ? 'global' : (string) ($row['load_policy'] ?? 'no');
        if (!$global && !in_array($loadPolicy, ['yes', 'no', 'auto', 'on', 'off'], true)) {
            throw new \InvalidArgumentException('SQLite application tenant JSON import load_policy must be a application load_policy token');
        }

        return [
            'setting_id' => $settingId,
            'group_id' => $groupId,
            'tenant_id' => $tenantId,
            'key_name' => $name,
            'key_value' => $value,
            'load_policy' => $loadPolicy,
            'scope' => $scope,
        ];
    }

    /**
     * @param array<string,mixed> $import
     */
    private static function batchName(array $import, int $index): string
    {
        $name = (string) ($import['name'] ?? 'tenant_json_' . ($index + 1));
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('SQLite application tenant JSON WAL savepoint names must be SQL identifiers');
        }

        return $name;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function scope(array $row): string
    {
        $scope = strtolower((string) ($row['scope'] ?? (($row['global'] ?? false) ? 'global' : 'tenant')));
        if (!in_array($scope, ['tenant', 'global'], true)) {
            throw new \InvalidArgumentException('SQLite application tenant JSON WAL import scope must be tenant or global');
        }

        return $scope;
    }

    /**
     * @return array{valid:bool,path:string,rows:list<array<string,mixed>>,row_count:int,key_names:list<string>,error:?string}
     */
    /**
     * @param array<string,mixed> $import
     */
    private static function jsonRows(mixed $json, string $path, string $scope, array $import): array
    {
        if (!is_string($json) && !$json instanceof SQLiteBlobValue && !$json instanceof SQLiteJsonSubtypeValue) {
            return self::invalidJson($path, 'SQLite application tenant JSON WAL import requires JSON text, JSON subtype, or JSONB blob');
        }

        try {
            $value = $json instanceof SQLiteJsonSubtypeValue ? $json->json : $json;
            if (self::jsonValid($value) !== true) {
                return self::invalidJson($path, 'SQLite application tenant JSON WAL import source is malformed JSON');
            }
            $extracted = SQLiteJsonExtract::extract($value, $path);
            if ($extracted === null) {
                return self::invalidJson($path, 'SQLite application tenant JSON WAL import path did not match any rows');
            }
            $decoded = is_string($extracted) ? json_decode($extracted, true, 1001, JSON_THROW_ON_ERROR) : $extracted;
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('SQLite application tenant JSON WAL import path must resolve to rows');
            }
            $rows = array_is_list($decoded) ? $decoded : [$decoded];
            $normalized = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite application tenant JSON WAL import rows must be objects');
                }
                $defaults = ['scope' => $scope];
                foreach (['group_id', 'tenant_id', 'global'] as $field) {
                    if (array_key_exists($field, $import)) {
                        $defaults[$field] = $import[$field];
                    }
                }
                $normalized[] = $row + $defaults;
            }

            return [
                'valid' => true,
                'path' => $path,
                'rows' => $normalized,
                'row_count' => count($normalized),
                'key_names' => array_values(array_filter(array_map(static fn (array $row): mixed => $row['key_name'] ?? null, $normalized), 'is_string')),
                'error' => null,
            ];
        } catch (\Throwable $throwable) {
            return self::invalidJson($path, $throwable->getMessage());
        }
    }

    /**
     * @return array{valid:bool,path:string,rows:list<array<string,mixed>>,row_count:int,key_names:list<string>,error:string}
     */
    private static function invalidJson(string $path, string $error): array
    {
        return ['valid' => false, 'path' => $path, 'rows' => [], 'row_count' => 0, 'key_names' => [], 'error' => $error];
    }

    private static function jsonValid(mixed $value): bool
    {
        return $value instanceof SQLiteBlobValue
            ? SQLiteJsonValidity::jsonValid($value, SQLiteJsonValidity::FLAG_STRICT_JSONB | SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB)
            : SQLiteJsonValidity::jsonValid((string) $value, SQLiteJsonValidity::FLAG_STRICT_TEXT | SQLiteJsonValidity::FLAG_JSON5_TEXT);
    }

    private static function requiresJsonValidation(string $name): bool
    {
        return str_starts_with($name, 'theme_mods_')
            || str_starts_with($name, 'widget_')
            || str_ends_with($name, '_settings')
            || str_ends_with($name, '_config')
            || str_ends_with($name, '_json');
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowScope(array $row): string
    {
        return (string) ($row['scope'] ?? (((int) ($row['tenant_id'] ?? 0)) === 0 ? 'global' : 'tenant'));
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowKey(array $row): string
    {
        return self::rowScope($row) === 'global'
            ? 'global:' . $row['group_id'] . ':' . $row['key_name']
            : 'tenant:' . $row['group_id'] . ':' . $row['tenant_id'] . ':' . $row['key_name'];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function pageNumber(array $row, string $scope): int
    {
        $id = (int) ($row['setting_id'] ?? 1);
        $base = $scope === 'global' ? 40 : 2 + (((int) $row['tenant_id'] - 1) * 16);
        return $base + intdiv($id - 1, 64);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function tableName(array $row): string
    {
        if (self::rowScope($row) === 'global') {
            return 'app_tenant_settings';
        }

        return ((int) $row['tenant_id']) === 1 ? 'app_settings' : 'app_tenant_' . $row['tenant_id'] . '_settings';
    }

    private static function positiveInt(mixed $value, string $field): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite application tenant JSON import {$field} must be a positive integer");
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \InvalidArgumentException("SQLite application tenant JSON import {$field} must be positive");
        }

        return $int;
    }

    /**
     * @param array<string,array<string,mixed>> $rows
     */
    private static function nextId(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, (int) ($row['setting_id'] ?? 0));
        }

        return $max + 1;
    }

    /**
     * @param array<string,array<string,array<string,mixed>>> $state
     * @return list<array<string,mixed>>
     */
    private static function flattenState(array $state): array
    {
        $rows = [];
        foreach ($state as $scopedRows) {
            ksort($scopedRows);
            foreach ($scopedRows as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param array<string,array<string,array<string,mixed>>> $state
     * @return list<string>
     */
    private static function keys(array $state): array
    {
        $keys = [];
        foreach ($state as $scopedRows) {
            foreach ($scopedRows as $key => $_row) {
                $keys[] = $key;
            }
        }
        sort($keys);

        return $keys;
    }

    /**
     * @param array{valid:bool,path:string,rows:list<array<string,mixed>>,row_count:int,key_names:list<string>,error:?string} $json
     * @param list<array<string,mixed>> $writes
     * @return array<string,mixed>
     */
    private static function rolledBackBatch(string $name, string $scope, ?string $error, array $json, array $writes, int $startFrame, int $currentFrame): array
    {
        return [
            'name' => $name,
            'status' => 'rolled_back',
            'scope' => $scope,
            'error' => $error,
            'json' => $json,
            'writes' => $writes,
            'dirty_pages' => [],
            'wal_start_frame' => $startFrame,
            'wal_current_frame' => $currentFrame,
            'wal_frames' => [],
        ];
    }
}
