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
            throw new \InvalidArgumentException('SQLite Application multisite JSON WAL import requires at least one batch');
        }

        $pageSize = (int) ($options['page_size'] ?? 1024);
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite Application multisite JSON WAL import page size must be a power of two at least 512');
        }

        $databasePath = (string) ($options['database_path'] ?? '/tmp/wp-multisite-json-wal.sqlite');
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite Application multisite JSON WAL import requires a database path');
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
                        throw new \InvalidArgumentException("Duplicate multisite import key {$key}");
                    }

                    $state[$scope][$key] = $normalized;
                    $pageNumber = self::pageNumber($normalized, $scope);
                    $dirtyPages[$pageNumber] = $pageNumber;
                    $writes[] = [
                        'row_index' => $rowIndex,
                        'key' => $key,
                        'scope' => $scope,
                        'table' => self::tableName($normalized),
                        'blog_id' => $normalized['blog_id'],
                        'site_id' => $normalized['site_id'],
                        'option_name' => $normalized['option_name'],
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
                'sqlite-application-multisite-json-wal-import',
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
        $network = $scope === 'network';
        $siteId = self::positiveInt($row['site_id'] ?? 1, 'site_id');
        $blogId = $network ? 0 : self::positiveInt($row['blog_id'] ?? ($row['site_id'] ?? 1), 'blog_id');
        $name = $row['option_name'] ?? $row['meta_key'] ?? null;
        if (!is_string($name) || $name === '' || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('SQLite Application multisite JSON import option_name/meta_key must be non-empty text');
        }

        $value = $row['option_value'] ?? $row['meta_value'] ?? null;
        if (!is_string($value) && !$value instanceof SQLiteBlobValue && !$value instanceof SQLiteJsonSubtypeValue) {
            throw new \InvalidArgumentException('SQLite Application multisite JSON import value must be JSON text, JSON subtype, or JSONB blob');
        }
        if (self::requiresJsonValidation($name) && self::jsonValid($value) !== true) {
            throw new \InvalidArgumentException("SQLite Application multisite JSON import value for {$name} is malformed JSON");
        }

        $idKey = $network ? 'meta_id' : 'option_id';
        $id = isset($row[$idKey]) ? self::positiveInt($row[$idKey], $idKey) : self::nextId($state[$scope] ?? []);
        $autoload = $network ? 'network' : (string) ($row['autoload'] ?? 'no');
        if (!$network && !in_array($autoload, ['yes', 'no', 'auto', 'on', 'off'], true)) {
            throw new \InvalidArgumentException('SQLite Application multisite JSON import autoload must be a Application autoload token');
        }

        return [
            $idKey => $id,
            'site_id' => $siteId,
            'blog_id' => $blogId,
            'option_name' => $name,
            'option_value' => $value,
            'autoload' => $autoload,
            'scope' => $scope,
        ];
    }

    /**
     * @param array<string,mixed> $import
     */
    private static function batchName(array $import, int $index): string
    {
        $name = (string) ($import['name'] ?? 'multisite_json_' . ($index + 1));
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('SQLite Application multisite JSON WAL savepoint names must be SQL identifiers');
        }

        return $name;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function scope(array $row): string
    {
        $scope = strtolower((string) ($row['scope'] ?? (($row['network'] ?? false) ? 'network' : 'blog')));
        if (!in_array($scope, ['blog', 'network'], true)) {
            throw new \InvalidArgumentException('SQLite Application multisite JSON WAL import scope must be blog or network');
        }

        return $scope;
    }

    /**
     * @return array{valid:bool,path:string,rows:list<array<string,mixed>>,row_count:int,option_names:list<string>,error:?string}
     */
    /**
     * @param array<string,mixed> $import
     */
    private static function jsonRows(mixed $json, string $path, string $scope, array $import): array
    {
        if (!is_string($json) && !$json instanceof SQLiteBlobValue && !$json instanceof SQLiteJsonSubtypeValue) {
            return self::invalidJson($path, 'SQLite Application multisite JSON WAL import requires JSON text, JSON subtype, or JSONB blob');
        }

        try {
            $value = $json instanceof SQLiteJsonSubtypeValue ? $json->json : $json;
            if (self::jsonValid($value) !== true) {
                return self::invalidJson($path, 'SQLite Application multisite JSON WAL import source is malformed JSON');
            }
            $extracted = SQLiteJsonExtract::extract($value, $path);
            if ($extracted === null) {
                return self::invalidJson($path, 'SQLite Application multisite JSON WAL import path did not match any rows');
            }
            $decoded = is_string($extracted) ? json_decode($extracted, true, 1001, JSON_THROW_ON_ERROR) : $extracted;
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('SQLite Application multisite JSON WAL import path must resolve to rows');
            }
            $rows = array_is_list($decoded) ? $decoded : [$decoded];
            $normalized = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite Application multisite JSON WAL import rows must be objects');
                }
                $defaults = ['scope' => $scope];
                foreach (['site_id', 'blog_id', 'network'] as $field) {
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
                'option_names' => array_values(array_filter(array_map(static fn (array $row): mixed => $row['option_name'] ?? $row['meta_key'] ?? null, $normalized), 'is_string')),
                'error' => null,
            ];
        } catch (\Throwable $throwable) {
            return self::invalidJson($path, $throwable->getMessage());
        }
    }

    /**
     * @return array{valid:bool,path:string,rows:list<array<string,mixed>>,row_count:int,option_names:list<string>,error:string}
     */
    private static function invalidJson(string $path, string $error): array
    {
        return ['valid' => false, 'path' => $path, 'rows' => [], 'row_count' => 0, 'option_names' => [], 'error' => $error];
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
        return (string) ($row['scope'] ?? (((int) ($row['blog_id'] ?? 0)) === 0 ? 'network' : 'blog'));
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowKey(array $row): string
    {
        return self::rowScope($row) === 'network'
            ? 'network:' . $row['site_id'] . ':' . $row['option_name']
            : 'blog:' . $row['site_id'] . ':' . $row['blog_id'] . ':' . $row['option_name'];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function pageNumber(array $row, string $scope): int
    {
        $id = (int) ($row[$scope === 'network' ? 'meta_id' : 'option_id'] ?? 1);
        $base = $scope === 'network' ? 40 : 2 + (((int) $row['blog_id'] - 1) * 16);
        return $base + intdiv($id - 1, 64);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function tableName(array $row): string
    {
        return self::rowScope($row) === 'network' ? 'wp_sitemeta' : 'wp_' . $row['blog_id'] . '_options';
    }

    private static function positiveInt(mixed $value, string $field): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite Application multisite JSON import {$field} must be a positive integer");
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \InvalidArgumentException("SQLite Application multisite JSON import {$field} must be positive");
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
            $max = max($max, (int) ($row['option_id'] ?? $row['meta_id'] ?? 0));
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
     * @param array{valid:bool,path:string,rows:list<array<string,mixed>>,row_count:int,option_names:list<string>,error:?string} $json
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
