<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTenantJsonWalCurrentNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array{scope:string,tenant_id?:int,group_id?:int,json:mixed,path?:string,release?:bool,on_error?:string}> $imports
     * @param array{database_path?:string,page_size?:int,first_frame?:int} $options
     * @return array<string,mixed>
     */
    public static function plan(array $currentRows, array $imports, array $options = []): array
    {
        if ($imports === []) {
            throw new \InvalidArgumentException('SQLite Application global JSON WAL plan requires at least one import batch');
        }

        $databasePath = (string) ($options['database_path'] ?? '/tmp/app-global-json.sqlite');
        $pageSize = (int) ($options['page_size'] ?? 4096);
        $currentFrame = (int) ($options['first_frame'] ?? 0);
        if ($databasePath === '' || $databasePath[0] !== '/' || str_contains($databasePath, "\0") || str_contains($databasePath, '..')) {
            throw new \InvalidArgumentException('SQLite Application global JSON WAL plan requires a safe absolute database path');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite Application global JSON WAL page size must be a power of two at least 512');
        }
        if ($currentFrame < 0) {
            throw new \InvalidArgumentException('SQLite Application global JSON WAL first frame cannot be negative');
        }

        $rows = self::normalizeCurrentRows($currentRows);
        $releasedRows = $rows;
        $batches = [];
        $frames = [];
        $released = [];
        $rolledBack = [];
        $dirtyPages = [];

        foreach (array_values($imports) as $batchIndex => $import) {
            $scope = strtolower((string) ($import['scope'] ?? ''));
            if (!in_array($scope, ['tenant', 'global'], true)) {
                throw new \InvalidArgumentException('SQLite Application global JSON WAL import scope must be tenant or global');
            }
            $onError = strtolower((string) ($import['on_error'] ?? 'rollback'));
            if (!in_array($onError, ['rollback', 'abort'], true)) {
                throw new \InvalidArgumentException('SQLite Application global JSON WAL import on_error must be rollback or abort');
            }

            $beforeRows = $rows;
            $beforeFrame = $currentFrame;
            $identity = $scope === 'global'
                ? self::sharedTenantIdentity($import['group_id'] ?? 1)
                : self::partitionIdentity($import['tenant_id'] ?? 1);
            $json = self::jsonRows($import['json'] ?? null, (string) ($import['path'] ?? '$.rows'), $scope);

            if (!$json['valid']) {
                if ($onError === 'abort') {
                    throw new \LogicException((string) $json['error']);
                }
                $rolledBack[] = $identity['savepoint'];
                $batches[] = self::rolledBackBatch($identity, $json, $beforeFrame, $currentFrame, $beforeRows);
                continue;
            }

            $changed = [];
            $conflicts = [];
            foreach ($json['rows'] as $row) {
                $key = self::rowKey($scope, $identity, $row);
                if (isset($rows[$key])) {
                    $conflicts[] = $key;
                }
                $rows[$key] = self::mergeRow($scope, $identity, $row, $rows[$key] ?? null);
                $changed[$key] = $rows[$key];
            }

            $batchFrames = [];
            foreach ($changed as $key => $row) {
                $currentFrame++;
                $page = self::pageNumber($scope, $identity, $row);
                $frame = [
                    'frame_index' => $currentFrame,
                    'page_number' => $page,
                    'scope' => $scope,
                    'table' => $identity['table'],
                    'row_key' => $key,
                    'commit_frame' => $key === array_key_last($changed),
                ];
                $frames[] = $frame;
                $batchFrames[] = $frame;
                $dirtyPages[$page] = true;
            }

            $isReleased = (bool) ($import['release'] ?? true);
            if ($isReleased) {
                $releasedRows = $rows;
                $released[] = $identity['savepoint'];
            }

            $batches[] = [
                'name' => $identity['savepoint'],
                'status' => $isReleased ? 'released' : 'open',
                'scope' => $scope,
                'table' => $identity['table'],
                'tenant_id' => $identity['tenant_id'],
                'group_id' => $identity['group_id'],
                'json' => $json,
                'changed_row_keys' => array_keys($changed),
                'conflict_row_keys' => $conflicts,
                'wal_start_frame' => $beforeFrame,
                'wal_current_frame' => $currentFrame,
                'wal_frames' => $batchFrames,
                'dirty_pages' => array_values(array_unique(array_column($batchFrames, 'page_number'))),
                'released' => $isReleased,
            ];
        }

        ksort($dirtyPages);

        return [
            'status' => 'planned',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'page_size' => $pageSize,
            'batch_count' => count($batches),
            'released_batches' => $released,
            'rolled_back_batches' => $rolledBack,
            'batches' => $batches,
            'current_rows' => array_values($currentRows),
            'final_rows' => array_values($rows),
            'released_rows' => array_values($releasedRows),
            'dirty_pages' => array_map('intval', array_keys($dirtyPages)),
            'wal' => [
                'start_frame' => (int) ($options['first_frame'] ?? 0),
                'current_frame' => $currentFrame,
                'frame_count' => count($frames),
                'bytes' => 32 + (count($frames) * (24 + $pageSize)),
                'frames' => $frames,
                'current_next' => true,
            ],
            'reader_visibility' => [
                'current_end_frame' => (int) ($options['first_frame'] ?? 0),
                'next_end_frame' => $currentFrame,
                'current_rows_visible' => count($currentRows),
                'next_rows_visible' => count($rows),
                'released_rows_visible' => count($releasedRows),
            ],
            'dependencies' => [
                'sqlite-application-global-json-wal-current-next',
                'sqlite-json-validity',
                'sqlite-wal-current-next-frame-accounting',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private static function normalizeCurrentRows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $scope = strtolower((string) ($row['scope'] ?? (isset($row['group_id']) ? 'global' : 'tenant')));
            $identity = $scope === 'global'
                ? self::sharedTenantIdentity($row['group_id'] ?? 1)
                : self::partitionIdentity($row['tenant_id'] ?? 1);
            $normalized[self::rowKey($scope, $identity, $row)] = self::mergeRow($scope, $identity, $row, null);
        }
        ksort($normalized);

        return $normalized;
    }

    /** @return array{scope:string,table:string,tenant_id:int|null,group_id:int|null,savepoint:string} */
    private static function partitionIdentity(mixed $tenantId): array
    {
        $tenantId = (int) $tenantId;
        if ($tenantId < 1) {
            throw new \InvalidArgumentException('SQLite Application global JSON WAL tenant_id must be positive');
        }

        return [
            'scope' => 'tenant',
            'table' => $tenantId === 1 ? 'app_settings' : 'app_tenant_' . $tenantId . '_settings',
            'tenant_id' => $tenantId,
            'group_id' => null,
            'savepoint' => 'tenant_' . $tenantId . '_json',
        ];
    }

    /** @return array{scope:string,table:string,tenant_id:int|null,group_id:int|null,savepoint:string} */
    private static function sharedTenantIdentity(mixed $groupId): array
    {
        $groupId = (int) $groupId;
        if ($groupId < 1) {
            throw new \InvalidArgumentException('SQLite Application global JSON WAL group_id must be positive');
        }

        return [
            'scope' => 'global',
            'table' => 'app_tenant_settings',
            'tenant_id' => null,
            'group_id' => $groupId,
            'savepoint' => 'global_' . $groupId . '_json',
        ];
    }

    /**
     * @return array{valid:bool,path:string,rows:list<array<string,mixed>>,row_count:int,error:string|null}
     */
    private static function jsonRows(mixed $json, string $path, string $scope): array
    {
        if (!is_string($json) && !$json instanceof SQLiteJsonSubtypeValue && !$json instanceof SQLiteBlobValue) {
            return self::invalidJson($path, 'global JSON WAL import requires text JSON, JSON subtype, or JSONB blob');
        }

        try {
            $value = $json instanceof SQLiteJsonSubtypeValue ? $json->json : $json;
            if ($value instanceof SQLiteBlobValue) {
                $valid = SQLiteJsonValidity::jsonValid($value, SQLiteJsonValidity::FLAG_STRICT_JSONB | SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB);
            } else {
                $valid = SQLiteJsonValidity::jsonValid((string) $value, SQLiteJsonValidity::FLAG_STRICT_TEXT | SQLiteJsonValidity::FLAG_JSON5_TEXT);
            }
            if ($valid !== true) {
                return self::invalidJson($path, 'global JSON WAL import source is malformed JSON');
            }
            $extracted = SQLiteJsonExtract::extract($value, $path);
            if ($extracted === null) {
                return self::invalidJson($path, 'global JSON WAL import path did not match rows');
            }
            $decoded = is_string($extracted)
                ? json_decode($extracted, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR)
                : $extracted;
            $rows = self::normalizeJsonRows($decoded, $scope);

            return ['valid' => true, 'path' => $path, 'rows' => $rows, 'row_count' => count($rows), 'error' => null];
        } catch (\Throwable $throwable) {
            return self::invalidJson($path, $throwable->getMessage());
        }
    }

    /** @return array{valid:bool,path:string,rows:list<array<string,mixed>>,row_count:int,error:string} */
    private static function invalidJson(string $path, string $error): array
    {
        return ['valid' => false, 'path' => $path, 'rows' => [], 'row_count' => 0, 'error' => $error];
    }

    /** @return list<array<string,mixed>> */
    private static function normalizeJsonRows(mixed $decoded, string $scope): array
    {
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('global JSON WAL import path must resolve to an object or array of objects');
        }
        $rows = array_is_list($decoded) ? $decoded : [$decoded];
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('global JSON WAL import rows must be objects');
            }
            $nameKey = 'key_name';
            $valueKey = 'key_value';
            $name = $row[$nameKey] ?? $row['name'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException("global JSON WAL import row requires {$nameKey}");
            }
            $normalized[] = [
                $nameKey => $name,
                $valueKey => $row[$valueKey] ?? $row['value'] ?? '',
                'load_policy' => $scope === 'tenant' ? (string) ($row['load_policy'] ?? 'no') : null,
            ];
        }

        return $normalized;
    }

    /** @param array{table:string,tenant_id:int|null,group_id:int|null} $identity */
    private static function rowKey(string $scope, array $identity, array $row): string
    {
        $name = (string) ($row['key_name'] ?? $row['name'] ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite Application global JSON WAL rows require non-empty names');
        }

        return $identity['table'] . ':' . $name;
    }

    /** @param array{table:string,tenant_id:int|null,group_id:int|null} $identity */
    private static function mergeRow(string $scope, array $identity, array $row, ?array $current): array
    {
        if ($scope === 'global') {
            $name = (string) ($row['key_name'] ?? $row['name'] ?? $current['key_name'] ?? '');
            return [
                'scope' => 'global',
                'table' => $identity['table'],
                'group_id' => $identity['group_id'],
                'key_name' => $name,
                'key_value' => $row['key_value'] ?? $row['value'] ?? $current['key_value'] ?? '',
            ];
        }

        $name = (string) ($row['key_name'] ?? $row['name'] ?? $current['key_name'] ?? '');
        return [
            'scope' => 'tenant',
            'table' => $identity['table'],
            'tenant_id' => $identity['tenant_id'],
            'key_name' => $name,
            'key_value' => $row['key_value'] ?? $row['value'] ?? $current['key_value'] ?? '',
            'load_policy' => (string) ($row['load_policy'] ?? $current['load_policy'] ?? 'no'),
        ];
    }

    /** @param array{tenant_id:int|null,group_id:int|null} $identity */
    private static function pageNumber(string $scope, array $identity, array $row): int
    {
        $name = (string) $row['key_name'];
        $base = $scope === 'global' ? 900 + ((int) $identity['group_id'] * 37) : 100 + ((int) $identity['tenant_id'] * 53);

        return $base + (crc32($name) % 31);
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $json
     * @param array<string,array<string,mixed>> $beforeRows
     * @return array<string,mixed>
     */
    private static function rolledBackBatch(array $identity, array $json, int $beforeFrame, int $currentFrame, array $beforeRows): array
    {
        return [
            'name' => $identity['savepoint'],
            'status' => 'rolled_back',
            'scope' => $identity['scope'],
            'table' => $identity['table'],
            'tenant_id' => $identity['tenant_id'],
            'group_id' => $identity['group_id'],
            'json' => $json,
            'changed_row_keys' => [],
            'conflict_row_keys' => [],
            'wal_start_frame' => $beforeFrame,
            'wal_current_frame' => $currentFrame,
            'wal_rollback_to_frame' => $beforeFrame,
            'wal_frames' => [],
            'dirty_pages' => [],
            'released' => false,
            'row_count_after_rollback' => count($beforeRows),
        ];
    }
}
