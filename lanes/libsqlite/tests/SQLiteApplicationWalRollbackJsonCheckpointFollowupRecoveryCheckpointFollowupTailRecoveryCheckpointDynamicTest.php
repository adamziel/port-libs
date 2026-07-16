<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;

ini_set('memory_limit', '1536M');
unset($tests);

$tailRecoveryScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryScenarios(18);
$checkpointScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointScenariosFromTailRecoveryScenarios($tailRecoveryScenarios);
$directCheckpointScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointScenarios(18);

$tests = [
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery checkpoint exposes requested scenario count' => static function (TestRunner $t) use ($checkpointScenarios): void {
        $t->same(18, count($checkpointScenarios));
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery checkpoint direct factory matches base factory' => static function (TestRunner $t) use ($checkpointScenarios, $directCheckpointScenarios): void {
        $t->same(array_column($checkpointScenarios, 'tenant_id'), array_column($directCheckpointScenarios, 'tenant_id'));
        $t->same(array_column($checkpointScenarios, 'expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_pages'), array_column($directCheckpointScenarios, 'expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_pages'));
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery checkpoint covers checkpoint reset modes' => static function (TestRunner $t) use ($checkpointScenarios): void {
        $modes = array_values(array_unique(array_column($checkpointScenarios, 'followup_recovery_checkpoint_followup_tail_recovery_checkpoint_mode')));
        sort($modes);
        $t->same(['restart', 'truncate'], $modes);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery checkpoint covers both page sizes' => static function (TestRunner $t) use ($checkpointScenarios): void {
        $pageSizes = array_values(array_unique(array_column($checkpointScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery checkpoint covers json text and jsonb rows' => static function (TestRunner $t) use ($checkpointScenarios): void {
        $jsonModes = array_values(array_unique(array_column($checkpointScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery checkpoint covers reset actions' => static function (TestRunner $t) use ($checkpointScenarios): void {
        $actions = array_values(array_unique(array_column($checkpointScenarios, 'expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_action')));
        sort($actions);
        $t->same(['restart_wal', 'truncate_wal'], $actions);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery checkpoint rejects zero scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointScenarios(0);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery checkpoint rejects empty recovery base scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointScenariosFromTailRecoveryScenarios([]);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery checkpoint small batch remains deterministic' => static function (TestRunner $t): void {
        $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointScenarios(4);
        $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
        $t->same(['restart', 'truncate', 'restart', 'truncate'], array_column($smallBatch, 'followup_recovery_checkpoint_followup_tail_recovery_checkpoint_mode'));
        $t->same([[2421, 1321, 3021, 2621], [2422, 1322, 3022, 2622], [2423, 1323, 3023, 2623], [2424, 1324, 3024, 2624]], array_column($smallBatch, 'expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_pages'));
        $t->same([[3, 4, 5, 6], [3, 4, 5, 6], [3, 4, 5, 6], [3, 4, 5, 6]], array_column($smallBatch, 'followup_recovery_checkpoint_followup_tail_recovery_checkpoint_applied_frame_indexes'));
        $t->same(['restart_wal', 'truncate_wal', 'restart_wal', 'truncate_wal'], array_column($smallBatch, 'expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_action'));
    },
];

foreach ($checkpointScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $recoveryPlan = $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_plan'];
    $checkpointPlan = $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_plan'];
    $releasedCheckpoint = $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_released_checkpoint'];
    $pinnedCheckpoint = $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_pinned_checkpoint'];
    $prefix = 'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery checkpoint seed ' . $seed . ' ';

    $tests[$prefix . 'starts from corrected final tail recovery wal'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same('ready', $recoveryPlan['status']);
        $t->same(false, $recoveryPlan['rollback_required']);
        $t->same($scenario['committed_prefix_frame_count_after_final_tail_failure'] + 3, $recoveryPlan['wal_frame_count_after']);
        $t->same($scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_database_bytes_before_hash'], hash('sha256', (string) $recoveryPlan['database_bytes_before']));
    };
    $tests[$prefix . 'uses selected checkpoint reset mode and action'] = static function (TestRunner $t) use ($scenario, $releasedCheckpoint): void {
        $t->same($scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_mode'], $releasedCheckpoint['mode']);
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_action'], $releasedCheckpoint['wal_action']);
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_released_wal_bytes_length'], $releasedCheckpoint['wal_bytes_length']);
    };
    $tests[$prefix . 'checkpoint plan ends at final tail recovery commit frame'] = static function (TestRunner $t) use ($scenario, $checkpointPlan, $recoveryPlan): void {
        $t->same($recoveryPlan['wal_frame_count_after'], $checkpointPlan['last_commit_frame']);
        $t->same(intdiv(strlen((string) $recoveryPlan['database_bytes_after_import']), (int) $scenario['page_size']), $checkpointPlan['database_page_count']);
        $t->same(strlen((string) $recoveryPlan['database_bytes_after_import']), $checkpointPlan['final_database_bytes']);
    };
    $tests[$prefix . 'checkpoint applies latest final tail recovery pages'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpointed_pages_match']);
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_pages'], $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_applied_page_numbers']);
    };
    $tests[$prefix . 'checkpoint supersedes final followup images for reused pages'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, count($scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_superseded_frame_indexes']) >= 2);
        $t->same(true, in_array($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_pages'][0], $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_superseded_page_numbers'], true));
        $t->same(true, in_array($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_pages'][2], $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_superseded_page_numbers'], true));
    };
    $tests[$prefix . 'released checkpoint completes all committed frames'] = static function (TestRunner $t) use ($scenario, $releasedCheckpoint): void {
        $t->same(false, $releasedCheckpoint['busy']);
        $t->same(true, $releasedCheckpoint['can_reset']);
        $t->same(0, $releasedCheckpoint['remaining_committed_frame_count']);
        $t->same(count($scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_applied_frame_indexes']), $releasedCheckpoint['checkpointed_frame_count']);
    };
    $tests[$prefix . 'released checkpoint sidecar shape matches mode'] = static function (TestRunner $t) use ($scenario, $releasedCheckpoint): void {
        if ($scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_mode'] === 'truncate') {
            $t->same(null, $releasedCheckpoint['wal_header']);
            $t->same('', $releasedCheckpoint['wal_bytes']);
            return;
        }

        $t->same(true, is_array($releasedCheckpoint['wal_header']));
        $t->same(32, strlen($releasedCheckpoint['wal_bytes']));
    };
    $tests[$prefix . 'reader pinned checkpoint preserves wal and reports busy reset'] = static function (TestRunner $t) use ($scenario, $recoveryPlan, $pinnedCheckpoint): void {
        $t->same(true, $pinnedCheckpoint['busy']);
        $t->same(false, $pinnedCheckpoint['can_reset']);
        $t->same('preserve_wal', $pinnedCheckpoint['wal_action']);
        $t->same((string) $recoveryPlan['wal_bytes_after'], $pinnedCheckpoint['wal_bytes']);
        $t->same($scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_reader_end_frame'], $pinnedCheckpoint['reader_end_frame']);
    };
    $tests[$prefix . 'reader pinned checkpoint stops before final followup page refresh'] = static function (TestRunner $t) use ($scenario, $pinnedCheckpoint): void {
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_pinned_prior_recovery_page_matches_tail_recovery']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_pinned_catalog_matches_tail_recovery']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_pinned_insert_page_matches_tail_recovery']);
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_recovery_pinned_final_followup_page_matches_tail_recovery']);
        $t->same(1, $pinnedCheckpoint['remaining_committed_frame_count']);
    };
    $tests[$prefix . 'released checkpoint publishes final database page count'] = static function (TestRunner $t) use ($checkpointPlan, $releasedCheckpoint): void {
        $t->same($checkpointPlan['database_page_count'], $releasedCheckpoint['database_page_count']);
        $t->same($checkpointPlan['final_database_bytes'], $releasedCheckpoint['final_database_bytes']);
    };
    $tests[$prefix . 'keeps corrected rows and excludes failed final tail rows'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_inserted_key_retained_after_checkpoint']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_final_followup_key_retained_after_checkpoint']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_prior_recovery_key_retained_after_checkpoint']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_prior_followup_key_retained_after_checkpoint']);
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_recovery_failed_tail_key_retained_after_checkpoint']);
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_recovery_failed_bad_key_retained_after_checkpoint']);
    };
    $tests[$prefix . 'released checkpoint materializes every expected page image'] = static function (TestRunner $t) use ($scenario, $releasedCheckpoint): void {
        $t->same(true, strlen((string) $releasedCheckpoint['database_bytes']) >= max($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_pages']) * (int) $scenario['page_size']);
        foreach ($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_pages'] as $pageNumber) {
            $t->same(true, in_array($pageNumber, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_applied_page_numbers'], true));
        }
    };
    $tests[$prefix . 'records durable checkpoint dependencies'] = static function (TestRunner $t) use ($releasedCheckpoint): void {
        $t->same(['sqlite-wal-checkpoint', 'durable-sidecar-write'], $releasedCheckpoint['dependencies']);
    };
}

unset(
    $tailRecoveryScenarios,
    $checkpointScenarios,
    $directCheckpointScenarios,
    $scenario,
    $seed,
    $checkpointPlan,
    $releasedCheckpoint,
    $pinnedCheckpoint,
    $recoveryPlan,
    $prefix
);

return $tests;
