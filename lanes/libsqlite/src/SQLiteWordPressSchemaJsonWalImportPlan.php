<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWordPressSchemaJsonWalImportPlan
{
    /**
     * @param list<array{option_id:int,option_name:string,option_value:mixed,autoload?:string,page_number?:int}> $currentRows
     * @param list<array{option_name:string,function?:string,path:string,value:mixed,page_number?:int,wal_frame_index?:int,statement?:string}> $mutations
     * @param array<string,array{sql?:string,type?:string}> $existingObjects
     * @param array{schema?:array<string,mixed>,json?:array<string,mixed>,database_path?:string,page_size?:int,wal_autocheckpoint?:int} $options
     * @return array<string,mixed>
     */
    public static function plan(
        string $schemaSql,
        array $currentRows,
        array $mutations,
        array $existingObjects = [],
        array $options = [],
    ): array {
        $pageSize = self::pageSize($options['page_size'] ?? ($options['json']['page_size'] ?? 512));
        $databasePath = self::databasePath($options['database_path'] ?? '/tmp/wp-schema-json-import.sqlite');
        $walAutocheckpoint = self::nonNegativeInt($options['wal_autocheckpoint'] ?? 1000, 'wal_autocheckpoint');
        $schemaOptions = is_array($options['schema'] ?? null) ? $options['schema'] : [];
        $jsonOptions = is_array($options['json'] ?? null) ? $options['json'] : [];
        $schemaOptions['next_rootpage'] ??= self::nextRootPage($currentRows);
        $jsonOptions['page_size'] = $pageSize;

        $schemaPlan = SQLiteWordPressSchemaBulkImportPlan::plan($schemaSql, $existingObjects, $schemaOptions);
        $jsonPlan = SQLiteWordPressJsonImportSavepointPlan::plan($currentRows, $mutations, $jsonOptions);

        $schemaFrames = self::schemaFrames($schemaPlan['objects'], $pageSize);
        $jsonFrames = self::jsonFrames($jsonPlan['applied'], $pageSize, count($schemaFrames));
        $failedFrames = self::failedFrames($jsonPlan['failed'], $pageSize, count($schemaFrames) + count($jsonFrames));
        $walFrames = array_merge($schemaFrames, $jsonFrames);
        $yielded = self::yielded($schemaPlan, $jsonPlan);
        $dirtyPages = self::dirtyPages($schemaPlan, $jsonPlan);
        $status = self::status($schemaPlan, $jsonPlan);

        return [
            'status' => $status,
            'database_path' => $databasePath,
            'page_size' => $pageSize,
            'wal_autocheckpoint' => $walAutocheckpoint,
            'schema' => $schemaPlan,
            'json' => $jsonPlan,
            'schema_applied_count' => $schemaPlan['applied_count'],
            'json_applied_count' => count($jsonPlan['applied']),
            'json_failed_count' => count($jsonPlan['failed']),
            'yielded_count' => count($yielded),
            'yielded' => $yielded,
            'wal_frames' => $walFrames,
            'failed_wal_frames' => $failedFrames,
            'wal_frame_count' => count($walFrames),
            'next_wal_frame' => count($schemaFrames) + count($jsonFrames) + count($failedFrames) + 1,
            'checkpoint_admission' => [
                'ready' => $status !== 'rolled_back',
                'reason' => $status === 'rolled_back' ? 'schema_json_import_rolled_back' : 'schema_json_import_frames_ready',
                'frame_count' => count($walFrames),
                'dirty_page_count' => count($dirtyPages),
                'requires_exclusive_lock' => true,
                'wal_autocheckpoint_reached' => count($walFrames) >= $walAutocheckpoint && $walAutocheckpoint > 0,
            ],
            'dirty_pages' => $dirtyPages,
            'schema_cookie' => [
                'before' => $schemaPlan['schema_version_before'],
                'after' => $schemaPlan['schema_version_after'],
                'changed' => $schemaPlan['schema_version_after'] !== $schemaPlan['schema_version_before'],
            ],
            'data_cookie' => [
                'before' => $schemaPlan['data_version_before'],
                'after' => $schemaPlan['data_version_after'],
                'changed' => $schemaPlan['data_version_after'] !== $schemaPlan['data_version_before'] || $jsonPlan['database_changed'],
            ],
            'commit_order' => array_values(array_filter([
                $schemaPlan['applied_count'] > 0 ? 'write_schema_pages' : null,
                count($jsonPlan['applied']) > 0 ? 'write_json_option_pages' : null,
                'sync_wal',
                'update_schema_cookie',
                'checkpoint_or_leave_wal',
            ])),
            'dependencies' => [
                'sqlite-wordpress-schema-bulk-import-current-next33',
                'sqlite-wordpress-json-import-savepoint-current-next31',
                'sqlite-wal-import-yield-current-next41',
            ],
        ];
    }

    private static function pageSize(mixed $value): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException('SQLite WordPress schema JSON WAL import page size must be an integer');
        }
        $pageSize = (int) $value;
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite WordPress schema JSON WAL import page size must be a power of two at least 512');
        }

        return $pageSize;
    }

    private static function databasePath(mixed $value): string
    {
        if (!is_string($value) || $value === '' || str_contains($value, "\0") || !str_starts_with($value, '/')) {
            throw new \InvalidArgumentException('SQLite WordPress schema JSON WAL import database_path must be an absolute path');
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite WordPress schema JSON WAL import {$label} must be a non-negative integer");
        }
        $integer = (int) $value;
        if ($integer < 0) {
            throw new \InvalidArgumentException("SQLite WordPress schema JSON WAL import {$label} must be non-negative");
        }

        return $integer;
    }

    /**
     * @param list<array{option_id:int,option_name:string,option_value:mixed,autoload?:string,page_number?:int}> $rows
     */
    private static function nextRootPage(array $rows): int
    {
        $max = 1;
        foreach ($rows as $row) {
            $page = $row['page_number'] ?? null;
            if (is_int($page)) {
                $max = max($max, $page);
            }
        }

        return $max + 1;
    }

    /**
     * @param list<array{name:string,type:string,table:string|null,sql:string,rootpage:int,autoindex_count:int,dependencies:list<string>}> $objects
     * @return list<array<string,mixed>>
     */
    private static function schemaFrames(array $objects, int $pageSize): array
    {
        $frames = [];
        foreach ($objects as $object) {
            if ($object['rootpage'] <= 0) {
                continue;
            }
            $frames[] = [
                'frame_index' => count($frames) + 1,
                'kind' => 'schema',
                'object' => $object['name'],
                'object_type' => $object['type'],
                'page_number' => $object['rootpage'],
                'page_size' => $pageSize,
                'commit' => false,
            ];
        }
        if ($frames !== []) {
            $frames[array_key_last($frames)]['commit'] = true;
        }

        return $frames;
    }

    /**
     * @param list<array<string,mixed>> $applied
     * @return list<array<string,mixed>>
     */
    private static function jsonFrames(array $applied, int $pageSize, int $offset): array
    {
        $frames = [];
        foreach ($applied as $index => $row) {
            $frames[] = [
                'frame_index' => $offset + $index + 1,
                'kind' => 'json_option',
                'statement' => $row['statement'],
                'option_name' => $row['option_name'],
                'page_number' => $row['page_number'],
                'page_size' => $pageSize,
                'source_wal_frame' => $row['wal_frame_index'],
                'json_path' => $row['json_path'],
                'commit' => $index === count($applied) - 1,
            ];
        }

        return $frames;
    }

    /**
     * @param list<array<string,mixed>> $failed
     * @return list<array<string,mixed>>
     */
    private static function failedFrames(array $failed, int $pageSize, int $offset): array
    {
        $frames = [];
        foreach ($failed as $index => $row) {
            $discarded = $row['rollback']['discarded_wal_frames'][0]['frame_index'] ?? null;
            $frames[] = [
                'frame_index' => $offset + $index + 1,
                'kind' => 'discarded_json_option',
                'statement' => $row['statement'],
                'option_name' => $row['option_name'],
                'page_size' => $pageSize,
                'source_wal_frame' => $discarded,
                'committed' => false,
            ];
        }

        return $frames;
    }

    /**
     * @param array<string,mixed> $schemaPlan
     * @param array<string,mixed> $jsonPlan
     * @return list<array<string,mixed>>
     */
    private static function yielded(array $schemaPlan, array $jsonPlan): array
    {
        $yielded = [];
        foreach ($schemaPlan['objects'] as $object) {
            $yielded[] = [
                'phase' => 'schema',
                'status' => 'applied',
                'name' => $object['name'],
                'type' => $object['type'],
                'rootpage' => $object['rootpage'],
            ];
        }
        foreach ($schemaPlan['skipped'] as $object) {
            $yielded[] = [
                'phase' => 'schema',
                'status' => 'skipped',
                'name' => $object['name'],
                'type' => $object['type'],
                'reason' => $object['reason'],
            ];
        }
        foreach ($jsonPlan['applied'] as $row) {
            $yielded[] = [
                'phase' => 'json',
                'status' => 'applied',
                'statement' => $row['statement'],
                'option_name' => $row['option_name'],
                'page_number' => $row['page_number'],
            ];
        }
        foreach ($jsonPlan['failed'] as $row) {
            $yielded[] = [
                'phase' => 'json',
                'status' => 'rolled_back',
                'statement' => $row['statement'],
                'option_name' => $row['option_name'],
                'error' => $row['error'],
            ];
        }

        return $yielded;
    }

    /**
     * @param array<string,mixed> $schemaPlan
     * @param array<string,mixed> $jsonPlan
     * @return list<int>
     */
    private static function dirtyPages(array $schemaPlan, array $jsonPlan): array
    {
        $pages = [];
        foreach ($schemaPlan['objects'] as $object) {
            if ($object['rootpage'] > 0) {
                $pages[$object['rootpage']] = true;
            }
        }
        foreach ($jsonPlan['commit']['committed_page_numbers'] as $page) {
            $pages[(int) $page] = true;
        }
        $numbers = array_map('intval', array_keys($pages));
        sort($numbers, SORT_NUMERIC);

        return $numbers;
    }

    /**
     * @param array<string,mixed> $schemaPlan
     * @param array<string,mixed> $jsonPlan
     */
    private static function status(array $schemaPlan, array $jsonPlan): string
    {
        if ($schemaPlan['applied_count'] === 0 && count($jsonPlan['applied']) === 0) {
            return 'noop';
        }
        if (count($jsonPlan['failed']) > 0) {
            return 'partial_json_rollback';
        }

        return 'ready';
    }
}
