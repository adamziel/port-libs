<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonImportRollbackWalPlan
{
    /**
     * @param list<array{setting_id:int,key_name:string,key_value:mixed,load_policy?:string,page_number?:int,tenant_id?:int}> $currentRows
     * @param list<array{key_name:string,function?:string,path:string,value:mixed,page_number?:int,wal_frame_index?:int,statement?:string,on_missing?:string,insert_setting_id?:int,insert_load_policy?:string,initial_value?:mixed,tenant_id?:int}> $mutations
     * @param array{database_bytes:string,page_size?:int,wal_bytes?:string,rollback_on_error?:bool,savepoint?:string,transaction?:string,pre_savepoint_wal_pages?:list<int>} $options
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

        $truncateToBytes = 32 + ($rollbackToFrame * (24 + $pageSize));
        $rolledBackWalBytes = $rollbackRequired ? substr($walBytes, 0, $truncateToBytes) : $walBytes;
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
            ];
        }

        return $scenarios;
    }

    private static function emptyWalBytes(int $pageSize): string
    {
        return pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 0, 0x51, 0x52, 0, 0);
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

        $frameSize = 24 + $pageSize;
        $frameBytes = strlen($walBytes) - 32;
        if ($frameBytes % $frameSize !== 0) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback WAL bytes have a partial frame tail');
        }

        return [
            'frame_count' => intdiv($frameBytes, $frameSize),
            'frame_size' => $frameSize,
        ];
    }
}
