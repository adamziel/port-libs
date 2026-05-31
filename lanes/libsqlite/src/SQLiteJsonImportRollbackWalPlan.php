<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonImportRollbackWalPlan
{
    /**
     * @param list<array{setting_id:int,key_name:string,key_value:mixed,load_policy?:string,page_number?:int,tenant_id?:int}> $currentRows
     * @param list<array{key_name:string,function?:string,path:string,value:mixed,page_number?:int,wal_frame_index?:int,statement?:string,on_missing?:string,insert_setting_id?:int,insert_load_policy?:string,initial_value?:mixed,tenant_id?:int}> $mutations
     * @param array{database_bytes:string,page_size?:int,wal_bytes?:string,rollback_on_error?:bool,savepoint?:string,transaction?:string,pre_savepoint_wal_pages?:list<int>,materialize_success_wal_frames?:bool} $options
     * @return array<string,mixed>
     */
    public static function plan(array $currentRows, array $mutations, array $options): array
    {
        $pageSize = (int) ($options['page_size'] ?? 512);
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback page size must be a power of two at least 512');
        }

        $databaseBytes = $options['database_bytes'] ?? null;
        if (!is_string($databaseBytes) || $databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback requires a page-aligned database image');
        }

        $usingDefaultWal = !array_key_exists('wal_bytes', $options) || $options['wal_bytes'] === null;
        $walBytes = $options['wal_bytes'] ?? self::emptyWalBytes($pageSize);
        if ($walBytes === null) {
            $walBytes = self::emptyWalBytes($pageSize);
        }
        if (!is_string($walBytes)) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback WAL bytes must be a string');
        }
        $walState = self::walState($walBytes, $pageSize);

        $importPlan = SQLiteJsonImportSavepointPlan::plan(
            $currentRows,
            $mutations,
            [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'savepoint' => $options['savepoint'] ?? 'current_json_batch',
                'transaction' => $options['transaction'] ?? 'application_json_import',
                'pre_savepoint_wal_pages' => $options['pre_savepoint_wal_pages'] ?? [],
            ]
        );

        $rollbackRequired = (bool) ($options['rollback_on_error'] ?? true) && $importPlan['failed'] !== [];
        $rollbackToFrame = (int) $importPlan['wal_rollback_to_savepoint']['rollback_to_frame'];
        if ($rollbackToFrame > $walState['frame_count']) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback frame is beyond the WAL byte stream');
        }
        if ($rollbackRequired && !$usingDefaultWal) {
            self::assertRollbackFramesExist($importPlan, $walState['frame_count']);
        }

        $truncateToBytes = 32 + ($rollbackToFrame * (24 + $pageSize));
        $materializedWalFrameCount = 0;
        $rolledBackWalBytes = $rollbackRequired ? substr($walBytes, 0, $truncateToBytes) : $walBytes;
        if (!$rollbackRequired && (bool) ($options['materialize_success_wal_frames'] ?? false)) {
            $appendResult = self::appendSuccessfulWalFrames(
                $rolledBackWalBytes,
                (string) $importPlan['database_bytes'],
                $importPlan['applied'],
                $pageSize
            );
            $rolledBackWalBytes = $appendResult['wal_bytes'];
            $materializedWalFrameCount = $appendResult['appended_frame_count'];
        }
        $rolledBackDatabaseBytes = $rollbackRequired ? $databaseBytes : (string) $importPlan['database_bytes'];
        $failedStatements = array_map(
            static fn (array $failure): string => (string) $failure['statement'],
            $importPlan['failed']
        );

        return [
            'status' => $rollbackRequired ? 'rolled_back_current_json_batch' : $importPlan['status'],
            'rollback_required' => $rollbackRequired,
            'transaction' => $importPlan['transaction'],
            'savepoint' => $importPlan['savepoint'],
            'page_size' => $pageSize,
            'failed_statements' => $failedStatements,
            'applied_statement_count' => count($importPlan['applied']),
            'failed_statement_count' => count($importPlan['failed']),
            'restored_database_bytes' => $rolledBackDatabaseBytes,
            'database_bytes_before' => $databaseBytes,
            'database_bytes_after_import' => $importPlan['database_bytes'],
            'database_restored_to_before' => $rolledBackDatabaseBytes === $databaseBytes,
            'database_changed_before_rollback' => $importPlan['database_bytes'] !== $databaseBytes,
            'wal_bytes_before' => $walBytes,
            'wal_bytes_after' => $rolledBackWalBytes,
            'wal_frame_count_before' => $walState['frame_count'],
            'wal_frame_count_after' => self::walState($rolledBackWalBytes, $pageSize)['frame_count'],
            'wal_truncate_to_bytes' => $truncateToBytes,
            'wal_truncated' => $rollbackRequired && strlen($rolledBackWalBytes) < strlen($walBytes),
            'materialized_wal_frame_count' => $materializedWalFrameCount,
            'discarded_wal_frame_count' => $rollbackRequired ? $walState['frame_count'] - $rollbackToFrame : 0,
            'rollback_to_savepoint' => $importPlan['rollback_to_savepoint'],
            'wal_rollback_to_savepoint' => $importPlan['wal_rollback_to_savepoint'],
            'import_plan' => $importPlan,
            'dependencies' => [
                'sqlite-application-json-import-savepoint-current',
                'sqlite-savepoint-wal-rollback-current',
                'sqlite-wal-current-batch-byte-truncation',
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicParityScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL rollback dynamic parity requires at least one scenario');
        }

        $scenarios = [];
        for ($seed = 1; $seed <= $scenarioCount; $seed++) {
            $pageSize = $seed % 2 === 0 ? 1024 : 512;
            $tenantId = 100 + $seed;
            $featurePage = 2 + ($seed % 4);
            $catalogPage = 10 + $seed;
            $brokenPage = 30 + $seed;
            $walFramesBefore = 5 + ($seed % 5);
            $jsonbMode = $seed % 3 === 0;
            $rollbackFrame = 0;

            $rows = [
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 10 + 1,
                    'key_name' => 'feature_flags_' . $seed,
                    'key_value' => json_encode(['enabled' => false, 'rollout' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => $seed % 2 === 0 ? 'yes' : 'no',
                    'page_number' => $featurePage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 10 + 2,
                    'key_name' => 'catalog_payload_' . $seed,
                    'key_value' => $jsonbMode
                        ? new SQLiteBlobValue(SQLiteJsonB::encode(['items' => ['base'], 'version' => $seed]))
                        : json_encode(['items' => ['base'], 'version' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'no',
                    'page_number' => $catalogPage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 10 + 3,
                    'key_name' => 'broken_payload_' . $seed,
                    'key_value' => '{"broken":',
                    'load_policy' => 'no',
                    'page_number' => $brokenPage,
                ],
            ];

            $mutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'enable_feature_' . $seed,
                    'key_name' => 'feature_flags_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'append_catalog_' . $seed,
                    'key_name' => 'catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.items',
                    'value' => new SQLiteJsonSubtypeValue(json_encode(['base', 'dynamic-' . $seed], JSON_THROW_ON_ERROR)),
                    'wal_frame_index' => 2,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'broken_payload_' . $seed,
                    'key_name' => 'broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => 3,
                ],
            ];

            $databaseBytes = self::scenarioDatabaseBytes($pageSize, max($brokenPage, $catalogPage, $featurePage));
            $walBytes = self::scenarioWalBytes($pageSize, $walFramesBefore, 0x7000 + $seed, 0x7100 + $seed);
            $plan = self::plan($rows, $mutations, [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'wal_bytes' => $walBytes,
                'transaction' => 'application_dynamic_json_import_' . $seed,
                'savepoint' => 'dynamic_json_batch_' . $seed,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'wal_frames_before' => $walFramesBefore,
                'rollback_frame' => $rollbackFrame,
                'expected_truncate_bytes' => 32 + ($rollbackFrame * (24 + $pageSize)),
                'expected_restored_pages' => [$featurePage, $catalogPage],
                'expected_failed_statement' => 'broken_payload_' . $seed,
                'database_bytes' => $databaseBytes,
                'wal_bytes' => $walBytes,
                'plan' => $plan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicPreexistingWalScenarios(int $scenarioCount = 20): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL preexisting rollback dynamic parity requires at least one scenario');
        }

        $scenarios = [];
        for ($seed = 1; $seed <= $scenarioCount; $seed++) {
            $pageSize = $seed % 2 === 0 ? 1024 : 512;
            $tenantId = 1200 + $seed;
            $preexistingFrames = 2 + ($seed % 4);
            $batchFrames = 3;
            $featurePage = 8 + ($seed % 6);
            $catalogPage = 220 + $seed;
            $brokenPage = 260 + $seed;
            $jsonbMode = $seed % 2 === 1;
            $preSavepointWalPages = [];
            for ($frame = 1; $frame <= $preexistingFrames; $frame++) {
                $preSavepointWalPages[] = 300 + $seed + $frame;
            }

            $rows = [
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 2000 + 1,
                    'key_name' => 'prefix_feature_flags_' . $seed,
                    'key_value' => json_encode(['enabled' => false, 'prefix' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'yes',
                    'page_number' => $featurePage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 2000 + 2,
                    'key_name' => 'prefix_catalog_payload_' . $seed,
                    'key_value' => $jsonbMode
                        ? new SQLiteBlobValue(SQLiteJsonB::encode(['items' => ['prefix'], 'version' => $seed]))
                        : json_encode(['items' => ['prefix'], 'version' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'no',
                    'page_number' => $catalogPage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 2000 + 3,
                    'key_name' => 'prefix_broken_payload_' . $seed,
                    'key_value' => '{"broken":',
                    'load_policy' => 'no',
                    'page_number' => $brokenPage,
                ],
            ];

            $mutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'prefix_enable_feature_' . $seed,
                    'key_name' => 'prefix_feature_flags_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => $preexistingFrames + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'prefix_append_catalog_' . $seed,
                    'key_name' => 'prefix_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.items',
                    'value' => new SQLiteJsonSubtypeValue(json_encode(['prefix', 'discarded-' . $seed], JSON_THROW_ON_ERROR)),
                    'wal_frame_index' => $preexistingFrames + 2,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'prefix_broken_payload_' . $seed,
                    'key_name' => 'prefix_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => $preexistingFrames + 3,
                ],
            ];

            $databaseBytes = self::scenarioDatabaseBytes($pageSize, max($brokenPage, $catalogPage, $featurePage));
            $walBytes = self::scenarioWalBytes($pageSize, $preexistingFrames + $batchFrames, 0x7c00 + $seed, 0x7d00 + $seed);
            $plan = self::plan($rows, $mutations, [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'wal_bytes' => $walBytes,
                'transaction' => 'application_prefix_json_import_' . $seed,
                'savepoint' => 'prefix_json_batch_' . $seed,
                'pre_savepoint_wal_pages' => $preSavepointWalPages,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'preexisting_frames' => $preexistingFrames,
                'batch_frames' => $batchFrames,
                'wal_frames_before' => $preexistingFrames + $batchFrames,
                'pre_savepoint_wal_pages' => $preSavepointWalPages,
                'expected_truncate_bytes' => 32 + ($preexistingFrames * (24 + $pageSize)),
                'expected_restored_pages' => [$featurePage, $catalogPage],
                'expected_failed_statement' => 'prefix_broken_payload_' . $seed,
                'database_bytes' => $databaseBytes,
                'wal_bytes' => $walBytes,
                'input_rows' => $rows,
                'input_mutations' => $mutations,
                'plan' => $plan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicTenantCollisionScenarios(int $scenarioCount = 18): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL tenant-collision dynamic parity requires at least one scenario');
        }

        $scenarios = [];
        for ($seed = 1; $seed <= $scenarioCount; $seed++) {
            $pageSize = $seed % 2 === 0 ? 1024 : 512;
            $targetTenantId = 2100 + $seed;
            $stableTenantId = 3100 + $seed;
            $sharedKey = 'tenant_shared_payload_' . $seed;
            $targetPage = 18 + ($seed % 5);
            $stablePage = 80 + $seed;
            $brokenPage = 140 + $seed;
            $walFramesBefore = 5 + ($seed % 4);
            $jsonbMode = $seed % 3 === 1;

            $rows = [
                [
                    'tenant_id' => $targetTenantId,
                    'setting_id' => $seed * 4000 + 1,
                    'key_name' => $sharedKey,
                    'key_value' => $jsonbMode
                        ? new SQLiteBlobValue(SQLiteJsonB::encode(['enabled' => false, 'tenant' => $targetTenantId]))
                        : json_encode(['enabled' => false, 'tenant' => $targetTenantId], JSON_THROW_ON_ERROR),
                    'load_policy' => 'yes',
                    'page_number' => $targetPage,
                ],
                [
                    'tenant_id' => $stableTenantId,
                    'setting_id' => $seed * 4000 + 2,
                    'key_name' => $sharedKey,
                    'key_value' => json_encode(['enabled' => false, 'tenant' => $stableTenantId, 'stable' => true], JSON_THROW_ON_ERROR),
                    'load_policy' => 'yes',
                    'page_number' => $stablePage,
                ],
                [
                    'tenant_id' => $targetTenantId,
                    'setting_id' => $seed * 4000 + 3,
                    'key_name' => 'tenant_broken_payload_' . $seed,
                    'key_value' => '{"broken":',
                    'load_policy' => 'no',
                    'page_number' => $brokenPage,
                ],
            ];

            $mutations = [
                [
                    'tenant_id' => $targetTenantId,
                    'statement' => 'tenant_enable_shared_' . $seed,
                    'key_name' => $sharedKey,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => 1,
                ],
                [
                    'tenant_id' => $targetTenantId,
                    'statement' => 'tenant_broken_payload_' . $seed,
                    'key_name' => 'tenant_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => 2,
                ],
            ];

            $databaseBytes = self::scenarioDatabaseBytes($pageSize, max($brokenPage, $stablePage, $targetPage));
            $walBytes = self::scenarioWalBytes($pageSize, $walFramesBefore, 0x8200 + $seed, 0x8300 + $seed);
            $plan = self::plan($rows, $mutations, [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'wal_bytes' => $walBytes,
                'transaction' => 'application_tenant_collision_json_import_' . $seed,
                'savepoint' => 'tenant_collision_json_batch_' . $seed,
            ]);

            $stableRows = array_values(array_filter(
                $plan['import_plan']['final_rows'],
                static fn (array $row): bool => $row['tenant_id'] === $stableTenantId
            ));

            $scenarios[] = [
                'seed' => $seed,
                'target_tenant_id' => $targetTenantId,
                'stable_tenant_id' => $stableTenantId,
                'page_size' => $pageSize,
                'shared_key' => $sharedKey,
                'jsonb_mode' => $jsonbMode,
                'wal_frames_before' => $walFramesBefore,
                'expected_restored_pages' => [$targetPage],
                'stable_page' => $stablePage,
                'expected_failed_statement' => 'tenant_broken_payload_' . $seed,
                'database_bytes' => $databaseBytes,
                'wal_bytes' => $walBytes,
                'stable_row_after_import' => $stableRows[0] ?? null,
                'plan' => $plan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicInsertedSettingRollbackScenarios(int $scenarioCount = 18): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL inserted-setting dynamic parity requires at least one scenario');
        }

        $scenarios = [];
        for ($seed = 1; $seed <= $scenarioCount; $seed++) {
            $pageSize = $seed % 2 === 0 ? 1024 : 512;
            $tenantId = 4100 + $seed;
            $basePage = 24 + ($seed % 6);
            $insertPage = 180 + $seed;
            $auditInsertPage = 230 + $seed;
            $brokenPage = 280 + $seed;
            $walFramesBefore = 4 + ($seed % 5);
            $jsonbMode = $seed % 2 === 0;

            $rows = [
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 5000 + 1,
                    'key_name' => 'insert_base_payload_' . $seed,
                    'key_value' => json_encode(['enabled' => false, 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'yes',
                    'page_number' => $basePage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 5000 + 2,
                    'key_name' => 'insert_broken_payload_' . $seed,
                    'key_value' => '{"broken":',
                    'load_policy' => 'no',
                    'page_number' => $brokenPage,
                ],
            ];

            $mutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'insert_enable_base_' . $seed,
                    'key_name' => 'insert_base_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'insert_new_payload_' . $seed,
                    'key_name' => 'insert_new_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.source',
                    'value' => 'batch-' . $seed,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 5000 + 3,
                    'insert_load_policy' => 'no',
                    'initial_value' => $jsonbMode ? new SQLiteBlobValue(SQLiteJsonB::encode(['source' => 'initial'])) : '{}',
                    'page_number' => $insertPage,
                    'wal_frame_index' => 2,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'insert_audit_payload_' . $seed,
                    'key_name' => 'insert_audit_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.audit',
                    'value' => true,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 5000 + 4,
                    'insert_load_policy' => 'yes',
                    'initial_value' => '{}',
                    'page_number' => $auditInsertPage,
                    'wal_frame_index' => 3,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'insert_broken_payload_' . $seed,
                    'key_name' => 'insert_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => 4,
                ],
            ];

            $databaseBytes = self::scenarioDatabaseBytes($pageSize, max($brokenPage, $auditInsertPage, $insertPage, $basePage));
            $walBytes = self::scenarioWalBytes($pageSize, $walFramesBefore, 0x8400 + $seed, 0x8500 + $seed);
            $plan = self::plan($rows, $mutations, [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'wal_bytes' => $walBytes,
                'transaction' => 'application_inserted_setting_json_import_' . $seed,
                'savepoint' => 'inserted_setting_json_batch_' . $seed,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'wal_frames_before' => $walFramesBefore,
                'inserted_setting_ids' => [$seed * 5000 + 3, $seed * 5000 + 4],
                'inserted_key_names' => ['insert_new_payload_' . $seed, 'insert_audit_payload_' . $seed],
                'expected_restored_pages' => [$basePage, $insertPage, $auditInsertPage],
                'expected_failed_statement' => 'insert_broken_payload_' . $seed,
                'database_bytes' => $databaseBytes,
                'wal_bytes' => $walBytes,
                'plan' => $plan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicDuplicateInsertedSettingRollbackScenarios(int $scenarioCount = 18): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL duplicate inserted-setting dynamic parity requires at least one scenario');
        }

        $scenarios = [];
        for ($seed = 1; $seed <= $scenarioCount; $seed++) {
            $pageSize = $seed % 2 === 0 ? 1024 : 512;
            $tenantId = 5100 + $seed;
            $basePage = 34 + ($seed % 7);
            $existingInsertIdPage = 310 + $seed;
            $duplicateInsertPage = 390 + $seed;
            $walFramesBefore = 3 + ($seed % 6);
            $duplicateSettingId = $seed * 6000 + 2;

            $rows = [
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 6000 + 1,
                    'key_name' => 'duplicate_insert_base_payload_' . $seed,
                    'key_value' => json_encode(['enabled' => false, 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'yes',
                    'page_number' => $basePage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $duplicateSettingId,
                    'key_name' => 'duplicate_insert_existing_payload_' . $seed,
                    'key_value' => json_encode(['reserved' => true, 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'no',
                    'page_number' => $existingInsertIdPage,
                ],
            ];

            $mutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'duplicate_insert_enable_base_' . $seed,
                    'key_name' => 'duplicate_insert_base_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'duplicate_insert_conflict_' . $seed,
                    'key_name' => 'duplicate_insert_new_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.source',
                    'value' => 'discarded-' . $seed,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $duplicateSettingId,
                    'insert_load_policy' => 'no',
                    'initial_value' => '{}',
                    'page_number' => $duplicateInsertPage,
                    'wal_frame_index' => 2,
                ],
            ];

            $databaseBytes = self::scenarioDatabaseBytes($pageSize, max($duplicateInsertPage, $existingInsertIdPage, $basePage));
            $walBytes = self::scenarioWalBytes($pageSize, $walFramesBefore, 0x8600 + $seed, 0x8700 + $seed);
            $plan = self::plan($rows, $mutations, [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'wal_bytes' => $walBytes,
                'transaction' => 'application_duplicate_insert_json_import_' . $seed,
                'savepoint' => 'duplicate_insert_json_batch_' . $seed,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'duplicate_setting_id' => $duplicateSettingId,
                'existing_insert_id_page' => $existingInsertIdPage,
                'duplicate_insert_page' => $duplicateInsertPage,
                'wal_frames_before' => $walFramesBefore,
                'expected_restored_pages' => [$basePage],
                'expected_failed_statement' => 'duplicate_insert_conflict_' . $seed,
                'expected_error' => 'SQLite Application JSON import inserted setting_id already exists: ' . $duplicateSettingId,
                'database_bytes' => $databaseBytes,
                'wal_bytes' => $walBytes,
                'plan' => $plan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicMalformedInsertedInitialValueScenarios(int $scenarioCount = 18): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL malformed inserted-initial-value dynamic parity requires at least one scenario');
        }

        $scenarios = [];
        for ($seed = 1; $seed <= $scenarioCount; $seed++) {
            $pageSize = $seed % 2 === 0 ? 1024 : 512;
            $tenantId = 6100 + $seed;
            $basePage = 44 + ($seed % 7);
            $insertPage = 430 + $seed;
            $walFramesBefore = 4 + ($seed % 5);
            $insertSettingId = $seed * 7000 + 2;
            $jsonbMode = $seed % 2 === 1;

            $rows = [
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 7000 + 1,
                    'key_name' => 'malformed_insert_base_payload_' . $seed,
                    'key_value' => json_encode(['enabled' => false, 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'yes',
                    'page_number' => $basePage,
                ],
            ];

            $mutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'malformed_insert_enable_base_' . $seed,
                    'key_name' => 'malformed_insert_base_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'malformed_insert_initial_value_' . $seed,
                    'key_name' => 'malformed_insert_new_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.source',
                    'value' => 'discarded-' . $seed,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $insertSettingId,
                    'insert_load_policy' => 'no',
                    'initial_value' => '{"broken":',
                    'page_number' => $insertPage,
                    'wal_frame_index' => 2,
                ],
            ];

            $databaseBytes = self::scenarioDatabaseBytes($pageSize, max($insertPage, $basePage));
            $walBytes = self::scenarioWalBytes($pageSize, $walFramesBefore, 0x8800 + $seed, 0x8900 + $seed);
            $plan = self::plan($rows, $mutations, [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'wal_bytes' => $walBytes,
                'transaction' => 'application_malformed_insert_json_import_' . $seed,
                'savepoint' => 'malformed_insert_json_batch_' . $seed,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'insert_setting_id' => $insertSettingId,
                'insert_page' => $insertPage,
                'wal_frames_before' => $walFramesBefore,
                'expected_restored_pages' => [$basePage],
                'expected_failed_statement' => 'malformed_insert_initial_value_' . $seed,
                'expected_error' => 'SQLite JSON5 input ended before a value',
                'database_bytes' => $databaseBytes,
                'wal_bytes' => $walBytes,
                'plan' => $plan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicDeferredFailureScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL deferred failure dynamic parity requires at least one scenario');
        }

        $scenarios = [];
        for ($seed = 1; $seed <= $scenarioCount; $seed++) {
            $pageSize = $seed % 2 === 0 ? 1024 : 512;
            $tenantId = 700 + $seed;
            $featurePage = 4 + ($seed % 3);
            $catalogPage = 40 + $seed;
            $brokenPage = 80 + $seed;
            $walFramesBefore = 4 + ($seed % 6);
            $jsonbMode = $seed % 4 === 0;

            $rows = [
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 100 + 1,
                    'key_name' => 'deferred_feature_flags_' . $seed,
                    'key_value' => json_encode(['enabled' => false, 'version' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'yes',
                    'page_number' => $featurePage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 100 + 2,
                    'key_name' => 'deferred_catalog_payload_' . $seed,
                    'key_value' => $jsonbMode
                        ? new SQLiteBlobValue(SQLiteJsonB::encode(['items' => ['current'], 'version' => $seed]))
                        : json_encode(['items' => ['current'], 'version' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'no',
                    'page_number' => $catalogPage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 100 + 3,
                    'key_name' => 'deferred_broken_payload_' . $seed,
                    'key_value' => '{"broken":',
                    'load_policy' => 'no',
                    'page_number' => $brokenPage,
                ],
            ];

            $mutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'deferred_enable_feature_' . $seed,
                    'key_name' => 'deferred_feature_flags_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'deferred_append_catalog_' . $seed,
                    'key_name' => 'deferred_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.items',
                    'value' => new SQLiteJsonSubtypeValue(json_encode(['current', 'kept-' . $seed], JSON_THROW_ON_ERROR)),
                    'wal_frame_index' => 2,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'deferred_broken_payload_' . $seed,
                    'key_name' => 'deferred_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => 3,
                ],
            ];

            $databaseBytes = self::scenarioDatabaseBytes($pageSize, max($brokenPage, $catalogPage, $featurePage));
            $walBytes = self::scenarioWalBytes($pageSize, $walFramesBefore, 0x7800 + $seed, 0x7900 + $seed);
            $plan = self::plan($rows, $mutations, [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'wal_bytes' => $walBytes,
                'transaction' => 'application_deferred_json_import_' . $seed,
                'savepoint' => 'deferred_json_batch_' . $seed,
                'rollback_on_error' => false,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'wal_frames_before' => $walFramesBefore,
                'expected_restored_pages' => [$featurePage, $catalogPage],
                'expected_failed_statement' => 'deferred_broken_payload_' . $seed,
                'database_bytes' => $databaseBytes,
                'wal_bytes' => $walBytes,
                'plan' => $plan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicRetryAfterRollbackScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL rollback retry dynamic parity requires at least one scenario');
        }

        $scenarios = [];
        for ($seed = 1; $seed <= $scenarioCount; $seed++) {
            $pageSize = $seed % 2 === 0 ? 1024 : 512;
            $tenantId = 900 + $seed;
            $featurePage = 6 + ($seed % 5);
            $catalogPage = 120 + $seed;
            $brokenPage = 170 + $seed;
            $walFramesBefore = 6 + ($seed % 4);
            $jsonbMode = $seed % 2 === 0;

            $rows = [
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 1000 + 1,
                    'key_name' => 'retry_feature_flags_' . $seed,
                    'key_value' => json_encode(['enabled' => false, 'retry' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'yes',
                    'page_number' => $featurePage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 1000 + 2,
                    'key_name' => 'retry_catalog_payload_' . $seed,
                    'key_value' => $jsonbMode
                        ? new SQLiteBlobValue(SQLiteJsonB::encode(['items' => ['before'], 'version' => $seed]))
                        : json_encode(['items' => ['before'], 'version' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'no',
                    'page_number' => $catalogPage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 1000 + 3,
                    'key_name' => 'retry_broken_payload_' . $seed,
                    'key_value' => '{"broken":',
                    'load_policy' => 'no',
                    'page_number' => $brokenPage,
                ],
            ];

            $failedMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'retry_enable_feature_failed_batch_' . $seed,
                    'key_name' => 'retry_feature_flags_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'retry_append_catalog_failed_batch_' . $seed,
                    'key_name' => 'retry_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.items',
                    'value' => new SQLiteJsonSubtypeValue(json_encode(['before', 'discarded-' . $seed], JSON_THROW_ON_ERROR)),
                    'wal_frame_index' => 2,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'retry_broken_payload_failed_batch_' . $seed,
                    'key_name' => 'retry_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => 3,
                ],
            ];

            $databaseBytes = self::scenarioDatabaseBytes($pageSize, max($brokenPage, $catalogPage, $featurePage));
            $walBytes = self::scenarioWalBytes($pageSize, $walFramesBefore, 0x7a00 + $seed, 0x7b00 + $seed);
            $failedPlan = self::plan($rows, $failedMutations, [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'wal_bytes' => $walBytes,
                'transaction' => 'application_retry_json_import_failed_' . $seed,
                'savepoint' => 'retry_json_batch_failed_' . $seed,
            ]);

            $retryRows = $rows;
            $retryRows[2]['key_value'] = json_encode(['fixed' => false, 'retry' => $seed], JSON_THROW_ON_ERROR);
            $retryMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'retry_enable_feature_' . $seed,
                    'key_name' => 'retry_feature_flags_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'retry_append_catalog_' . $seed,
                    'key_name' => 'retry_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.items',
                    'value' => new SQLiteJsonSubtypeValue(json_encode(['before', 'kept-' . $seed], JSON_THROW_ON_ERROR)),
                    'wal_frame_index' => 2,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'retry_mark_fixed_payload_' . $seed,
                    'key_name' => 'retry_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.fixed',
                    'value' => true,
                    'wal_frame_index' => 3,
                ],
            ];

            $retryPlan = self::plan($retryRows, $retryMutations, [
                'database_bytes' => $failedPlan['restored_database_bytes'],
                'page_size' => $pageSize,
                'wal_bytes' => $failedPlan['wal_bytes_after'],
                'transaction' => 'application_retry_json_import_success_' . $seed,
                'savepoint' => 'retry_json_batch_success_' . $seed,
            ]);
            $materializedRetryPlan = self::plan($retryRows, $retryMutations, [
                'database_bytes' => $failedPlan['restored_database_bytes'],
                'page_size' => $pageSize,
                'wal_bytes' => $failedPlan['wal_bytes_after'],
                'transaction' => 'application_retry_json_import_success_materialized_' . $seed,
                'savepoint' => 'retry_json_batch_success_materialized_' . $seed,
                'materialize_success_wal_frames' => true,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'wal_frames_before' => $walFramesBefore,
                'database_bytes' => $databaseBytes,
                'wal_bytes' => $walBytes,
                'expected_retry_pages' => [$featurePage, $catalogPage, $brokenPage],
                'failed_plan' => $failedPlan,
                'retry_plan' => $retryPlan,
                'materialized_retry_plan' => $materializedRetryPlan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicPreexistingWalRetryScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL prefix retry dynamic parity requires at least one scenario');
        }

        $scenarios = [];
        for ($seed = 1; $seed <= $scenarioCount; $seed++) {
            $pageSize = $seed % 2 === 0 ? 1024 : 512;
            $tenantId = 1500 + $seed;
            $preexistingFrames = 1 + ($seed % 5);
            $featurePage = 14 + ($seed % 7);
            $catalogPage = 340 + $seed;
            $brokenPage = 390 + $seed;
            $jsonbMode = $seed % 2 === 0;
            $preSavepointWalPages = [];
            for ($frame = 1; $frame <= $preexistingFrames; $frame++) {
                $preSavepointWalPages[] = 480 + $seed + $frame;
            }

            $rows = [
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 3000 + 1,
                    'key_name' => 'prefix_retry_feature_flags_' . $seed,
                    'key_value' => json_encode(['enabled' => false, 'retry' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'yes',
                    'page_number' => $featurePage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 3000 + 2,
                    'key_name' => 'prefix_retry_catalog_payload_' . $seed,
                    'key_value' => $jsonbMode
                        ? new SQLiteBlobValue(SQLiteJsonB::encode(['items' => ['prefix'], 'version' => $seed]))
                        : json_encode(['items' => ['prefix'], 'version' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'no',
                    'page_number' => $catalogPage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 3000 + 3,
                    'key_name' => 'prefix_retry_broken_payload_' . $seed,
                    'key_value' => '{"broken":',
                    'load_policy' => 'no',
                    'page_number' => $brokenPage,
                ],
            ];

            $failedMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'prefix_retry_enable_failed_' . $seed,
                    'key_name' => 'prefix_retry_feature_flags_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => $preexistingFrames + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'prefix_retry_catalog_failed_' . $seed,
                    'key_name' => 'prefix_retry_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.items',
                    'value' => new SQLiteJsonSubtypeValue(json_encode(['prefix', 'discarded-' . $seed], JSON_THROW_ON_ERROR)),
                    'wal_frame_index' => $preexistingFrames + 2,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'prefix_retry_broken_failed_' . $seed,
                    'key_name' => 'prefix_retry_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => $preexistingFrames + 3,
                ],
            ];

            $databaseBytes = self::scenarioDatabaseBytes($pageSize, max($brokenPage, $catalogPage, $featurePage));
            $walBytes = self::scenarioWalBytes($pageSize, $preexistingFrames + 3, 0x7e00 + $seed, 0x7f00 + $seed);
            $failedPlan = self::plan($rows, $failedMutations, [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'wal_bytes' => $walBytes,
                'transaction' => 'application_prefix_retry_json_import_failed_' . $seed,
                'savepoint' => 'prefix_retry_json_batch_failed_' . $seed,
                'pre_savepoint_wal_pages' => $preSavepointWalPages,
            ]);

            $retryRows = $rows;
            $retryRows[2]['key_value'] = json_encode(['fixed' => false, 'retry' => $seed], JSON_THROW_ON_ERROR);
            $retryMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'prefix_retry_enable_success_' . $seed,
                    'key_name' => 'prefix_retry_feature_flags_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => $preexistingFrames + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'prefix_retry_catalog_success_' . $seed,
                    'key_name' => 'prefix_retry_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.items',
                    'value' => new SQLiteJsonSubtypeValue(json_encode(['prefix', 'kept-' . $seed], JSON_THROW_ON_ERROR)),
                    'wal_frame_index' => $preexistingFrames + 2,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'prefix_retry_fixed_payload_success_' . $seed,
                    'key_name' => 'prefix_retry_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.fixed',
                    'value' => true,
                    'wal_frame_index' => $preexistingFrames + 3,
                ],
            ];

            $retryPlan = self::plan($retryRows, $retryMutations, [
                'database_bytes' => $failedPlan['restored_database_bytes'],
                'page_size' => $pageSize,
                'wal_bytes' => $failedPlan['wal_bytes_after'],
                'transaction' => 'application_prefix_retry_json_import_success_' . $seed,
                'savepoint' => 'prefix_retry_json_batch_success_' . $seed,
                'pre_savepoint_wal_pages' => $preSavepointWalPages,
            ]);
            $materializedRetryPlan = self::plan($retryRows, $retryMutations, [
                'database_bytes' => $failedPlan['restored_database_bytes'],
                'page_size' => $pageSize,
                'wal_bytes' => $failedPlan['wal_bytes_after'],
                'transaction' => 'application_prefix_retry_json_import_success_materialized_' . $seed,
                'savepoint' => 'prefix_retry_json_batch_success_materialized_' . $seed,
                'pre_savepoint_wal_pages' => $preSavepointWalPages,
                'materialize_success_wal_frames' => true,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'preexisting_frames' => $preexistingFrames,
                'pre_savepoint_wal_pages' => $preSavepointWalPages,
                'expected_truncate_bytes' => 32 + ($preexistingFrames * (24 + $pageSize)),
                'database_bytes' => $databaseBytes,
                'wal_bytes' => $walBytes,
                'expected_retry_pages' => [$featurePage, $catalogPage, $brokenPage],
                'failed_plan' => $failedPlan,
                'retry_plan' => $retryPlan,
                'materialized_retry_plan' => $materializedRetryPlan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicMissingWalTailScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL missing-tail dynamic parity requires at least one scenario');
        }

        $prefixScenarios = self::dynamicPreexistingWalScenarios($scenarioCount);
        $scenarios = [];
        foreach ($prefixScenarios as $base) {
            $seed = (int) $base['seed'];
            $pageSize = (int) $base['page_size'];
            $frameSize = 24 + $pageSize;
            $missingFrames = 1 + ($seed % 2);
            $shortFrameCount = (int) $base['wal_frames_before'] - $missingFrames;
            $shortWalBytes = substr((string) $base['wal_bytes'], 0, 32 + ($shortFrameCount * $frameSize));
            $exceptionMessage = null;

            try {
                self::plan(
                    $base['input_rows'],
                    $base['input_mutations'],
                    [
                        'database_bytes' => $base['database_bytes'],
                        'page_size' => $pageSize,
                        'wal_bytes' => $shortWalBytes,
                        'transaction' => 'application_missing_wal_tail_' . $seed,
                        'savepoint' => 'missing_wal_tail_batch_' . $seed,
                        'pre_savepoint_wal_pages' => $base['pre_savepoint_wal_pages'],
                    ]
                );
            } catch (\InvalidArgumentException $exception) {
                $exceptionMessage = $exception->getMessage();
            }

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $base['tenant_id'],
                'page_size' => $pageSize,
                'preexisting_frames' => $base['preexisting_frames'],
                'expected_frame_count' => $base['wal_frames_before'],
                'short_frame_count' => $shortFrameCount,
                'missing_frames' => $missingFrames,
                'missing_frame_indexes' => range($shortFrameCount + 1, (int) $base['wal_frames_before']),
                'short_wal_bytes' => $shortWalBytes,
                'exception_message' => $exceptionMessage,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicPartialWalTailScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL partial-tail dynamic parity requires at least one scenario');
        }

        $prefixScenarios = self::dynamicPreexistingWalScenarios($scenarioCount);
        $scenarios = [];
        foreach ($prefixScenarios as $base) {
            $seed = (int) $base['seed'];
            $pageSize = (int) $base['page_size'];
            $frameSize = 24 + $pageSize;
            $completeFrames = (int) $base['preexisting_frames'] + 1;
            $partialPayloadBytes = 1 + (($seed * 37) % ($frameSize - 1));
            $partialWalBytes = substr(
                (string) $base['wal_bytes'],
                0,
                32 + ($completeFrames * $frameSize) + $partialPayloadBytes
            );
            $exceptionMessage = null;

            try {
                self::plan(
                    $base['input_rows'],
                    $base['input_mutations'],
                    [
                        'database_bytes' => $base['database_bytes'],
                        'page_size' => $pageSize,
                        'wal_bytes' => $partialWalBytes,
                        'transaction' => 'application_partial_wal_tail_' . $seed,
                        'savepoint' => 'partial_wal_tail_batch_' . $seed,
                        'pre_savepoint_wal_pages' => $base['pre_savepoint_wal_pages'],
                    ]
                );
            } catch (\InvalidArgumentException $exception) {
                $exceptionMessage = $exception->getMessage();
            }

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $base['tenant_id'],
                'page_size' => $pageSize,
                'preexisting_frames' => $base['preexisting_frames'],
                'complete_frame_count' => $completeFrames,
                'partial_payload_bytes' => $partialPayloadBytes,
                'frame_size' => $frameSize,
                'partial_wal_bytes' => $partialWalBytes,
                'exception_message' => $exceptionMessage,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicFrameHeaderMismatchScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL frame-header dynamic parity requires at least one scenario');
        }

        $prefixScenarios = self::dynamicPreexistingWalScenarios($scenarioCount);
        $scenarios = [];
        foreach ($prefixScenarios as $base) {
            $seed = (int) $base['seed'];
            $pageSize = (int) $base['page_size'];
            $frameSize = 24 + $pageSize;
            $targetFrame = (int) $base['preexisting_frames'] + 1;
            $walBytes = (string) $base['wal_bytes'];
            $corruption = $seed % 2 === 0 ? 'zero_page' : 'salt_mismatch';
            $frameOffset = 32 + (($targetFrame - 1) * $frameSize);
            $corruptWalBytes = $corruption === 'zero_page'
                ? substr_replace($walBytes, pack('N', 0), $frameOffset, 4)
                : substr_replace($walBytes, pack('N', 0x91000000 + $seed), $frameOffset + 8, 4);
            $exceptionMessage = null;

            try {
                self::plan(
                    $base['input_rows'],
                    $base['input_mutations'],
                    [
                        'database_bytes' => $base['database_bytes'],
                        'page_size' => $pageSize,
                        'wal_bytes' => $corruptWalBytes,
                        'transaction' => 'application_frame_header_wal_' . $seed,
                        'savepoint' => 'frame_header_wal_batch_' . $seed,
                        'pre_savepoint_wal_pages' => $base['pre_savepoint_wal_pages'],
                    ]
                );
            } catch (\InvalidArgumentException $exception) {
                $exceptionMessage = $exception->getMessage();
            }

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $base['tenant_id'],
                'page_size' => $pageSize,
                'preexisting_frames' => $base['preexisting_frames'],
                'target_frame' => $targetFrame,
                'corruption' => $corruption,
                'frame_offset' => $frameOffset,
                'corrupt_wal_bytes' => $corruptWalBytes,
                'exception_message' => $exceptionMessage,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicFrameChecksumMismatchScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL frame-checksum dynamic parity requires at least one scenario');
        }

        $prefixScenarios = self::dynamicPreexistingWalScenarios($scenarioCount);
        $scenarios = [];
        foreach ($prefixScenarios as $base) {
            $seed = (int) $base['seed'];
            $pageSize = (int) $base['page_size'];
            $frameSize = 24 + $pageSize;
            $targetFrame = (int) $base['preexisting_frames'] + 1;
            $walBytes = self::scenarioChecksummedWalBytes($pageSize, (int) $base['wal_frames_before'], 0x9000 + $seed, 0x9100 + $seed);
            $frameOffset = 32 + (($targetFrame - 1) * $frameSize);
            $checksumOffset = $frameOffset + 16 + (($seed % 2) * 4);
            $corruptWalBytes = substr_replace($walBytes, pack('N', 0xa5000000 + $seed), $checksumOffset, 4);
            $exceptionMessage = null;

            try {
                self::plan(
                    $base['input_rows'],
                    $base['input_mutations'],
                    [
                        'database_bytes' => $base['database_bytes'],
                        'page_size' => $pageSize,
                        'wal_bytes' => $corruptWalBytes,
                        'transaction' => 'application_frame_checksum_wal_' . $seed,
                        'savepoint' => 'frame_checksum_wal_batch_' . $seed,
                        'pre_savepoint_wal_pages' => $base['pre_savepoint_wal_pages'],
                    ]
                );
            } catch (\InvalidArgumentException $exception) {
                $exceptionMessage = $exception->getMessage();
            }

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $base['tenant_id'],
                'page_size' => $pageSize,
                'preexisting_frames' => $base['preexisting_frames'],
                'target_frame' => $targetFrame,
                'checksum_offset' => $checksumOffset,
                'corrupt_wal_bytes' => $corruptWalBytes,
                'exception_message' => $exceptionMessage,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicHeaderChecksumMismatchScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL header-checksum dynamic parity requires at least one scenario');
        }

        $prefixScenarios = self::dynamicPreexistingWalScenarios($scenarioCount);
        $scenarios = [];
        foreach ($prefixScenarios as $base) {
            $seed = (int) $base['seed'];
            $pageSize = (int) $base['page_size'];
            $walBytes = self::scenarioChecksummedWalBytes($pageSize, (int) $base['wal_frames_before'], 0x9200 + $seed, 0x9300 + $seed);
            $checksumOffset = 24 + (($seed % 2) * 4);
            $corruptWalBytes = substr_replace($walBytes, pack('N', 0xb6000000 + $seed), $checksumOffset, 4);
            $exceptionMessage = null;

            try {
                self::plan(
                    $base['input_rows'],
                    $base['input_mutations'],
                    [
                        'database_bytes' => $base['database_bytes'],
                        'page_size' => $pageSize,
                        'wal_bytes' => $corruptWalBytes,
                        'transaction' => 'application_header_checksum_wal_' . $seed,
                        'savepoint' => 'header_checksum_wal_batch_' . $seed,
                        'pre_savepoint_wal_pages' => $base['pre_savepoint_wal_pages'],
                    ]
                );
            } catch (\InvalidArgumentException $exception) {
                $exceptionMessage = $exception->getMessage();
            }

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $base['tenant_id'],
                'page_size' => $pageSize,
                'preexisting_frames' => $base['preexisting_frames'],
                'checksum_offset' => $checksumOffset,
                'wal_frames_before' => $base['wal_frames_before'],
                'corrupt_wal_bytes' => $corruptWalBytes,
                'exception_message' => $exceptionMessage,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicSuccessfulMaterializedWalScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL successful materialization dynamic parity requires at least one scenario');
        }

        $scenarios = [];
        for ($seed = 1; $seed <= $scenarioCount; $seed++) {
            $pageSize = $seed % 2 === 0 ? 1024 : 512;
            $tenantId = 6100 + $seed;
            $featurePage = 42 + ($seed % 9);
            $catalogPage = 520 + $seed;
            $auditPage = 620 + $seed;
            $preexistingFrames = $seed % 4;
            $jsonbMode = $seed % 3 === 2;
            $preSavepointWalPages = [];
            for ($frame = 1; $frame <= $preexistingFrames; $frame++) {
                $preSavepointWalPages[] = 700 + $seed + $frame;
            }

            $rows = [
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 7000 + 1,
                    'key_name' => 'success_feature_flags_' . $seed,
                    'key_value' => json_encode(['enabled' => false, 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'yes',
                    'page_number' => $featurePage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 7000 + 2,
                    'key_name' => 'success_catalog_payload_' . $seed,
                    'key_value' => $jsonbMode
                        ? new SQLiteBlobValue(SQLiteJsonB::encode(['items' => ['before'], 'seed' => $seed]))
                        : json_encode(['items' => ['before'], 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'no',
                    'page_number' => $catalogPage,
                ],
            ];

            $mutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'success_enable_feature_' . $seed,
                    'key_name' => 'success_feature_flags_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => $preexistingFrames + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'success_append_catalog_' . $seed,
                    'key_name' => 'success_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.items',
                    'value' => new SQLiteJsonSubtypeValue(json_encode(['before', 'kept-' . $seed], JSON_THROW_ON_ERROR)),
                    'wal_frame_index' => $preexistingFrames + 2,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'success_insert_audit_' . $seed,
                    'key_name' => 'success_audit_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.source',
                    'value' => 'successful-materialized-' . $seed,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 7000 + 3,
                    'insert_load_policy' => 'auto',
                    'initial_value' => '{}',
                    'page_number' => $auditPage,
                    'wal_frame_index' => $preexistingFrames + 3,
                ],
            ];

            $databaseBytes = self::scenarioDatabaseBytes($pageSize, max($auditPage, $catalogPage, $featurePage));
            $walBytes = self::scenarioChecksummedWalBytes($pageSize, $preexistingFrames, 0x9400 + $seed, 0x9500 + $seed);
            $plan = self::plan($rows, $mutations, [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'wal_bytes' => $walBytes,
                'transaction' => 'application_success_json_import_' . $seed,
                'savepoint' => 'success_json_batch_' . $seed,
                'pre_savepoint_wal_pages' => $preSavepointWalPages,
                'materialize_success_wal_frames' => true,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'preexisting_frames' => $preexistingFrames,
                'pre_savepoint_wal_pages' => $preSavepointWalPages,
                'expected_applied_pages' => [$featurePage, $catalogPage, $auditPage],
                'expected_inserted_key' => 'success_audit_payload_' . $seed,
                'database_bytes' => $databaseBytes,
                'wal_bytes' => $walBytes,
                'plan' => $plan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicFullRunMaterializedWalScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL full-run materialized dynamic parity requires at least one scenario');
        }

        $scenarios = [];
        for ($seed = 1; $seed <= $scenarioCount; $seed++) {
            $pageSize = $seed % 2 === 0 ? 1024 : 512;
            $tenantId = 7100 + $seed;
            $preexistingFrames = 1 + ($seed % 4);
            $featurePage = 50 + ($seed % 8);
            $catalogPage = 720 + $seed;
            $brokenPage = 820 + $seed;
            $auditPage = 920 + $seed;
            $finalPage = 1020 + $seed;
            $jsonbMode = $seed % 2 === 0;
            $preSavepointWalPages = [];
            for ($frame = 1; $frame <= $preexistingFrames; $frame++) {
                $preSavepointWalPages[] = 1100 + $seed + $frame;
            }

            $rows = [
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 8000 + 1,
                    'key_name' => 'full_run_feature_flags_' . $seed,
                    'key_value' => json_encode(['enabled' => false, 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'yes',
                    'page_number' => $featurePage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 8000 + 2,
                    'key_name' => 'full_run_catalog_payload_' . $seed,
                    'key_value' => $jsonbMode
                        ? new SQLiteBlobValue(SQLiteJsonB::encode(['items' => ['before'], 'seed' => $seed]))
                        : json_encode(['items' => ['before'], 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'no',
                    'page_number' => $catalogPage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 8000 + 3,
                    'key_name' => 'full_run_broken_payload_' . $seed,
                    'key_value' => '{"broken":',
                    'load_policy' => 'no',
                    'page_number' => $brokenPage,
                ],
            ];

            $failedMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'full_run_enable_failed_' . $seed,
                    'key_name' => 'full_run_feature_flags_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => $preexistingFrames + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'full_run_catalog_failed_' . $seed,
                    'key_name' => 'full_run_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.items',
                    'value' => new SQLiteJsonSubtypeValue(json_encode(['before', 'discarded-' . $seed], JSON_THROW_ON_ERROR)),
                    'wal_frame_index' => $preexistingFrames + 2,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'full_run_broken_failed_' . $seed,
                    'key_name' => 'full_run_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => $preexistingFrames + 3,
                ],
            ];

            $databaseBytes = self::scenarioDatabaseBytes($pageSize, max($finalPage, $auditPage, $brokenPage, $catalogPage, $featurePage));
            $walBytes = self::scenarioChecksummedWalBytes($pageSize, $preexistingFrames + 3, 0x9600 + $seed, 0x9700 + $seed);
            $failedPlan = self::plan($rows, $failedMutations, [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'wal_bytes' => $walBytes,
                'transaction' => 'application_full_run_json_import_failed_' . $seed,
                'savepoint' => 'full_run_json_batch_failed_' . $seed,
                'pre_savepoint_wal_pages' => $preSavepointWalPages,
            ]);

            $retryRows = $rows;
            $retryRows[2]['key_value'] = json_encode(['fixed' => false, 'seed' => $seed], JSON_THROW_ON_ERROR);
            $retryMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'full_run_enable_retry_' . $seed,
                    'key_name' => 'full_run_feature_flags_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => $preexistingFrames + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'full_run_catalog_retry_' . $seed,
                    'key_name' => 'full_run_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.items',
                    'value' => new SQLiteJsonSubtypeValue(json_encode(['before', 'kept-' . $seed], JSON_THROW_ON_ERROR)),
                    'wal_frame_index' => $preexistingFrames + 2,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'full_run_fixed_retry_' . $seed,
                    'key_name' => 'full_run_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.fixed',
                    'value' => true,
                    'wal_frame_index' => $preexistingFrames + 3,
                ],
            ];
            $retryPlan = self::plan($retryRows, $retryMutations, [
                'database_bytes' => $failedPlan['restored_database_bytes'],
                'page_size' => $pageSize,
                'wal_bytes' => $failedPlan['wal_bytes_after'],
                'transaction' => 'application_full_run_json_import_retry_' . $seed,
                'savepoint' => 'full_run_json_batch_retry_' . $seed,
                'pre_savepoint_wal_pages' => $preSavepointWalPages,
                'materialize_success_wal_frames' => true,
            ]);

            $followupRows = [
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 8000 + 1,
                    'key_name' => 'full_run_feature_flags_' . $seed,
                    'key_value' => json_encode(['enabled' => true, 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'yes',
                    'page_number' => $featurePage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 8000 + 2,
                    'key_name' => 'full_run_catalog_payload_' . $seed,
                    'key_value' => $jsonbMode
                        ? new SQLiteBlobValue(SQLiteJsonB::encode(['items' => ['before', 'kept-' . $seed], 'seed' => $seed]))
                        : json_encode(['items' => ['before', 'kept-' . $seed], 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'no',
                    'page_number' => $catalogPage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 8000 + 3,
                    'key_name' => 'full_run_broken_payload_' . $seed,
                    'key_value' => json_encode(['fixed' => true, 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'no',
                    'page_number' => $brokenPage,
                ],
            ];
            $followupFrameStart = $preexistingFrames + 4;
            $followupPreSavepointWalPages = array_merge($preSavepointWalPages, [$featurePage, $catalogPage, $brokenPage]);
            $followupMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'full_run_catalog_followup_' . $seed,
                    'key_name' => 'full_run_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.final',
                    'value' => 'chained-' . $seed,
                    'wal_frame_index' => $followupFrameStart,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'full_run_insert_final_' . $seed,
                    'key_name' => 'full_run_final_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.complete',
                    'value' => true,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 8000 + 4,
                    'insert_load_policy' => 'auto',
                    'initial_value' => '{}',
                    'page_number' => $finalPage,
                    'wal_frame_index' => $followupFrameStart + 1,
                ],
            ];
            $followupPlan = self::plan($followupRows, $followupMutations, [
                'database_bytes' => $retryPlan['database_bytes_after_import'],
                'page_size' => $pageSize,
                'wal_bytes' => $retryPlan['wal_bytes_after'],
                'transaction' => 'application_full_run_json_import_followup_' . $seed,
                'savepoint' => 'full_run_json_batch_followup_' . $seed,
                'pre_savepoint_wal_pages' => $followupPreSavepointWalPages,
                'materialize_success_wal_frames' => true,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'preexisting_frames' => $preexistingFrames,
                'pre_savepoint_wal_pages' => $preSavepointWalPages,
                'followup_pre_savepoint_wal_pages' => $followupPreSavepointWalPages,
                'expected_retry_pages' => [$featurePage, $catalogPage, $brokenPage],
                'expected_followup_pages' => [$catalogPage, $finalPage],
                'expected_final_key' => 'full_run_final_payload_' . $seed,
                'database_bytes' => $databaseBytes,
                'wal_bytes' => $walBytes,
                'failed_plan' => $failedPlan,
                'retry_plan' => $retryPlan,
                'followup_plan' => $followupPlan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicCommittedPrefixFailureScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL committed-prefix failure dynamic parity requires at least one scenario');
        }

        $baseScenarios = self::dynamicFullRunMaterializedWalScenarios($scenarioCount);
        $scenarios = [];
        foreach ($baseScenarios as $base) {
            $seed = (int) $base['seed'];
            $tenantId = (int) $base['tenant_id'];
            $pageSize = (int) $base['page_size'];
            $preexistingFrames = (int) $base['preexisting_frames'];
            $retryPlan = $base['retry_plan'];
            $retryFrameCount = (int) $retryPlan['wal_frame_count_after'];
            $catalogPage = (int) $base['expected_retry_pages'][1];
            $brokenPage = (int) $base['expected_retry_pages'][2];
            $auditPage = 1220 + $seed;
            $jsonbMode = (bool) $base['jsonb_mode'];
            $committedPrefixPages = array_merge(
                $base['pre_savepoint_wal_pages'],
                $base['expected_retry_pages']
            );

            $rows = [
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 9000 + 1,
                    'key_name' => 'committed_prefix_catalog_payload_' . $seed,
                    'key_value' => $jsonbMode
                        ? new SQLiteBlobValue(SQLiteJsonB::encode(['items' => ['before', 'kept-' . $seed], 'seed' => $seed]))
                        : json_encode(['items' => ['before', 'kept-' . $seed], 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'no',
                    'page_number' => $catalogPage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 9000 + 2,
                    'key_name' => 'committed_prefix_broken_payload_' . $seed,
                    'key_value' => json_encode(['fixed' => true, 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'no',
                    'page_number' => $brokenPage,
                ],
            ];

            $tailMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'committed_prefix_catalog_tail_' . $seed,
                    'key_name' => 'committed_prefix_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.tail',
                    'value' => 'discarded-tail-' . $seed,
                    'wal_frame_index' => $retryFrameCount + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'committed_prefix_insert_audit_' . $seed,
                    'key_name' => 'committed_prefix_audit_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.audit',
                    'value' => true,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 9000 + 3,
                    'insert_load_policy' => 'auto',
                    'initial_value' => '{}',
                    'page_number' => $auditPage,
                    'wal_frame_index' => $retryFrameCount + 2,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'committed_prefix_malformed_tail_' . $seed,
                    'key_name' => 'committed_prefix_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.broken',
                    'value' => new SQLiteJsonSubtypeValue('{"unterminated":'),
                    'wal_frame_index' => $retryFrameCount + 3,
                ],
            ];

            $tailWalBytes = self::appendScenarioWalFrames(
                (string) $retryPlan['wal_bytes_after'],
                $pageSize,
                [
                    $retryFrameCount + 1 => $catalogPage,
                    $retryFrameCount + 2 => $auditPage,
                    $retryFrameCount + 3 => $brokenPage,
                ],
                'app-json-dynamic-committed-prefix-tail:' . $seed . ':'
            );
            $tailPlan = self::plan($rows, $tailMutations, [
                'database_bytes' => (string) $retryPlan['database_bytes_after_import'],
                'page_size' => $pageSize,
                'wal_bytes' => $tailWalBytes,
                'transaction' => 'application_committed_prefix_json_import_' . $seed,
                'savepoint' => 'committed_prefix_json_batch_' . $seed,
                'pre_savepoint_wal_pages' => $committedPrefixPages,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'preexisting_frames' => $preexistingFrames,
                'committed_prefix_frame_count' => $retryFrameCount,
                'committed_prefix_pages' => $committedPrefixPages,
                'expected_tail_pages' => [$catalogPage, $auditPage],
                'expected_failed_statement' => 'committed_prefix_malformed_tail_' . $seed,
                'tail_wal_bytes' => $tailWalBytes,
                'tail_plan' => $tailPlan,
                'retry_plan' => $retryPlan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicRollbackDisabledMaterializedWalScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL rollback-disabled materialized dynamic parity requires at least one scenario');
        }

        $scenarios = [];
        for ($seed = 1; $seed <= $scenarioCount; $seed++) {
            $pageSize = $seed % 2 === 0 ? 1024 : 512;
            $tenantId = 8100 + $seed;
            $preexistingFrames = 1 + ($seed % 4);
            $featurePage = 62 + ($seed % 5);
            $catalogPage = 1320 + $seed;
            $brokenPage = 1420 + $seed;
            $jsonbMode = $seed % 3 === 2;
            $preSavepointWalPages = [];
            for ($frame = 1; $frame <= $preexistingFrames; $frame++) {
                $preSavepointWalPages[] = 1500 + $seed + $frame;
            }

            $rows = [
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 10000 + 1,
                    'key_name' => 'disabled_rollback_feature_payload_' . $seed,
                    'key_value' => json_encode(['enabled' => false, 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'yes',
                    'page_number' => $featurePage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 10000 + 2,
                    'key_name' => 'disabled_rollback_catalog_payload_' . $seed,
                    'key_value' => $jsonbMode
                        ? new SQLiteBlobValue(SQLiteJsonB::encode(['items' => ['before'], 'seed' => $seed]))
                        : json_encode(['items' => ['before'], 'seed' => $seed], JSON_THROW_ON_ERROR),
                    'load_policy' => 'no',
                    'page_number' => $catalogPage,
                ],
                [
                    'tenant_id' => $tenantId,
                    'setting_id' => $seed * 10000 + 3,
                    'key_name' => 'disabled_rollback_broken_payload_' . $seed,
                    'key_value' => '{"broken":',
                    'load_policy' => 'no',
                    'page_number' => $brokenPage,
                ],
            ];

            $mutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_rollback_enable_feature_' . $seed,
                    'key_name' => 'disabled_rollback_feature_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => $preexistingFrames + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_rollback_catalog_append_' . $seed,
                    'key_name' => 'disabled_rollback_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.items',
                    'value' => new SQLiteJsonSubtypeValue(json_encode(['before', 'kept-despite-failure-' . $seed], JSON_THROW_ON_ERROR)),
                    'wal_frame_index' => $preexistingFrames + 2,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_rollback_broken_payload_' . $seed,
                    'key_name' => 'disabled_rollback_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.enabled',
                    'value' => true,
                    'wal_frame_index' => $preexistingFrames + 3,
                ],
            ];

            $databaseBytes = self::scenarioDatabaseBytes($pageSize, max($brokenPage, $catalogPage, $featurePage));
            $walBytes = self::scenarioWalBytes($pageSize, $preexistingFrames, 0x8a00 + $seed, 0x8b00 + $seed);
            $plan = self::plan($rows, $mutations, [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'wal_bytes' => $walBytes,
                'transaction' => 'application_disabled_rollback_json_import_' . $seed,
                'savepoint' => 'disabled_rollback_json_batch_' . $seed,
                'pre_savepoint_wal_pages' => $preSavepointWalPages,
                'rollback_on_error' => false,
                'materialize_success_wal_frames' => true,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'preexisting_frames' => $preexistingFrames,
                'pre_savepoint_wal_pages' => $preSavepointWalPages,
                'expected_applied_pages' => [$featurePage, $catalogPage],
                'expected_failed_statement' => 'disabled_rollback_broken_payload_' . $seed,
                'database_bytes' => $databaseBytes,
                'wal_bytes' => $walBytes,
                'plan' => $plan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicRollbackDisabledFollowupScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL rollback-disabled followup dynamic parity requires at least one scenario');
        }

        $baseScenarios = self::dynamicRollbackDisabledMaterializedWalScenarios($scenarioCount);
        $scenarios = [];
        foreach ($baseScenarios as $base) {
            $seed = (int) $base['seed'];
            $tenantId = (int) $base['tenant_id'];
            $pageSize = (int) $base['page_size'];
            $partialPlan = $base['plan'];
            $partialFrameCount = (int) $partialPlan['wal_frame_count_after'];
            $catalogPage = (int) $base['expected_applied_pages'][1];
            $followupPage = 1520 + $seed;
            $jsonbMode = (bool) $base['jsonb_mode'];
            $committedPrefixPages = array_merge(
                $base['pre_savepoint_wal_pages'],
                $base['expected_applied_pages']
            );

            $followupRows = $partialPlan['import_plan']['final_rows'];
            $followupMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_followup_catalog_append_' . $seed,
                    'key_name' => 'disabled_rollback_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.followup',
                    'value' => 'after-partial-' . $seed,
                    'wal_frame_index' => $partialFrameCount + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_followup_insert_summary_' . $seed,
                    'key_name' => 'disabled_rollback_followup_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.complete',
                    'value' => true,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 10000 + 4,
                    'insert_load_policy' => 'auto',
                    'initial_value' => '{}',
                    'page_number' => $followupPage,
                    'wal_frame_index' => $partialFrameCount + 2,
                ],
            ];

            $followupPlan = self::plan($followupRows, $followupMutations, [
                'database_bytes' => (string) $partialPlan['database_bytes_after_import'],
                'page_size' => $pageSize,
                'wal_bytes' => (string) $partialPlan['wal_bytes_after'],
                'transaction' => 'application_disabled_followup_json_import_' . $seed,
                'savepoint' => 'disabled_followup_json_batch_' . $seed,
                'pre_savepoint_wal_pages' => $committedPrefixPages,
                'materialize_success_wal_frames' => true,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'preexisting_frames' => $base['preexisting_frames'],
                'partial_frame_count' => $partialFrameCount,
                'committed_prefix_pages' => $committedPrefixPages,
                'expected_followup_pages' => [$catalogPage, $followupPage],
                'expected_inserted_key' => 'disabled_rollback_followup_payload_' . $seed,
                'partial_plan' => $partialPlan,
                'followup_plan' => $followupPlan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicRollbackDisabledFollowupFailureScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL rollback-disabled followup failure dynamic parity requires at least one scenario');
        }

        $baseScenarios = self::dynamicRollbackDisabledFollowupScenarios($scenarioCount);
        $scenarios = [];
        foreach ($baseScenarios as $base) {
            $seed = (int) $base['seed'];
            $tenantId = (int) $base['tenant_id'];
            $pageSize = (int) $base['page_size'];
            $jsonbMode = (bool) $base['jsonb_mode'];
            $followupPlan = $base['followup_plan'];
            $partialPlan = $base['partial_plan'];
            $committedPrefixFrameCount = (int) $followupPlan['wal_frame_count_after'];
            $tailFrameStart = $committedPrefixFrameCount + 1;
            $catalogPage = (int) $base['expected_followup_pages'][0];
            $tailInsertPage = 1620 + $seed;
            $brokenPage = 1420 + $seed;
            $committedPrefixPages = array_merge(
                $base['committed_prefix_pages'],
                $base['expected_followup_pages']
            );

            $tailRows = $followupPlan['import_plan']['final_rows'];
            $tailMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_followup_tail_catalog_' . $seed,
                    'key_name' => 'disabled_rollback_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.tail',
                    'value' => 'rolled-back-tail-' . $seed,
                    'wal_frame_index' => $tailFrameStart,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_followup_tail_insert_' . $seed,
                    'key_name' => 'disabled_followup_tail_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.queued',
                    'value' => true,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 10000 + 5,
                    'insert_load_policy' => 'auto',
                    'initial_value' => '{}',
                    'page_number' => $tailInsertPage,
                    'wal_frame_index' => $tailFrameStart + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_followup_tail_broken_payload_' . $seed,
                    'key_name' => 'disabled_rollback_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.tail',
                    'value' => true,
                    'wal_frame_index' => $tailFrameStart + 2,
                ],
            ];

            $tailWalBytes = self::appendScenarioWalFrames(
                (string) $followupPlan['wal_bytes_after'],
                $pageSize,
                [
                    $tailFrameStart => $catalogPage,
                    $tailFrameStart + 1 => $tailInsertPage,
                    $tailFrameStart + 2 => $brokenPage,
                ],
                'app-json-dynamic-disabled-followup-tail:' . $seed . ':'
            );
            $tailPlan = self::plan($tailRows, $tailMutations, [
                'database_bytes' => (string) $followupPlan['database_bytes_after_import'],
                'page_size' => $pageSize,
                'wal_bytes' => $tailWalBytes,
                'transaction' => 'application_disabled_followup_tail_json_import_' . $seed,
                'savepoint' => 'disabled_followup_tail_json_batch_' . $seed,
                'pre_savepoint_wal_pages' => $committedPrefixPages,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'preexisting_frames' => $base['preexisting_frames'],
                'partial_frame_count' => $base['partial_frame_count'],
                'committed_prefix_frame_count' => $committedPrefixFrameCount,
                'committed_prefix_pages' => $committedPrefixPages,
                'expected_tail_pages' => [$catalogPage, $tailInsertPage],
                'expected_tail_inserted_key' => 'disabled_followup_tail_payload_' . $seed,
                'expected_failed_statement' => 'disabled_followup_tail_broken_payload_' . $seed,
                'tail_broken_page' => $brokenPage,
                'tail_wal_bytes' => $tailWalBytes,
                'partial_plan' => $partialPlan,
                'followup_plan' => $followupPlan,
                'tail_plan' => $tailPlan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicRollbackDisabledFollowupRecoveryScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL rollback-disabled followup recovery dynamic parity requires at least one scenario');
        }

        $baseScenarios = self::dynamicRollbackDisabledFollowupFailureScenarios($scenarioCount);
        $scenarios = [];
        foreach ($baseScenarios as $base) {
            $seed = (int) $base['seed'];
            $tenantId = (int) $base['tenant_id'];
            $pageSize = (int) $base['page_size'];
            $jsonbMode = (bool) $base['jsonb_mode'];
            $followupPlan = $base['followup_plan'];
            $tailPlan = $base['tail_plan'];
            $committedPrefixFrameCount = (int) $tailPlan['wal_frame_count_after'];
            $catalogPage = (int) $base['expected_tail_pages'][0];
            $recoveryInsertPage = 1720 + $seed;
            $recoveryFrameStart = $committedPrefixFrameCount + 1;

            $recoveryRows = $followupPlan['import_plan']['final_rows'];
            $recoveryMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_followup_recovery_catalog_' . $seed,
                    'key_name' => 'disabled_rollback_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.recovery',
                    'value' => 'after-tail-rollback-' . $seed,
                    'wal_frame_index' => $recoveryFrameStart,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_followup_recovery_insert_' . $seed,
                    'key_name' => 'disabled_followup_recovery_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.recovered',
                    'value' => true,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 10000 + 6,
                    'insert_load_policy' => 'auto',
                    'initial_value' => '{}',
                    'page_number' => $recoveryInsertPage,
                    'wal_frame_index' => $recoveryFrameStart + 1,
                ],
            ];

            $recoveryPlan = self::plan($recoveryRows, $recoveryMutations, [
                'database_bytes' => (string) $tailPlan['restored_database_bytes'],
                'page_size' => $pageSize,
                'wal_bytes' => (string) $tailPlan['wal_bytes_after'],
                'transaction' => 'application_disabled_followup_recovery_json_import_' . $seed,
                'savepoint' => 'disabled_followup_recovery_json_batch_' . $seed,
                'pre_savepoint_wal_pages' => $base['committed_prefix_pages'],
                'materialize_success_wal_frames' => true,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'preexisting_frames' => $base['preexisting_frames'],
                'partial_frame_count' => $base['partial_frame_count'],
                'committed_prefix_frame_count' => $committedPrefixFrameCount,
                'committed_prefix_pages' => $base['committed_prefix_pages'],
                'expected_recovery_pages' => [$catalogPage, $recoveryInsertPage],
                'expected_recovery_inserted_key' => 'disabled_followup_recovery_payload_' . $seed,
                'expected_recovery_inserted_id' => $seed * 10000 + 6,
                'rejected_tail_inserted_key' => $base['expected_tail_inserted_key'],
                'partial_plan' => $base['partial_plan'],
                'followup_plan' => $followupPlan,
                'tail_plan' => $tailPlan,
                'recovery_plan' => $recoveryPlan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicRollbackDisabledPostRecoveryFailureScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL rollback-disabled post-recovery failure dynamic parity requires at least one scenario');
        }

        $baseScenarios = self::dynamicRollbackDisabledFollowupRecoveryScenarios($scenarioCount);
        $scenarios = [];
        foreach ($baseScenarios as $base) {
            $seed = (int) $base['seed'];
            $tenantId = (int) $base['tenant_id'];
            $pageSize = (int) $base['page_size'];
            $jsonbMode = (bool) $base['jsonb_mode'];
            $recoveryPlan = $base['recovery_plan'];
            $committedPrefixFrameCount = (int) $recoveryPlan['wal_frame_count_after'];
            $catalogPage = (int) $base['expected_recovery_pages'][0];
            $postRecoveryInsertPage = 1820 + $seed;
            $brokenPage = 1420 + $seed;
            $postRecoveryFrameStart = $committedPrefixFrameCount + 1;
            $committedPrefixPages = array_merge(
                $base['committed_prefix_pages'],
                $base['expected_recovery_pages']
            );

            $postRecoveryRows = $recoveryPlan['import_plan']['final_rows'];
            $postRecoveryMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_post_recovery_tail_catalog_' . $seed,
                    'key_name' => 'disabled_rollback_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.post_recovery_tail',
                    'value' => 'rolled-back-after-recovery-' . $seed,
                    'wal_frame_index' => $postRecoveryFrameStart,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_post_recovery_tail_insert_' . $seed,
                    'key_name' => 'disabled_post_recovery_tail_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.queued',
                    'value' => true,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 10000 + 7,
                    'insert_load_policy' => 'auto',
                    'initial_value' => '{}',
                    'page_number' => $postRecoveryInsertPage,
                    'wal_frame_index' => $postRecoveryFrameStart + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_post_recovery_tail_broken_payload_' . $seed,
                    'key_name' => 'disabled_rollback_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.post_recovery_tail',
                    'value' => true,
                    'wal_frame_index' => $postRecoveryFrameStart + 2,
                ],
            ];

            $postRecoveryWalBytes = self::appendScenarioWalFrames(
                (string) $recoveryPlan['wal_bytes_after'],
                $pageSize,
                [
                    $postRecoveryFrameStart => $catalogPage,
                    $postRecoveryFrameStart + 1 => $postRecoveryInsertPage,
                    $postRecoveryFrameStart + 2 => $brokenPage,
                ],
                'app-json-dynamic-disabled-post-recovery-tail:' . $seed . ':'
            );
            $postRecoveryFailurePlan = self::plan($postRecoveryRows, $postRecoveryMutations, [
                'database_bytes' => (string) $recoveryPlan['database_bytes_after_import'],
                'page_size' => $pageSize,
                'wal_bytes' => $postRecoveryWalBytes,
                'transaction' => 'application_disabled_post_recovery_tail_json_import_' . $seed,
                'savepoint' => 'disabled_post_recovery_tail_json_batch_' . $seed,
                'pre_savepoint_wal_pages' => $committedPrefixPages,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'preexisting_frames' => $base['preexisting_frames'],
                'partial_frame_count' => $base['partial_frame_count'],
                'committed_prefix_frame_count' => $committedPrefixFrameCount,
                'committed_prefix_pages' => $committedPrefixPages,
                'expected_post_recovery_pages' => [$catalogPage, $postRecoveryInsertPage],
                'expected_post_recovery_inserted_key' => 'disabled_post_recovery_tail_payload_' . $seed,
                'expected_post_recovery_inserted_id' => $seed * 10000 + 7,
                'expected_failed_statement' => 'disabled_post_recovery_tail_broken_payload_' . $seed,
                'rejected_prior_tail_inserted_key' => $base['rejected_tail_inserted_key'],
                'tail_broken_page' => $brokenPage,
                'post_recovery_wal_bytes' => $postRecoveryWalBytes,
                'partial_plan' => $base['partial_plan'],
                'followup_plan' => $base['followup_plan'],
                'previous_tail_plan' => $base['tail_plan'],
                'recovery_plan' => $recoveryPlan,
                'post_recovery_failure_plan' => $postRecoveryFailurePlan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicRollbackDisabledReopenedPrefixSuccessScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL rollback-disabled reopened-prefix success dynamic parity requires at least one scenario');
        }

        return self::dynamicRollbackDisabledReopenedPrefixSuccessScenariosFrom(
            self::dynamicRollbackDisabledPostRecoveryRecoveryScenarios($scenarioCount)
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicPostCheckpointFollowupScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL post-checkpoint followup dynamic parity requires at least one scenario');
        }

        return self::dynamicPostCheckpointFollowupScenariosFromCheckpointScenarios(
            self::dynamicPostRecoveryCheckpointScenarios($scenarioCount)
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicPostCheckpointTailFailureScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL post-checkpoint tail failure dynamic parity requires at least one scenario');
        }

        return self::dynamicPostCheckpointTailFailureScenariosFromFollowupScenarios(
            self::dynamicPostCheckpointFollowupScenarios($scenarioCount)
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicPostCheckpointTailRecoveryScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL post-checkpoint tail recovery dynamic parity requires at least one scenario');
        }

        return self::dynamicPostCheckpointTailRecoveryScenariosFromTailFailureScenarios(
            self::dynamicPostCheckpointTailFailureScenarios($scenarioCount)
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicPostCheckpointTailRecoveryCheckpointScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL post-checkpoint tail recovery checkpoint dynamic parity requires at least one scenario');
        }

        return self::dynamicPostCheckpointTailRecoveryCheckpointScenariosFromTailRecoveryScenarios(
            self::dynamicPostCheckpointTailRecoveryScenarios($scenarioCount)
        );
    }

    /**
     * @param list<array<string,mixed>> $baseScenarios
     * @return list<array<string,mixed>>
     */
    public static function dynamicRollbackDisabledReopenedPrefixSuccessScenariosFrom(array $baseScenarios): array
    {
        if ($baseScenarios === []) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL rollback-disabled reopened-prefix success dynamic parity requires base scenarios');
        }

        $scenarios = [];
        foreach ($baseScenarios as $base) {
            $seed = (int) $base['seed'];
            $tenantId = (int) $base['tenant_id'];
            $pageSize = (int) $base['page_size'];
            $jsonbMode = (bool) $base['jsonb_mode'];
            $previousRecoveryPlan = $base['post_recovery_recovery_plan'];
            $committedPrefixFrameCount = (int) $previousRecoveryPlan['wal_frame_count_after'];
            $catalogPage = (int) $base['expected_recovery_pages'][0];
            $previousRecoveryInsertPage = (int) $base['expected_recovery_pages'][1];
            $reopenedInsertPage = 2120 + $seed;
            $reopenedFrameStart = $committedPrefixFrameCount + 1;
            $committedPrefixPages = array_merge(
                $base['committed_prefix_pages'],
                $base['expected_recovery_pages']
            );

            $reopenedRows = $previousRecoveryPlan['import_plan']['final_rows'];
            $reopenedMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_reopened_success_catalog_' . $seed,
                    'key_name' => 'disabled_rollback_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.reopened_success',
                    'value' => 'committed-after-reopened-prefix-' . $seed,
                    'wal_frame_index' => $reopenedFrameStart,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_reopened_success_insert_' . $seed,
                    'key_name' => 'disabled_reopened_prefix_success_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.committed',
                    'value' => true,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 10000 + 9,
                    'insert_load_policy' => 'auto',
                    'initial_value' => '{}',
                    'page_number' => $reopenedInsertPage,
                    'wal_frame_index' => $reopenedFrameStart + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_reopened_success_previous_recovery_' . $seed,
                    'key_name' => $base['expected_recovery_inserted_key'],
                    'function' => 'json_set',
                    'path' => '$.reopened_success_seen',
                    'value' => true,
                    'page_number' => $previousRecoveryInsertPage,
                    'wal_frame_index' => $reopenedFrameStart + 2,
                ],
            ];

            $reopenedSuccessPlan = self::plan($reopenedRows, $reopenedMutations, [
                'database_bytes' => (string) $previousRecoveryPlan['database_bytes_after_import'],
                'page_size' => $pageSize,
                'wal_bytes' => (string) $previousRecoveryPlan['wal_bytes_after'],
                'transaction' => 'application_disabled_reopened_success_json_import_' . $seed,
                'savepoint' => 'disabled_reopened_success_json_batch_' . $seed,
                'pre_savepoint_wal_pages' => $committedPrefixPages,
                'materialize_success_wal_frames' => true,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'preexisting_frames' => $base['preexisting_frames'],
                'partial_frame_count' => $base['partial_frame_count'],
                'committed_prefix_frame_count' => $committedPrefixFrameCount,
                'committed_prefix_pages' => $committedPrefixPages,
                'expected_reopened_pages' => [$catalogPage, $reopenedInsertPage, $previousRecoveryInsertPage],
                'expected_reopened_inserted_key' => 'disabled_reopened_prefix_success_payload_' . $seed,
                'expected_reopened_inserted_id' => $seed * 10000 + 9,
                'expected_previous_recovery_inserted_key' => $base['expected_recovery_inserted_key'],
                'rejected_prior_tail_inserted_key' => $base['rejected_prior_tail_inserted_key'],
                'rejected_post_recovery_tail_inserted_key' => $base['rejected_post_recovery_tail_inserted_key'],
                'status_chain' => $base['status_chain'] + [
                    'post_recovery_recovery' => $previousRecoveryPlan['status'],
                ],
                'previous_recovery_database_hash' => hash('sha256', (string) $previousRecoveryPlan['database_bytes_after_import']),
                'previous_recovery_wal_hash' => hash('sha256', (string) $previousRecoveryPlan['wal_bytes_after']),
                'previous_recovery_final_row_count' => count($previousRecoveryPlan['import_plan']['final_rows']),
                'reopened_success_plan' => $reopenedSuccessPlan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicRollbackDisabledPostRecoveryRecoveryScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL rollback-disabled post-recovery recovery dynamic parity requires at least one scenario');
        }

        $baseScenarios = self::dynamicRollbackDisabledPostRecoveryFailureScenarios($scenarioCount);
        $scenarios = [];
        foreach ($baseScenarios as $base) {
            $seed = (int) $base['seed'];
            $tenantId = (int) $base['tenant_id'];
            $pageSize = (int) $base['page_size'];
            $jsonbMode = (bool) $base['jsonb_mode'];
            $postRecoveryFailurePlan = $base['post_recovery_failure_plan'];
            $committedPrefixFrameCount = (int) $postRecoveryFailurePlan['wal_frame_count_after'];
            $catalogPage = (int) $base['expected_post_recovery_pages'][0];
            $recoveryInsertPage = 1920 + $seed;
            $recoveryFrameStart = $committedPrefixFrameCount + 1;

            $recoveryRows = $base['recovery_plan']['import_plan']['final_rows'];
            $recoveryMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_post_recovery_recovery_catalog_' . $seed,
                    'key_name' => 'disabled_rollback_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.post_recovery_recovered',
                    'value' => 'after-post-recovery-tail-' . $seed,
                    'wal_frame_index' => $recoveryFrameStart,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'disabled_post_recovery_recovery_insert_' . $seed,
                    'key_name' => 'disabled_post_recovery_recovery_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.recovered',
                    'value' => true,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 10000 + 8,
                    'insert_load_policy' => 'auto',
                    'initial_value' => '{}',
                    'page_number' => $recoveryInsertPage,
                    'wal_frame_index' => $recoveryFrameStart + 1,
                ],
            ];

            $recoveryPlan = self::plan($recoveryRows, $recoveryMutations, [
                'database_bytes' => (string) $postRecoveryFailurePlan['restored_database_bytes'],
                'page_size' => $pageSize,
                'wal_bytes' => (string) $postRecoveryFailurePlan['wal_bytes_after'],
                'transaction' => 'application_disabled_post_recovery_recovery_json_import_' . $seed,
                'savepoint' => 'disabled_post_recovery_recovery_json_batch_' . $seed,
                'pre_savepoint_wal_pages' => $base['committed_prefix_pages'],
                'materialize_success_wal_frames' => true,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'preexisting_frames' => $base['preexisting_frames'],
                'partial_frame_count' => $base['partial_frame_count'],
                'committed_prefix_frame_count' => $committedPrefixFrameCount,
                'committed_prefix_pages' => $base['committed_prefix_pages'],
                'expected_recovery_pages' => [$catalogPage, $recoveryInsertPage],
                'expected_recovery_inserted_key' => 'disabled_post_recovery_recovery_payload_' . $seed,
                'expected_recovery_inserted_id' => $seed * 10000 + 8,
                'rejected_prior_tail_inserted_key' => $base['rejected_prior_tail_inserted_key'],
                'rejected_post_recovery_tail_inserted_key' => $base['expected_post_recovery_inserted_key'],
                'status_chain' => [
                    'partial' => $base['partial_plan']['status'],
                    'followup' => $base['followup_plan']['status'],
                    'previous_tail' => $base['previous_tail_plan']['status'],
                    'previous_recovery' => $base['recovery_plan']['status'],
                    'post_recovery_failure' => $postRecoveryFailurePlan['status'],
                ],
                'post_failure_database_hash' => hash('sha256', (string) $postRecoveryFailurePlan['restored_database_bytes']),
                'post_failure_wal_hash' => hash('sha256', (string) $postRecoveryFailurePlan['wal_bytes_after']),
                'post_recovery_recovery_plan' => $recoveryPlan,
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function dynamicPostRecoveryCheckpointScenarios(int $scenarioCount = 16): array
    {
        if ($scenarioCount < 1) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL post-recovery checkpoint dynamic parity requires at least one scenario');
        }

        return self::dynamicPostRecoveryCheckpointScenariosFromRecoveryScenarios(
            self::dynamicRollbackDisabledPostRecoveryRecoveryScenarios($scenarioCount)
        );
    }

    /**
     * @param list<array<string,mixed>> $baseScenarios
     * @return list<array<string,mixed>>
     */
    public static function dynamicPostRecoveryCheckpointScenariosFromRecoveryScenarios(array $baseScenarios): array
    {
        if ($baseScenarios === []) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL post-recovery checkpoint dynamic parity requires at least one recovery scenario');
        }

        $scenarios = [];
        foreach ($baseScenarios as $base) {
            $seed = (int) $base['seed'];
            $pageSize = (int) $base['page_size'];
            $mode = $seed % 2 === 0 ? 'truncate' : 'restart';
            $recoveryPlan = $base['post_recovery_recovery_plan'];
            $wal = SQLiteWal::parse((string) $recoveryPlan['wal_bytes_after'], $pageSize, false);
            $checkpointInput = (string) $recoveryPlan['database_bytes_before'];
            $checkpointPlan = $wal->checkpointPlan($checkpointInput);
            $releasedCheckpoint = $wal->durableCheckpointResult($checkpointInput, $mode);
            $readerEndFrame = max(0, (int) $checkpointPlan['last_commit_frame'] - 1);
            $pinnedCheckpoint = $wal->durableCheckpointResult($checkpointInput, $mode, $readerEndFrame);

            $latestCommittedPageImages = [];
            $lastCommitFrame = (int) ($checkpointPlan['last_commit_frame'] ?? 0);
            foreach ($wal->frames as $frame) {
                if ($frame->index > $lastCommitFrame) {
                    break;
                }
                $latestCommittedPageImages[$frame->pageNumber] = $frame->pageImage;
            }

            $appliedFrameIndexes = [];
            $appliedPageNumbers = [];
            $supersededFrameIndexes = [];
            $supersededPageNumbers = [];
            foreach ($checkpointPlan['frames'] as $frame) {
                if ($frame['applied']) {
                    $appliedFrameIndexes[] = $frame['frame_index'];
                    $appliedPageNumbers[] = $frame['page_number'];
                }
                if ($frame['reason'] === 'superseded_by_later_committed_frame') {
                    $supersededFrameIndexes[] = $frame['frame_index'];
                    $supersededPageNumbers[] = $frame['page_number'];
                }
            }

            $releasedMatchesExpectedPages = true;
            foreach ($base['expected_recovery_pages'] as $pageNumber) {
                $pageNumber = (int) $pageNumber;
                $releasedImage = self::databasePageSlice((string) $releasedCheckpoint['database_bytes'], $pageSize, $pageNumber);
                if ($releasedImage === null || $releasedImage !== ($latestCommittedPageImages[$pageNumber] ?? null)) {
                    $releasedMatchesExpectedPages = false;
                    break;
                }
            }

            $insertedPage = (int) $base['expected_recovery_pages'][1];
            $pinnedInsertedPageImage = self::databasePageSlice((string) $pinnedCheckpoint['database_bytes'], $pageSize, $insertedPage);
            $pinnedMatchesCorrectedInsert = $pinnedInsertedPageImage !== null
                && $pinnedInsertedPageImage === ($latestCommittedPageImages[$insertedPage] ?? null);

            $finalKeys = array_column($recoveryPlan['import_plan']['final_rows'], 'key_name');

            $scenarios[] = array_merge($base, [
                'checkpoint_mode' => $mode,
                'reader_end_frame' => $readerEndFrame,
                'checkpoint_database_bytes_before_hash' => hash('sha256', $checkpointInput),
                'checkpoint_plan' => $checkpointPlan,
                'released_checkpoint' => $releasedCheckpoint,
                'pinned_checkpoint' => $pinnedCheckpoint,
                'expected_checkpoint_action' => $mode === 'truncate' ? 'truncate_wal' : 'restart_wal',
                'expected_released_wal_bytes_length' => $mode === 'truncate' ? 0 : 32,
                'expected_recovery_pages_checkpointed' => $releasedMatchesExpectedPages,
                'pinned_insert_page_matches_corrected_recovery' => $pinnedMatchesCorrectedInsert,
                'applied_frame_indexes' => $appliedFrameIndexes,
                'applied_page_numbers' => $appliedPageNumbers,
                'superseded_frame_indexes' => $supersededFrameIndexes,
                'superseded_page_numbers' => array_values(array_unique($supersededPageNumbers)),
                'recovery_inserted_key_retained' => in_array($base['expected_recovery_inserted_key'], $finalKeys, true),
                'rejected_prior_tail_key_retained' => in_array($base['rejected_prior_tail_inserted_key'], $finalKeys, true),
                'rejected_post_recovery_tail_key_retained' => in_array($base['rejected_post_recovery_tail_inserted_key'], $finalKeys, true),
            ]);
        }

        return $scenarios;
    }

    /**
     * @param list<array<string,mixed>> $baseScenarios
     * @return list<array<string,mixed>>
     */
    public static function dynamicPostCheckpointFollowupScenariosFromCheckpointScenarios(array $baseScenarios): array
    {
        if ($baseScenarios === []) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL post-checkpoint followup dynamic parity requires at least one checkpoint scenario');
        }

        $scenarios = [];
        foreach ($baseScenarios as $base) {
            $seed = (int) $base['seed'];
            $tenantId = (int) $base['tenant_id'];
            $pageSize = (int) $base['page_size'];
            $jsonbMode = (bool) $base['jsonb_mode'];
            $releasedCheckpoint = $base['released_checkpoint'];
            $checkpointedDatabase = (string) $releasedCheckpoint['database_bytes'];
            $checkpointWalBytes = (string) $releasedCheckpoint['wal_bytes'];
            $startedNewWalHeader = $checkpointWalBytes === '';
            if ($startedNewWalHeader) {
                $checkpointWalBytes = self::emptyCheckpointWalBytes(
                    $pageSize,
                    $releasedCheckpoint['next_wal_header_salt'] ?? [0x51, 0x52],
                    1
                );
            }

            $catalogPage = (int) $base['expected_recovery_pages'][0];
            $followupInsertPage = 2020 + $seed;
            $followupRows = $base['post_recovery_recovery_plan']['import_plan']['final_rows'];
            $followupMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'post_checkpoint_catalog_' . $seed,
                    'key_name' => 'disabled_rollback_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.after_checkpoint',
                    'value' => 'after-checkpoint-' . $seed,
                    'wal_frame_index' => 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'post_checkpoint_insert_' . $seed,
                    'key_name' => 'post_checkpoint_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.complete',
                    'value' => true,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 10000 + 9,
                    'insert_load_policy' => 'auto',
                    'initial_value' => '{}',
                    'page_number' => $followupInsertPage,
                    'wal_frame_index' => 2,
                ],
            ];

            $followupPlan = self::plan($followupRows, $followupMutations, [
                'database_bytes' => $checkpointedDatabase,
                'page_size' => $pageSize,
                'wal_bytes' => $checkpointWalBytes,
                'transaction' => 'application_post_checkpoint_json_import_' . $seed,
                'savepoint' => 'post_checkpoint_json_batch_' . $seed,
                'pre_savepoint_wal_pages' => [],
                'materialize_success_wal_frames' => true,
            ]);

            $finalKeys = array_column($followupPlan['import_plan']['final_rows'], 'key_name');
            $scenarios[] = array_merge($base, [
                'post_checkpoint_input_database_hash' => hash('sha256', $checkpointedDatabase),
                'post_checkpoint_input_wal_hash' => hash('sha256', $checkpointWalBytes),
                'post_checkpoint_wal_bytes' => $checkpointWalBytes,
                'post_checkpoint_started_new_wal_header' => $startedNewWalHeader,
                'post_checkpoint_wal_header_length' => strlen($checkpointWalBytes),
                'expected_followup_pages' => [$catalogPage, $followupInsertPage],
                'expected_followup_inserted_key' => 'post_checkpoint_payload_' . $seed,
                'expected_followup_inserted_id' => $seed * 10000 + 9,
                'post_checkpoint_followup_plan' => $followupPlan,
                'followup_inserted_key_retained' => in_array('post_checkpoint_payload_' . $seed, $finalKeys, true),
                'rejected_prior_tail_key_retained_after_followup' => in_array($base['rejected_prior_tail_inserted_key'], $finalKeys, true),
                'rejected_post_recovery_tail_key_retained_after_followup' => in_array($base['rejected_post_recovery_tail_inserted_key'], $finalKeys, true),
            ]);
        }

        return $scenarios;
    }

    /**
     * @param list<array<string,mixed>> $baseScenarios
     * @return list<array<string,mixed>>
     */
    public static function dynamicPostCheckpointTailFailureScenariosFromFollowupScenarios(array $baseScenarios): array
    {
        if ($baseScenarios === []) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL post-checkpoint tail failure dynamic parity requires at least one followup scenario');
        }

        $scenarios = [];
        foreach ($baseScenarios as $base) {
            $seed = (int) $base['seed'];
            $tenantId = (int) $base['tenant_id'];
            $pageSize = (int) $base['page_size'];
            $jsonbMode = (bool) $base['jsonb_mode'];
            $followupPlan = $base['post_checkpoint_followup_plan'];
            $committedPrefixFrameCount = (int) $followupPlan['wal_frame_count_after'];
            $tailFrameStart = $committedPrefixFrameCount + 1;
            $catalogPage = (int) $base['expected_followup_pages'][0];
            $tailInsertPage = 1930 + $seed;
            $brokenPage = 1420 + $seed;
            $committedPrefixPages = $base['expected_followup_pages'];

            $tailRows = $followupPlan['import_plan']['final_rows'];
            $tailMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'post_checkpoint_tail_catalog_' . $seed,
                    'key_name' => 'disabled_rollback_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.post_checkpoint_tail',
                    'value' => 'rolled-back-after-checkpoint-' . $seed,
                    'wal_frame_index' => $tailFrameStart,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'post_checkpoint_tail_insert_' . $seed,
                    'key_name' => 'post_checkpoint_tail_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.queued',
                    'value' => true,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 10000 + 10,
                    'insert_load_policy' => 'auto',
                    'initial_value' => '{}',
                    'page_number' => $tailInsertPage,
                    'wal_frame_index' => $tailFrameStart + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'post_checkpoint_tail_broken_payload_' . $seed,
                    'key_name' => 'disabled_rollback_broken_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.post_checkpoint_tail',
                    'value' => true,
                    'wal_frame_index' => $tailFrameStart + 2,
                ],
            ];

            $tailWalBytes = self::appendScenarioWalFrames(
                (string) $followupPlan['wal_bytes_after'],
                $pageSize,
                [
                    $tailFrameStart => $catalogPage,
                    $tailFrameStart + 1 => $tailInsertPage,
                    $tailFrameStart + 2 => $brokenPage,
                ],
                'app-json-dynamic-post-checkpoint-tail:' . $seed . ':'
            );
            $tailPlan = self::plan($tailRows, $tailMutations, [
                'database_bytes' => (string) $followupPlan['database_bytes_after_import'],
                'page_size' => $pageSize,
                'wal_bytes' => $tailWalBytes,
                'transaction' => 'application_post_checkpoint_tail_json_import_' . $seed,
                'savepoint' => 'post_checkpoint_tail_json_batch_' . $seed,
                'pre_savepoint_wal_pages' => $committedPrefixPages,
            ]);

            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'checkpoint_mode' => $base['checkpoint_mode'],
                'post_checkpoint_started_new_wal_header' => $base['post_checkpoint_started_new_wal_header'],
                'expected_followup_pages' => $base['expected_followup_pages'],
                'expected_followup_inserted_key' => $base['expected_followup_inserted_key'],
                'rejected_prior_tail_key_retained_after_followup' => $base['rejected_prior_tail_key_retained_after_followup'],
                'rejected_post_recovery_tail_key_retained_after_followup' => $base['rejected_post_recovery_tail_key_retained_after_followup'],
                'committed_prefix_frame_count' => $committedPrefixFrameCount,
                'committed_prefix_pages' => $committedPrefixPages,
                'expected_post_checkpoint_tail_pages' => [$catalogPage, $tailInsertPage],
                'expected_post_checkpoint_tail_inserted_key' => 'post_checkpoint_tail_payload_' . $seed,
                'expected_post_checkpoint_tail_inserted_id' => $seed * 10000 + 10,
                'expected_failed_statement' => 'post_checkpoint_tail_broken_payload_' . $seed,
                'tail_broken_page' => $brokenPage,
                'post_checkpoint_followup_database_hash' => hash('sha256', (string) $followupPlan['database_bytes_after_import']),
                'post_checkpoint_followup_wal_hash' => hash('sha256', (string) $followupPlan['wal_bytes_after']),
                'post_checkpoint_followup_final_row_count' => count($followupPlan['import_plan']['final_rows']),
                'post_checkpoint_followup_plan' => $followupPlan,
                'post_checkpoint_tail_failure_plan' => $tailPlan,
            ];
        }

        return $scenarios;
    }

    /**
     * @param list<array<string,mixed>> $baseScenarios
     * @return list<array<string,mixed>>
     */
    public static function dynamicPostCheckpointTailRecoveryScenariosFromTailFailureScenarios(array $baseScenarios): array
    {
        if ($baseScenarios === []) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL post-checkpoint tail recovery dynamic parity requires at least one tail failure scenario');
        }

        $scenarios = [];
        foreach ($baseScenarios as $base) {
            $seed = (int) $base['seed'];
            $tenantId = (int) $base['tenant_id'];
            $pageSize = (int) $base['page_size'];
            $jsonbMode = (bool) $base['jsonb_mode'];
            $tailPlan = $base['post_checkpoint_tail_failure_plan'];
            $followupPlan = $base['post_checkpoint_followup_plan'];
            $committedPrefixFrameCount = (int) $tailPlan['wal_frame_count_after'];
            $recoveryFrameStart = $committedPrefixFrameCount + 1;
            $catalogPage = (int) $base['expected_followup_pages'][0];
            $recoveryInsertPage = 1940 + $seed;
            $followupInsertPage = (int) $base['expected_followup_pages'][1];

            $recoveryRows = $followupPlan['import_plan']['final_rows'];
            $recoveryMutations = [
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'post_checkpoint_recovery_catalog_' . $seed,
                    'key_name' => 'disabled_rollback_catalog_payload_' . $seed,
                    'function' => $jsonbMode ? 'jsonb_set' : 'json_set',
                    'path' => '$.post_checkpoint_recovery',
                    'value' => 'after-tail-rollback-' . $seed,
                    'wal_frame_index' => $recoveryFrameStart,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'post_checkpoint_recovery_insert_' . $seed,
                    'key_name' => 'post_checkpoint_recovery_payload_' . $seed,
                    'function' => 'json_set',
                    'path' => '$.recovered',
                    'value' => true,
                    'on_missing' => 'insert',
                    'insert_setting_id' => $seed * 10000 + 11,
                    'insert_load_policy' => 'auto',
                    'initial_value' => '{}',
                    'page_number' => $recoveryInsertPage,
                    'wal_frame_index' => $recoveryFrameStart + 1,
                ],
                [
                    'tenant_id' => $tenantId,
                    'statement' => 'post_checkpoint_recovery_followup_seen_' . $seed,
                    'key_name' => $base['expected_followup_inserted_key'],
                    'function' => 'json_set',
                    'path' => '$.post_checkpoint_recovery_seen',
                    'value' => true,
                    'page_number' => $followupInsertPage,
                    'wal_frame_index' => $recoveryFrameStart + 2,
                ],
            ];

            $recoveryPlan = self::plan($recoveryRows, $recoveryMutations, [
                'database_bytes' => (string) $tailPlan['restored_database_bytes'],
                'page_size' => $pageSize,
                'wal_bytes' => (string) $tailPlan['wal_bytes_after'],
                'transaction' => 'application_post_checkpoint_recovery_json_import_' . $seed,
                'savepoint' => 'post_checkpoint_recovery_json_batch_' . $seed,
                'pre_savepoint_wal_pages' => $base['committed_prefix_pages'],
                'materialize_success_wal_frames' => true,
            ]);

            $finalKeys = array_column($recoveryPlan['import_plan']['final_rows'], 'key_name');
            $scenarios[] = [
                'seed' => $seed,
                'tenant_id' => $tenantId,
                'page_size' => $pageSize,
                'jsonb_mode' => $jsonbMode,
                'checkpoint_mode' => $base['checkpoint_mode'],
                'expected_followup_pages' => $base['expected_followup_pages'],
                'expected_followup_inserted_key' => $base['expected_followup_inserted_key'],
                'rejected_prior_tail_key_retained_after_followup' => $base['rejected_prior_tail_key_retained_after_followup'],
                'rejected_post_recovery_tail_key_retained_after_followup' => $base['rejected_post_recovery_tail_key_retained_after_followup'],
                'committed_prefix_frame_count' => $committedPrefixFrameCount,
                'committed_prefix_pages' => $base['committed_prefix_pages'],
                'expected_post_checkpoint_recovery_pages' => [$catalogPage, $recoveryInsertPage, $followupInsertPage],
                'expected_post_checkpoint_recovery_inserted_key' => 'post_checkpoint_recovery_payload_' . $seed,
                'expected_post_checkpoint_recovery_inserted_id' => $seed * 10000 + 11,
                'rejected_post_checkpoint_tail_inserted_key' => $base['expected_post_checkpoint_tail_inserted_key'],
                'post_checkpoint_tail_failure_database_hash' => hash('sha256', (string) $tailPlan['restored_database_bytes']),
                'post_checkpoint_tail_failure_wal_hash' => hash('sha256', (string) $tailPlan['wal_bytes_after']),
                'post_checkpoint_tail_failure_row_count' => $base['post_checkpoint_followup_final_row_count'],
                'post_checkpoint_tail_failure_plan' => $tailPlan,
                'post_checkpoint_tail_recovery_plan' => $recoveryPlan,
                'post_checkpoint_recovery_inserted_key_retained' => in_array('post_checkpoint_recovery_payload_' . $seed, $finalKeys, true),
                'post_checkpoint_tail_inserted_key_retained_after_recovery' => in_array($base['expected_post_checkpoint_tail_inserted_key'], $finalKeys, true),
                'post_checkpoint_followup_inserted_key_retained_after_recovery' => in_array($base['expected_followup_inserted_key'], $finalKeys, true),
            ];
        }

        return $scenarios;
    }

    /**
     * @param list<array<string,mixed>> $baseScenarios
     * @return list<array<string,mixed>>
     */
    public static function dynamicPostCheckpointTailRecoveryCheckpointScenariosFromTailRecoveryScenarios(array $baseScenarios): array
    {
        if ($baseScenarios === []) {
            throw new \InvalidArgumentException('SQLite Application JSON WAL post-checkpoint tail recovery checkpoint dynamic parity requires at least one tail recovery scenario');
        }

        $scenarios = [];
        foreach ($baseScenarios as $base) {
            $seed = (int) $base['seed'];
            $pageSize = (int) $base['page_size'];
            $mode = (string) $base['checkpoint_mode'];
            $recoveryPlan = $base['post_checkpoint_tail_recovery_plan'];
            $wal = SQLiteWal::parse((string) $recoveryPlan['wal_bytes_after'], $pageSize, false);
            $checkpointInput = (string) $recoveryPlan['database_bytes_before'];
            $checkpointPlan = $wal->checkpointPlan($checkpointInput);
            $releasedCheckpoint = $wal->durableCheckpointResult($checkpointInput, $mode);
            $readerEndFrame = max(0, (int) ($checkpointPlan['last_commit_frame'] ?? 0) - 1);
            $pinnedCheckpoint = $wal->durableCheckpointResult($checkpointInput, $mode, $readerEndFrame);

            $latestCommittedPageImages = [];
            $lastCommitFrame = (int) ($checkpointPlan['last_commit_frame'] ?? 0);
            foreach ($wal->frames as $frame) {
                if ($frame->index > $lastCommitFrame) {
                    break;
                }
                $latestCommittedPageImages[$frame->pageNumber] = $frame->pageImage;
            }

            $appliedFrameIndexes = [];
            $appliedPageNumbers = [];
            $supersededFrameIndexes = [];
            $supersededPageNumbers = [];
            foreach ($checkpointPlan['frames'] as $frame) {
                if ($frame['applied']) {
                    $appliedFrameIndexes[] = $frame['frame_index'];
                    $appliedPageNumbers[] = $frame['page_number'];
                }
                if ($frame['reason'] === 'superseded_by_later_committed_frame') {
                    $supersededFrameIndexes[] = $frame['frame_index'];
                    $supersededPageNumbers[] = $frame['page_number'];
                }
            }

            $releasedMatchesRecoveryPages = true;
            foreach ($base['expected_post_checkpoint_recovery_pages'] as $pageNumber) {
                $pageNumber = (int) $pageNumber;
                $releasedImage = self::databasePageSlice((string) $releasedCheckpoint['database_bytes'], $pageSize, $pageNumber);
                if ($releasedImage === null || $releasedImage !== ($latestCommittedPageImages[$pageNumber] ?? null)) {
                    $releasedMatchesRecoveryPages = false;
                    break;
                }
            }

            $recoveryPages = $base['expected_post_checkpoint_recovery_pages'];
            $catalogPage = (int) $recoveryPages[0];
            $insertedPage = (int) $recoveryPages[1];
            $followupPage = (int) $recoveryPages[2];
            $pinnedCatalogPageImage = self::databasePageSlice((string) $pinnedCheckpoint['database_bytes'], $pageSize, $catalogPage);
            $pinnedInsertedPageImage = self::databasePageSlice((string) $pinnedCheckpoint['database_bytes'], $pageSize, $insertedPage);
            $pinnedFollowupPageImage = self::databasePageSlice((string) $pinnedCheckpoint['database_bytes'], $pageSize, $followupPage);

            $finalKeys = array_column($recoveryPlan['import_plan']['final_rows'], 'key_name');

            $scenarios[] = array_merge($base, [
                'tail_recovery_checkpoint_database_bytes_before_hash' => hash('sha256', $checkpointInput),
                'tail_recovery_checkpoint_plan' => $checkpointPlan,
                'tail_recovery_released_checkpoint' => $releasedCheckpoint,
                'tail_recovery_pinned_checkpoint' => $pinnedCheckpoint,
                'tail_recovery_checkpoint_reader_end_frame' => $readerEndFrame,
                'expected_tail_recovery_checkpoint_action' => $mode === 'truncate' ? 'truncate_wal' : 'restart_wal',
                'expected_tail_recovery_released_wal_bytes_length' => $mode === 'truncate' ? 0 : 32,
                'tail_recovery_checkpointed_pages_match' => $releasedMatchesRecoveryPages,
                'tail_recovery_pinned_catalog_matches_recovery' => $pinnedCatalogPageImage !== null
                    && $pinnedCatalogPageImage === ($latestCommittedPageImages[$catalogPage] ?? null),
                'tail_recovery_pinned_insert_page_matches_recovery' => $pinnedInsertedPageImage !== null
                    && $pinnedInsertedPageImage === ($latestCommittedPageImages[$insertedPage] ?? null),
                'tail_recovery_pinned_followup_page_matches_final_recovery' => $pinnedFollowupPageImage !== null
                    && $pinnedFollowupPageImage === ($latestCommittedPageImages[$followupPage] ?? null),
                'tail_recovery_applied_frame_indexes' => $appliedFrameIndexes,
                'tail_recovery_applied_page_numbers' => $appliedPageNumbers,
                'tail_recovery_superseded_frame_indexes' => $supersededFrameIndexes,
                'tail_recovery_superseded_page_numbers' => array_values(array_unique($supersededPageNumbers)),
                'tail_recovery_inserted_key_retained_after_checkpoint' => in_array($base['expected_post_checkpoint_recovery_inserted_key'], $finalKeys, true),
                'tail_recovery_rejected_tail_key_retained_after_checkpoint' => in_array($base['rejected_post_checkpoint_tail_inserted_key'], $finalKeys, true),
            ]);
        }

        return $scenarios;
    }

    /**
     * @param array<string,mixed> $importPlan
     */
    private static function assertRollbackFramesExist(array $importPlan, int $walFrameCount): void
    {
        $frameIndexes = [];
        foreach ($importPlan['wal_rollback_to_savepoint']['discarded_wal_frames'] ?? [] as $frame) {
            if (is_array($frame) && isset($frame['frame_index'])) {
                $frameIndexes[] = (int) $frame['frame_index'];
            }
        }
        foreach ($importPlan['failed'] ?? [] as $failure) {
            if (!is_array($failure)) {
                continue;
            }
            foreach ($failure['rollback']['discarded_wal_frames'] ?? [] as $frame) {
                if (is_array($frame) && isset($frame['frame_index'])) {
                    $frameIndexes[] = (int) $frame['frame_index'];
                }
            }
        }

        $missing = array_values(array_unique(array_filter(
            $frameIndexes,
            static fn (int $frameIndex): bool => $frameIndex > $walFrameCount
        )));
        sort($missing);
        if ($missing !== []) {
            throw new \InvalidArgumentException(
                'SQLite Application JSON import rollback WAL bytes are missing current batch frame(s): ' . implode(', ', $missing)
            );
        }
    }

    private static function emptyWalBytes(int $pageSize): string
    {
        return pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 0, 0x51, 0x52, 0, 0);
    }

    /**
     * @param array{0:int,1:int}|list<int> $salt
     */
    private static function emptyCheckpointWalBytes(int $pageSize, array $salt, int $checkpointSequence): string
    {
        $saltOne = (int) ($salt[0] ?? 0x51);
        $saltTwo = (int) ($salt[1] ?? 0x52);
        $prefix = pack(
            'N*',
            SQLiteWalHeader::MAGIC_BIG_ENDIAN,
            3007000,
            $pageSize,
            $checkpointSequence,
            $saltOne,
            $saltTwo
        );
        $checksum = SQLiteWal::checksumPair($prefix, false);

        return $prefix . pack('N*', $checksum[0], $checksum[1]);
    }

    /**
     * @param list<array<string,mixed>> $appliedStatements
     * @return array{wal_bytes:string,appended_frame_count:int}
     */
    private static function appendSuccessfulWalFrames(
        string $walBytes,
        string $databaseBytes,
        array $appliedStatements,
        int $pageSize
    ): array {
        $walState = self::walState($walBytes, $pageSize);
        $header = unpack('Nmagic/Nversion/Npage_size/Ncheckpoint/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($walBytes, 0, 32));
        if (!is_array($header)) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback WAL bytes require a valid WAL header');
        }

        $checksumSeed = SQLiteWal::checksumPair(substr($walBytes, 0, 24), false);
        for ($frame = 1; $frame <= $walState['frame_count']; $frame++) {
            $frameOffset = 32 + (($frame - 1) * $walState['frame_size']);
            $pageBytes = substr($walBytes, $frameOffset + 24, $pageSize);
            $checksumSeed = SQLiteWal::checksumPair(
                substr($walBytes, $frameOffset, 8) . $pageBytes,
                false,
                $checksumSeed[0],
                $checksumSeed[1]
            );
        }

        $pendingFrames = [];
        foreach ($appliedStatements as $statement) {
            $frameIndex = (int) ($statement['wal_frame_index'] ?? 0);
            if ($frameIndex <= $walState['frame_count']) {
                continue;
            }
            $pageNumber = (int) ($statement['page_number'] ?? 0);
            if ($pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite Application JSON import rollback applied WAL frame requires a page number');
            }
            $pageOffset = ($pageNumber - 1) * $pageSize;
            if ($pageOffset + $pageSize > strlen($databaseBytes)) {
                throw new \InvalidArgumentException(
                    'SQLite Application JSON import rollback applied WAL frame page is outside the database image: ' . $pageNumber
                );
            }
            $pendingFrames[$frameIndex] = [
                'page_number' => $pageNumber,
                'page_bytes' => substr($databaseBytes, $pageOffset, $pageSize),
            ];
        }

        ksort($pendingFrames);
        $nextFrame = $walState['frame_count'] + 1;
        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        $pendingCount = count($pendingFrames);
        $pendingOrdinal = 0;
        foreach ($pendingFrames as $frameIndex => $frame) {
            if ($frameIndex !== $nextFrame) {
                throw new \InvalidArgumentException(
                    'SQLite Application JSON import rollback success WAL frame indexes must be contiguous after rollback'
                );
            }
            $pendingOrdinal++;
            $commitPageCount = $pendingOrdinal === $pendingCount ? $databasePageCount : 0;
            $framePrefix = pack('N*', $frame['page_number'], $commitPageCount, (int) $header['salt_1'], (int) $header['salt_2']);
            $checksumSeed = SQLiteWal::checksumPair(
                substr($framePrefix, 0, 8) . $frame['page_bytes'],
                false,
                $checksumSeed[0],
                $checksumSeed[1]
            );
            $walBytes .= $framePrefix . pack('N*', $checksumSeed[0], $checksumSeed[1]) . $frame['page_bytes'];
            $nextFrame++;
        }

        return [
            'wal_bytes' => $walBytes,
            'appended_frame_count' => count($pendingFrames),
        ];
    }

    private static function scenarioDatabaseBytes(int $pageSize, int $maxPage): string
    {
        $bytes = '';
        for ($page = 1; $page <= $maxPage; $page++) {
            $bytes .= str_pad("app-json-dynamic-page:{$page}:before", $pageSize, "\0");
        }

        return $bytes;
    }

    private static function scenarioWalBytes(int $pageSize, int $frames, int $saltOne, int $saltTwo): string
    {
        $bytes = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 0, $saltOne, $saltTwo, 0, 0);
        for ($frame = 1; $frame <= $frames; $frame++) {
            $bytes .= pack('N*', $frame + 1, 0, $saltOne, $saltTwo, 0, 0)
                . str_pad("app-json-dynamic-wal-frame:{$frame}", $pageSize, "\0");
        }

        return $bytes;
    }

    private static function scenarioChecksummedWalBytes(int $pageSize, int $frames, int $saltOne, int $saltTwo): string
    {
        $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 0, $saltOne, $saltTwo);
        $checksum = SQLiteWal::checksumPair($prefix, false);
        $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);
        $seed = SQLiteWal::checksumPair(substr($bytes, 0, 24), false);
        for ($frame = 1; $frame <= $frames; $frame++) {
            $page = str_pad("app-json-dynamic-checksum-wal-frame:{$frame}", $pageSize, "\0");
            $framePrefix = pack('N*', $frame + 1, 0, $saltOne, $saltTwo);
            $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $page, false, $seed[0], $seed[1]);
            $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $page;
        }

        return $bytes;
    }

    /**
     * @param array<int,int> $framePages
     */
    private static function appendScenarioWalFrames(string $walBytes, int $pageSize, array $framePages, string $labelPrefix): string
    {
        if ($framePages === []) {
            return $walBytes;
        }

        $walState = self::walState($walBytes, $pageSize);
        $header = unpack('Nmagic/Nversion/Npage_size/Ncheckpoint/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($walBytes, 0, 32));
        if (!is_array($header)) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback WAL bytes require a valid WAL header');
        }

        $checksumSeed = SQLiteWal::checksumPair(substr($walBytes, 0, 24), false);
        for ($frame = 1; $frame <= $walState['frame_count']; $frame++) {
            $frameOffset = 32 + (($frame - 1) * $walState['frame_size']);
            $pageBytes = substr($walBytes, $frameOffset + 24, $pageSize);
            $checksumSeed = SQLiteWal::checksumPair(
                substr($walBytes, $frameOffset, 8) . $pageBytes,
                false,
                $checksumSeed[0],
                $checksumSeed[1]
            );
        }

        ksort($framePages);
        $nextFrame = $walState['frame_count'] + 1;
        foreach ($framePages as $frameIndex => $pageNumber) {
            if ((int) $frameIndex !== $nextFrame) {
                throw new \InvalidArgumentException(
                    'SQLite Application JSON import rollback scenario WAL frame indexes must be contiguous'
                );
            }
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite Application JSON import rollback scenario WAL pages must be one-based integers');
            }
            $page = str_pad($labelPrefix . $frameIndex, $pageSize, "\0");
            $framePrefix = pack('N*', $pageNumber, 0, (int) $header['salt_1'], (int) $header['salt_2']);
            $checksumSeed = SQLiteWal::checksumPair(
                substr($framePrefix, 0, 8) . $page,
                false,
                $checksumSeed[0],
                $checksumSeed[1]
            );
            $walBytes .= $framePrefix . pack('N*', $checksumSeed[0], $checksumSeed[1]) . $page;
            $nextFrame++;
        }

        return $walBytes;
    }

    private static function databasePageSlice(string $databaseBytes, int $pageSize, int $pageNumber): ?string
    {
        if ($pageNumber < 1) {
            return null;
        }
        $offset = ($pageNumber - 1) * $pageSize;
        if ($offset + $pageSize > strlen($databaseBytes)) {
            return null;
        }

        return substr($databaseBytes, $offset, $pageSize);
    }

    /**
     * @return array{frame_count:int,frame_size:int}
     */
    private static function walState(string $walBytes, int $pageSize): array
    {
        if (strlen($walBytes) < 32) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback WAL bytes require a 32 byte header');
        }
        $header = unpack('Nmagic/Nversion/Npage_size/Ncheckpoint/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($walBytes, 0, 32));
        if (!is_array($header) || (int) $header['magic'] !== SQLiteWalHeader::MAGIC_BIG_ENDIAN) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback WAL bytes require a valid WAL header');
        }
        if ((int) $header['page_size'] !== $pageSize) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback WAL page size must match the database page size');
        }
        if ((int) $header['checksum_1'] !== 0 || (int) $header['checksum_2'] !== 0) {
            $headerChecksum = SQLiteWal::checksumPair(substr($walBytes, 0, 24), false);
            if ((int) $header['checksum_1'] !== $headerChecksum[0] || (int) $header['checksum_2'] !== $headerChecksum[1]) {
                throw new \InvalidArgumentException('SQLite Application JSON import rollback WAL header checksum does not match the header content');
            }
        }

        $frameSize = 24 + $pageSize;
        $frameBytes = strlen($walBytes) - 32;
        if ($frameBytes % $frameSize !== 0) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback WAL bytes have a partial frame tail');
        }
        $frameCount = intdiv($frameBytes, $frameSize);
        $seed = SQLiteWal::checksumPair(substr($walBytes, 0, 24), false);
        for ($frame = 1; $frame <= $frameCount; $frame++) {
            $offset = 32 + (($frame - 1) * $frameSize);
            $frameHeader = unpack(
                'Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2',
                substr($walBytes, $offset, 24)
            );
            if (!is_array($frameHeader)) {
                throw new \InvalidArgumentException('SQLite Application JSON import rollback WAL frame header is incomplete');
            }
            if ((int) $frameHeader['page_number'] < 1) {
                throw new \InvalidArgumentException(
                    'SQLite Application JSON import rollback WAL frame ' . $frame . ' has an invalid page number'
                );
            }
            if ((int) $frameHeader['salt_1'] !== (int) $header['salt_1'] || (int) $frameHeader['salt_2'] !== (int) $header['salt_2']) {
                throw new \InvalidArgumentException(
                    'SQLite Application JSON import rollback WAL frame ' . $frame . ' salt does not match the WAL header'
                );
            }
            $page = substr($walBytes, $offset + 24, $pageSize);
            $seed = SQLiteWal::checksumPair(substr($walBytes, $offset, 8) . $page, false, $seed[0], $seed[1]);
            if ((int) $frameHeader['checksum_1'] !== 0 || (int) $frameHeader['checksum_2'] !== 0) {
                if ((int) $frameHeader['checksum_1'] !== $seed[0] || (int) $frameHeader['checksum_2'] !== $seed[1]) {
                    throw new \InvalidArgumentException(
                        'SQLite Application JSON import rollback WAL frame ' . $frame . ' checksum does not match the frame payload'
                    );
                }
            }
        }

        return [
            'frame_count' => $frameCount,
            'frame_size' => $frameSize,
        ];
    }
}
