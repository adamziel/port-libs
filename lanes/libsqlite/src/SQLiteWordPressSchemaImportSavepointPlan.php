<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWordPressSchemaImportSavepointPlan
{
    /**
     * @param array<string, array{sql?:string,type?:string}> $existingObjects
     * @param list<array{name?:string,dump:string,on_error?:string,release?:bool}> $batches
     * @param array{schema_version?:int|string,data_version?:int|string,next_rootpage?:int|string,page_size?:int|string,honor_if_not_exists?:bool} $options
     * @return array<string,mixed>
     */
    public static function plan(array $existingObjects, array $batches, array $options = []): array
    {
        if ($batches === []) {
            throw new \InvalidArgumentException('SQLite WordPress schema import savepoint plan requires at least one batch');
        }

        $schemaVersion = self::nonNegativeInt($options['schema_version'] ?? 0, 'schema_version');
        $dataVersion = self::nonNegativeInt($options['data_version'] ?? 1, 'data_version');
        $nextRootPage = max(2, self::nonNegativeInt($options['next_rootpage'] ?? 2, 'next_rootpage'));
        $pageSize = self::pageSize($options['page_size'] ?? 4096);
        $honorIfNotExists = (bool) ($options['honor_if_not_exists'] ?? true);

        $savepoints = new SQLiteSavepointStack();
        $savepoints->beginTransaction('wp_schema_import');

        $visibleObjects = self::normalizeExisting($existingObjects);
        $releasedObjects = $visibleObjects;
        $batchPlans = [];
        $releasedNames = [];
        $rolledBackNames = [];
        $openNames = [];
        $dirtyPages = [];
        $appliedTotal = 0;
        $skippedTotal = 0;
        $warningTotal = 0;

        foreach (array_values($batches) as $batchIndex => $batch) {
            $name = self::batchName($batch, $batchIndex);
            $onError = strtolower((string) ($batch['on_error'] ?? 'rollback'));
            if (!in_array($onError, ['rollback', 'abort'], true)) {
                throw new \InvalidArgumentException('SQLite WordPress schema import savepoint on_error must be rollback or abort');
            }

            $savepoints->savepoint($name);
            $beforeObjects = $visibleObjects;
            $beforeSchemaVersion = $schemaVersion;
            $beforeDataVersion = $dataVersion;
            $beforeRootPage = $nextRootPage;

            try {
                $schemaPlan = SQLiteWordPressSchemaBulkImportPlan::plan(
                    (string) $batch['dump'],
                    self::existingForBulkImport($visibleObjects),
                    [
                        'schema_version' => $schemaVersion,
                        'data_version' => $dataVersion,
                        'next_rootpage' => $nextRootPage,
                        'honor_if_not_exists' => $honorIfNotExists,
                    ]
                );
            } catch (\InvalidArgumentException $exception) {
                if ($onError === 'abort') {
                    throw $exception;
                }

                $rollback = $savepoints->rollbackToWithPlan($name);
                $rolledBackNames[] = $name;
                $visibleObjects = $beforeObjects;
                $schemaVersion = $beforeSchemaVersion;
                $dataVersion = $beforeDataVersion;
                $nextRootPage = $beforeRootPage;
                $batchPlans[] = [
                    'name' => $name,
                    'status' => 'rolled_back',
                    'error' => $exception->getMessage(),
                    'applied_count' => 0,
                    'skipped_count' => 0,
                    'warning_count' => 0,
                    'schema_version_before' => $beforeSchemaVersion,
                    'schema_version_after' => $schemaVersion,
                    'data_version_before' => $beforeDataVersion,
                    'data_version_after' => $dataVersion,
                    'next_rootpage_before' => $beforeRootPage,
                    'next_rootpage_after' => $nextRootPage,
                    'ordered_names' => [],
                    'dirty_pages' => [],
                    'rollback_page_numbers' => $rollback['rollback_page_numbers'],
                    'retained_depth' => $rollback['retained_depth'],
                    'released' => false,
                ];
                continue;
            }

            $appliedObjects = $schemaPlan['objects'];
            foreach ($appliedObjects as $object) {
                $visibleObjects[strtolower((string) $object['name'])] = [
                    'name' => (string) $object['name'],
                    'type' => (string) $object['type'],
                    'sql' => (string) $object['sql'],
                    'rootpage' => (int) $object['rootpage'],
                ];
                if ((int) $object['rootpage'] > 0) {
                    $dirtyPages[(int) $object['rootpage']] = true;
                    $savepoints->recordPageImageWrite((int) $object['rootpage'], self::pageImage($name, (int) $object['rootpage'], $pageSize));
                    $nextRootPage = max($nextRootPage, (int) $object['rootpage'] + 1);
                }
            }

            if ($schemaPlan['applied_count'] > 0) {
                $dirtyPages[1] = true;
                $savepoints->recordPageImageWrite(1, self::pageImage($name, 1, $pageSize));
            }

            $schemaVersion = (int) $schemaPlan['schema_version_after'];
            $dataVersion = (int) $schemaPlan['data_version_after'];
            $appliedTotal += (int) $schemaPlan['applied_count'];
            $skippedTotal += (int) $schemaPlan['skipped_count'];
            $warningTotal += count($schemaPlan['warnings']);

            $shouldRelease = (bool) ($batch['release'] ?? true);
            if ($shouldRelease) {
                $savepoints->release($name);
                $releasedObjects = $visibleObjects;
                $releasedNames[] = $name;
            } else {
                $openNames[] = $name;
            }

            $batchDirtyPages = [];
            if ($schemaPlan['applied_count'] > 0) {
                $batchDirtyPages[] = 1;
            }
            foreach ($appliedObjects as $object) {
                if ((int) $object['rootpage'] > 0) {
                    $batchDirtyPages[] = (int) $object['rootpage'];
                }
            }

            $batchPlans[] = [
                'name' => $name,
                'status' => $shouldRelease ? 'released' : 'open',
                'error' => null,
                'applied_count' => (int) $schemaPlan['applied_count'],
                'skipped_count' => (int) $schemaPlan['skipped_count'],
                'warning_count' => count($schemaPlan['warnings']),
                'schema_version_before' => $beforeSchemaVersion,
                'schema_version_after' => $schemaVersion,
                'data_version_before' => $beforeDataVersion,
                'data_version_after' => $dataVersion,
                'next_rootpage_before' => $beforeRootPage,
                'next_rootpage_after' => $nextRootPage,
                'ordered_names' => $schemaPlan['ordered_names'],
                'dirty_pages' => array_values(array_unique($batchDirtyPages)),
                'rollback_page_numbers' => [],
                'retained_depth' => null,
                'released' => $shouldRelease,
            ];
        }

        ksort($dirtyPages);

        return [
            'status' => 'planned',
            'batch_count' => count($batchPlans),
            'applied_count' => $appliedTotal,
            'skipped_count' => $skippedTotal,
            'warning_count' => $warningTotal,
            'released_batches' => $releasedNames,
            'rolled_back_batches' => $rolledBackNames,
            'open_batches' => $openNames,
            'schema_version_after' => $schemaVersion,
            'data_version_after' => $dataVersion,
            'next_rootpage_after' => $nextRootPage,
            'visible_names' => self::objectNames($visibleObjects),
            'released_names' => self::objectNames($releasedObjects),
            'dirty_pages' => array_map('intval', array_keys($dirtyPages)),
            'journal_bytes' => $dirtyPages === [] ? 0 : 28 + (count($dirtyPages) * ($pageSize + 8)),
            'batches' => $batchPlans,
            'savepoint_state' => $savepoints->toArray(),
            'dependencies' => [
                'sqlite-wordpress-schema-import-savepoint-current',
                'sqlite-schema-bulk-import',
                'sqlite-savepoint-current-rollback',
                'sqlite-schema-cookie-update',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $batch
     */
    private static function batchName(array $batch, int $index): string
    {
        if (!isset($batch['dump']) || !is_string($batch['dump'])) {
            throw new \InvalidArgumentException('SQLite WordPress schema import savepoint batch dump must be SQL text');
        }

        $name = (string) ($batch['name'] ?? 'wp_schema_' . ($index + 1));
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('SQLite WordPress schema import savepoint names must be SQL identifiers');
        }

        return $name;
    }

    /**
     * @param array<string, array{name:string,type:string,sql:string,rootpage:int}> $objects
     * @return array<string, array{sql:string,type:string}>
     */
    private static function existingForBulkImport(array $objects): array
    {
        $existing = [];
        foreach ($objects as $object) {
            $existing[$object['name']] = [
                'sql' => $object['sql'],
                'type' => $object['type'],
            ];
        }

        return $existing;
    }

    /**
     * @param array<string, array{sql?:string,type?:string}> $objects
     * @return array<string, array{name:string,type:string,sql:string,rootpage:int}>
     */
    private static function normalizeExisting(array $objects): array
    {
        $normalized = [];
        foreach ($objects as $name => $object) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $normalized[strtolower($name)] = [
                'name' => $name,
                'type' => (string) ($object['type'] ?? 'table'),
                'sql' => (string) ($object['sql'] ?? ''),
                'rootpage' => 0,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, array{name:string,type:string,sql:string,rootpage:int}> $objects
     * @return list<string>
     */
    private static function objectNames(array $objects): array
    {
        $names = array_map(static fn (array $object): string => $object['name'], array_values($objects));
        sort($names, SORT_STRING);

        return $names;
    }

    private static function pageSize(mixed $value): int
    {
        $pageSize = self::nonNegativeInt($value, 'page_size');
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite WordPress schema import savepoint page size must be a power of two at least 512');
        }

        return $pageSize;
    }

    private static function nonNegativeInt(mixed $value, string $name): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("{$name} must be a non-negative integer");
        }
        if ((int) $value < 0) {
            throw new \InvalidArgumentException("{$name} must be a non-negative integer");
        }

        return (int) $value;
    }

    private static function pageImage(string $savepoint, int $pageNumber, int $pageSize): string
    {
        return str_pad($savepoint . ':schema-before:' . $pageNumber, $pageSize, '.', STR_PAD_RIGHT);
    }
}
