<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;

$summary = [
    'scenario' => 'application-wal-rollback-json-dynamic-parity',
];

$withScenarios = static function (callable $factory, callable $summarize): void {
    $scenarios = $factory();
    $summarize($scenarios);
    unset($scenarios);
    gc_collect_cycles();
};

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicParityScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['scenarioCount'] = count($scenarios);
        $summary['statuses'] = array_map(static fn (array $scenario): string => $scenario['plan']['status'], $scenarios);
        $summary['walFramesBefore'] = array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_before'], $scenarios);
        $summary['walFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $scenarios);
        $summary['restoredPages'] = array_map(static fn (array $scenario): array => $scenario['plan']['rollback_to_savepoint']['restored_page_numbers'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['preexistingWalScenarioCount'] = count($scenarios);
        $summary['preexistingWalStatuses'] = array_map(static fn (array $scenario): string => $scenario['plan']['status'], $scenarios);
        $summary['preexistingWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $scenarios);
        $summary['preexistingWalTruncateBytes'] = array_map(static fn (array $scenario): int => $scenario['plan']['wal_truncate_to_bytes'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicTenantCollisionScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['tenantCollisionScenarioCount'] = count($scenarios);
        $summary['tenantCollisionStatuses'] = array_map(static fn (array $scenario): string => $scenario['plan']['status'], $scenarios);
        $summary['tenantCollisionStablePages'] = array_map(static fn (array $scenario): int => $scenario['stable_page'], $scenarios);
        $summary['tenantCollisionRestoredPages'] = array_map(static fn (array $scenario): array => $scenario['plan']['rollback_to_savepoint']['restored_page_numbers'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicInsertedSettingRollbackScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['insertedSettingScenarioCount'] = count($scenarios);
        $summary['insertedSettingStatuses'] = array_map(static fn (array $scenario): string => $scenario['plan']['status'], $scenarios);
        $summary['insertedSettingIds'] = array_map(static fn (array $scenario): array => $scenario['inserted_setting_ids'], $scenarios);
        $summary['insertedSettingRestoredPages'] = array_map(static fn (array $scenario): array => $scenario['plan']['rollback_to_savepoint']['restored_page_numbers'], $scenarios);
        $summary['insertedSettingWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicDuplicateInsertedSettingRollbackScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['duplicateInsertedSettingScenarioCount'] = count($scenarios);
        $summary['duplicateInsertedSettingStatuses'] = array_map(static fn (array $scenario): string => $scenario['plan']['status'], $scenarios);
        $summary['duplicateInsertedSettingIds'] = array_map(static fn (array $scenario): int => $scenario['duplicate_setting_id'], $scenarios);
        $summary['duplicateInsertedSettingRestoredPages'] = array_map(static fn (array $scenario): array => $scenario['plan']['rollback_to_savepoint']['restored_page_numbers'], $scenarios);
        $summary['duplicateInsertedSettingErrors'] = array_map(static fn (array $scenario): string => $scenario['plan']['import_plan']['failed'][0]['error'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicMalformedInsertedInitialValueScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['malformedInsertedInitialValueScenarioCount'] = count($scenarios);
        $summary['malformedInsertedInitialValueStatuses'] = array_map(static fn (array $scenario): string => $scenario['plan']['status'], $scenarios);
        $summary['malformedInsertedInitialValueIds'] = array_map(static fn (array $scenario): int => $scenario['insert_setting_id'], $scenarios);
        $summary['malformedInsertedInitialValueStatementRestoredPages'] = array_map(static fn (array $scenario): array => $scenario['plan']['import_plan']['failed'][0]['rollback']['restored_page_numbers'], $scenarios);
        $summary['malformedInsertedInitialValueFailedFrames'] = array_map(static fn (array $scenario): array => array_column($scenario['plan']['import_plan']['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'), $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicDeferredFailureScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['deferredScenarioCount'] = count($scenarios);
        $summary['deferredStatuses'] = array_map(static fn (array $scenario): string => $scenario['plan']['status'], $scenarios);
        $summary['deferredWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalRetryScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['preexistingRetryScenarioCount'] = count($scenarios);
        $summary['preexistingRetryStatuses'] = array_map(static fn (array $scenario): string => $scenario['retry_plan']['status'], $scenarios);
        $summary['preexistingRetryFailedWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['failed_plan']['wal_frame_count_after'], $scenarios);
        $summary['preexistingRetryWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['retry_plan']['wal_frame_count_after'], $scenarios);
        $summary['preexistingRetryMaterializedChecksumPairs'] = array_map(static function (array $scenario): array {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $frameOffset = 32 + ((int) $scenario['preexisting_frames'] * $frameSize);
        $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr((string) $scenario['materialized_retry_plan']['wal_bytes_after'], $frameOffset, 24));

        return [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']];
        }, $scenarios);
        $summary['preexistingRetryMaterializedCommitMarkers'] = array_map(static function (array $scenario): array {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        foreach ($scenario['expected_retry_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + (((int) $scenario['preexisting_frames'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr((string) $scenario['materialized_retry_plan']['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }

        return $commits;
        }, $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicMissingWalTailScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['missingWalTailScenarioCount'] = count($scenarios);
        $summary['missingWalTailMessages'] = array_map(static fn (array $scenario): ?string => $scenario['exception_message'], $scenarios);
        $summary['missingWalTailShortFrameCounts'] = array_map(static fn (array $scenario): int => $scenario['short_frame_count'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicPartialWalTailScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['partialWalTailScenarioCount'] = count($scenarios);
        $summary['partialWalTailMessages'] = array_map(static fn (array $scenario): ?string => $scenario['exception_message'], $scenarios);
        $summary['partialWalTailCompleteFrameCounts'] = array_map(static fn (array $scenario): int => $scenario['complete_frame_count'], $scenarios);
        $summary['partialWalTailPayloadBytes'] = array_map(static fn (array $scenario): int => $scenario['partial_payload_bytes'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicFrameHeaderMismatchScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['frameHeaderMismatchScenarioCount'] = count($scenarios);
        $summary['frameHeaderMismatchMessages'] = array_map(static fn (array $scenario): ?string => $scenario['exception_message'], $scenarios);
        $summary['frameHeaderMismatchTargetFrames'] = array_map(static fn (array $scenario): int => $scenario['target_frame'], $scenarios);
        $summary['frameHeaderMismatchCorruptions'] = array_map(static fn (array $scenario): string => $scenario['corruption'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicFrameChecksumMismatchScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['frameChecksumMismatchScenarioCount'] = count($scenarios);
        $summary['frameChecksumMismatchMessages'] = array_map(static fn (array $scenario): ?string => $scenario['exception_message'], $scenarios);
        $summary['frameChecksumMismatchTargetFrames'] = array_map(static fn (array $scenario): int => $scenario['target_frame'], $scenarios);
        $summary['frameChecksumMismatchOffsets'] = array_map(static fn (array $scenario): int => $scenario['checksum_offset'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicHeaderChecksumMismatchScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['headerChecksumMismatchScenarioCount'] = count($scenarios);
        $summary['headerChecksumMismatchMessages'] = array_map(static fn (array $scenario): ?string => $scenario['exception_message'], $scenarios);
        $summary['headerChecksumMismatchOffsets'] = array_map(static fn (array $scenario): int => $scenario['checksum_offset'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicFullRunMaterializedWalScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['fullRunMaterializedWalScenarioCount'] = count($scenarios);
        $summary['fullRunFailedStatuses'] = array_map(static fn (array $scenario): string => $scenario['failed_plan']['status'], $scenarios);
        $summary['fullRunRetryWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['retry_plan']['wal_frame_count_after'], $scenarios);
        $summary['fullRunFollowupWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['followup_plan']['wal_frame_count_after'], $scenarios);
        $summary['fullRunFollowupPages'] = array_map(static fn (array $scenario): array => array_column($scenario['followup_plan']['import_plan']['applied'], 'page_number'), $scenarios);
        $summary['fullRunFollowupKeys'] = array_map(static fn (array $scenario): string => $scenario['followup_plan']['import_plan']['applied'][1]['key_name'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicCommittedPrefixFailureScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['committedPrefixFailureScenarioCount'] = count($scenarios);
        $summary['committedPrefixFailureStatuses'] = array_map(static fn (array $scenario): string => $scenario['tail_plan']['status'], $scenarios);
        $summary['committedPrefixFailureWalFramesBefore'] = array_map(static fn (array $scenario): int => $scenario['tail_plan']['wal_frame_count_before'], $scenarios);
        $summary['committedPrefixFailureWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['tail_plan']['wal_frame_count_after'], $scenarios);
        $summary['committedPrefixFailureTailPages'] = array_map(static fn (array $scenario): array => $scenario['tail_plan']['rollback_to_savepoint']['restored_page_numbers'], $scenarios);
        $summary['committedPrefixFailureFailedStatements'] = array_map(static fn (array $scenario): array => $scenario['tail_plan']['failed_statements'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledMaterializedWalScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['rollbackDisabledMaterializedWalScenarioCount'] = count($scenarios);
        $summary['rollbackDisabledMaterializedWalStatuses'] = array_map(static fn (array $scenario): string => $scenario['plan']['status'], $scenarios);
        $summary['rollbackDisabledMaterializedWalFramesBefore'] = array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_before'], $scenarios);
        $summary['rollbackDisabledMaterializedWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $scenarios);
        $summary['rollbackDisabledMaterializedWalAppliedPages'] = array_map(static fn (array $scenario): array => array_column($scenario['plan']['import_plan']['applied'], 'page_number'), $scenarios);
        $summary['rollbackDisabledMaterializedWalFailedStatements'] = array_map(static fn (array $scenario): array => $scenario['plan']['failed_statements'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledReopenedPrefixSuccessScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['rollbackDisabledReopenedPrefixSuccessScenarioCount'] = count($scenarios);
        $summary['rollbackDisabledReopenedPrefixSuccessStatuses'] = array_map(static fn (array $scenario): string => $scenario['reopened_success_plan']['status'], $scenarios);
        $summary['rollbackDisabledReopenedPrefixSuccessWalFramesBefore'] = array_map(static fn (array $scenario): int => $scenario['reopened_success_plan']['wal_frame_count_before'], $scenarios);
        $summary['rollbackDisabledReopenedPrefixSuccessWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['reopened_success_plan']['wal_frame_count_after'], $scenarios);
        $summary['rollbackDisabledReopenedPrefixSuccessMaterializedFrames'] = array_map(static fn (array $scenario): int => $scenario['reopened_success_plan']['materialized_wal_frame_count'], $scenarios);
        $summary['rollbackDisabledReopenedPrefixSuccessPages'] = array_map(static fn (array $scenario): array => array_column($scenario['reopened_success_plan']['import_plan']['applied'], 'page_number'), $scenarios);
        $summary['rollbackDisabledReopenedPrefixSuccessInsertedKeys'] = array_map(static fn (array $scenario): string => $scenario['reopened_success_plan']['import_plan']['applied'][1]['key_name'], $scenarios);
        $summary['rollbackDisabledReopenedPrefixSuccessPreviousRecoveryKeysRetained'] = array_map(
            static fn (array $scenario): bool => in_array($scenario['expected_previous_recovery_inserted_key'], array_column($scenario['reopened_success_plan']['import_plan']['final_rows'], 'key_name'), true),
            $scenarios
        );
        $summary['rollbackDisabledReopenedPrefixSuccessPriorTailKeysRetained'] = array_map(
            static fn (array $scenario): bool => in_array($scenario['rejected_prior_tail_inserted_key'], array_column($scenario['reopened_success_plan']['import_plan']['final_rows'], 'key_name'), true),
            $scenarios
        );
        $summary['rollbackDisabledReopenedPrefixSuccessPostTailKeysRetained'] = array_map(
            static fn (array $scenario): bool => in_array($scenario['rejected_post_recovery_tail_inserted_key'], array_column($scenario['reopened_success_plan']['import_plan']['final_rows'], 'key_name'), true),
            $scenarios
        );
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['rollbackDisabledFollowupScenarioCount'] = count($scenarios);
        $summary['rollbackDisabledFollowupStatuses'] = array_map(static fn (array $scenario): string => $scenario['followup_plan']['status'], $scenarios);
        $summary['rollbackDisabledFollowupWalFramesBefore'] = array_map(static fn (array $scenario): int => $scenario['followup_plan']['wal_frame_count_before'], $scenarios);
        $summary['rollbackDisabledFollowupWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['followup_plan']['wal_frame_count_after'], $scenarios);
        $summary['rollbackDisabledFollowupPages'] = array_map(static fn (array $scenario): array => array_column($scenario['followup_plan']['import_plan']['applied'], 'page_number'), $scenarios);
        $summary['rollbackDisabledFollowupInsertedKeys'] = array_map(static fn (array $scenario): string => $scenario['followup_plan']['import_plan']['applied'][1]['key_name'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupFailureScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['rollbackDisabledFollowupFailureScenarioCount'] = count($scenarios);
        $summary['rollbackDisabledFollowupFailureStatuses'] = array_map(static fn (array $scenario): string => $scenario['tail_plan']['status'], $scenarios);
        $summary['rollbackDisabledFollowupFailureWalFramesBefore'] = array_map(static fn (array $scenario): int => $scenario['tail_plan']['wal_frame_count_before'], $scenarios);
        $summary['rollbackDisabledFollowupFailureWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['tail_plan']['wal_frame_count_after'], $scenarios);
        $summary['rollbackDisabledFollowupFailureTailPages'] = array_map(static fn (array $scenario): array => $scenario['tail_plan']['rollback_to_savepoint']['restored_page_numbers'], $scenarios);
        $summary['rollbackDisabledFollowupFailureFailedStatements'] = array_map(static fn (array $scenario): array => $scenario['tail_plan']['failed_statements'], $scenarios);
        $summary['rollbackDisabledFollowupFailureInsertedKeys'] = array_map(static fn (array $scenario): string => $scenario['tail_plan']['import_plan']['applied'][1]['key_name'], $scenarios);
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupRecoveryScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['rollbackDisabledFollowupRecoveryScenarioCount'] = count($scenarios);
        $summary['rollbackDisabledFollowupRecoveryStatuses'] = array_map(static fn (array $scenario): string => $scenario['recovery_plan']['status'], $scenarios);
        $summary['rollbackDisabledFollowupRecoveryWalFramesBefore'] = array_map(static fn (array $scenario): int => $scenario['recovery_plan']['wal_frame_count_before'], $scenarios);
        $summary['rollbackDisabledFollowupRecoveryWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['recovery_plan']['wal_frame_count_after'], $scenarios);
        $summary['rollbackDisabledFollowupRecoveryPages'] = array_map(static fn (array $scenario): array => array_column($scenario['recovery_plan']['import_plan']['applied'], 'page_number'), $scenarios);
        $summary['rollbackDisabledFollowupRecoveryInsertedKeys'] = array_map(static fn (array $scenario): string => $scenario['recovery_plan']['import_plan']['applied'][1]['key_name'], $scenarios);
        $summary['rollbackDisabledFollowupRecoveryRejectedTailKeysRetained'] = array_map(
        static fn (array $scenario): bool => in_array($scenario['rejected_tail_inserted_key'], array_column($scenario['recovery_plan']['import_plan']['final_rows'], 'key_name'), true),
            $scenarios
        );
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledPostRecoveryFailureScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['rollbackDisabledPostRecoveryFailureScenarioCount'] = count($scenarios);
        $summary['rollbackDisabledPostRecoveryFailureStatuses'] = array_map(static fn (array $scenario): string => $scenario['post_recovery_failure_plan']['status'], $scenarios);
        $summary['rollbackDisabledPostRecoveryFailureWalFramesBefore'] = array_map(static fn (array $scenario): int => $scenario['post_recovery_failure_plan']['wal_frame_count_before'], $scenarios);
        $summary['rollbackDisabledPostRecoveryFailureWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['post_recovery_failure_plan']['wal_frame_count_after'], $scenarios);
        $summary['rollbackDisabledPostRecoveryFailureTailPages'] = array_map(static fn (array $scenario): array => $scenario['post_recovery_failure_plan']['rollback_to_savepoint']['restored_page_numbers'], $scenarios);
        $summary['rollbackDisabledPostRecoveryFailureFailedStatements'] = array_map(static fn (array $scenario): array => $scenario['post_recovery_failure_plan']['failed_statements'], $scenarios);
        $summary['rollbackDisabledPostRecoveryFailureInsertedKeys'] = array_map(static fn (array $scenario): string => $scenario['post_recovery_failure_plan']['import_plan']['applied'][1]['key_name'], $scenarios);
        $summary['rollbackDisabledPostRecoveryFailurePriorTailKeysRetained'] = array_map(
        static fn (array $scenario): bool => in_array($scenario['rejected_prior_tail_inserted_key'], array_column($scenario['post_recovery_failure_plan']['import_plan']['final_rows'], 'key_name'), true),
            $scenarios
        );
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledPostRecoveryRecoveryScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['rollbackDisabledPostRecoveryRecoveryScenarioCount'] = count($scenarios);
        $summary['rollbackDisabledPostRecoveryRecoveryStatuses'] = array_map(static fn (array $scenario): string => $scenario['post_recovery_recovery_plan']['status'], $scenarios);
        $summary['rollbackDisabledPostRecoveryRecoveryWalFramesBefore'] = array_map(static fn (array $scenario): int => $scenario['post_recovery_recovery_plan']['wal_frame_count_before'], $scenarios);
        $summary['rollbackDisabledPostRecoveryRecoveryWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['post_recovery_recovery_plan']['wal_frame_count_after'], $scenarios);
        $summary['rollbackDisabledPostRecoveryRecoveryPages'] = array_map(static fn (array $scenario): array => array_column($scenario['post_recovery_recovery_plan']['import_plan']['applied'], 'page_number'), $scenarios);
        $summary['rollbackDisabledPostRecoveryRecoveryInsertedKeys'] = array_map(static fn (array $scenario): string => $scenario['post_recovery_recovery_plan']['import_plan']['applied'][1]['key_name'], $scenarios);
        $summary['rollbackDisabledPostRecoveryRecoveryPriorTailKeysRetained'] = array_map(
            static fn (array $scenario): bool => in_array($scenario['rejected_prior_tail_inserted_key'], array_column($scenario['post_recovery_recovery_plan']['import_plan']['final_rows'], 'key_name'), true),
            $scenarios
        );
        $summary['rollbackDisabledPostRecoveryRecoveryPostTailKeysRetained'] = array_map(
            static fn (array $scenario): bool => in_array($scenario['rejected_post_recovery_tail_inserted_key'], array_column($scenario['post_recovery_recovery_plan']['import_plan']['final_rows'], 'key_name'), true),
            $scenarios
        );
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicPostRecoveryCheckpointScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['postRecoveryCheckpointScenarioCount'] = count($scenarios);
        $summary['postRecoveryCheckpointModes'] = array_map(static fn (array $scenario): string => $scenario['checkpoint_mode'], $scenarios);
        $summary['postRecoveryCheckpointActions'] = array_map(static fn (array $scenario): string => $scenario['released_checkpoint']['wal_action'], $scenarios);
        $summary['postRecoveryCheckpointReleasedWalBytes'] = array_map(static fn (array $scenario): int => $scenario['released_checkpoint']['wal_bytes_length'], $scenarios);
        $summary['postRecoveryCheckpointPinnedBusy'] = array_map(static fn (array $scenario): bool => $scenario['pinned_checkpoint']['busy'], $scenarios);
        $summary['postRecoveryCheckpointAppliedPages'] = array_map(static fn (array $scenario): array => $scenario['expected_recovery_pages'], $scenarios);
        $summary['postRecoveryCheckpointPagesMaterialized'] = array_map(static fn (array $scenario): bool => $scenario['expected_recovery_pages_checkpointed'], $scenarios);
        $summary['postRecoveryCheckpointRejectedKeysRetained'] = array_map(
            static fn (array $scenario): bool => $scenario['rejected_prior_tail_key_retained'] || $scenario['rejected_post_recovery_tail_key_retained'],
            $scenarios
        );
    }
);

$withScenarios(
    static fn (): array => SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointFollowupScenarios(4),
    static function (array $scenarios) use (&$summary): void {
        $summary['postCheckpointFollowupScenarioCount'] = count($scenarios);
        $summary['postCheckpointFollowupModes'] = array_map(static fn (array $scenario): string => $scenario['checkpoint_mode'], $scenarios);
        $summary['postCheckpointFollowupStartedNewWalHeaders'] = array_map(static fn (array $scenario): bool => $scenario['post_checkpoint_started_new_wal_header'], $scenarios);
        $summary['postCheckpointFollowupWalFramesBefore'] = array_map(static fn (array $scenario): int => $scenario['post_checkpoint_followup_plan']['wal_frame_count_before'], $scenarios);
        $summary['postCheckpointFollowupWalFramesAfter'] = array_map(static fn (array $scenario): int => $scenario['post_checkpoint_followup_plan']['wal_frame_count_after'], $scenarios);
        $summary['postCheckpointFollowupPages'] = array_map(static fn (array $scenario): array => array_column($scenario['post_checkpoint_followup_plan']['import_plan']['applied'], 'page_number'), $scenarios);
        $summary['postCheckpointFollowupInsertedKeys'] = array_map(static fn (array $scenario): string => $scenario['post_checkpoint_followup_plan']['import_plan']['applied'][1]['key_name'], $scenarios);
        $summary['postCheckpointFollowupRejectedKeysRetained'] = array_map(
            static fn (array $scenario): bool => $scenario['rejected_prior_tail_key_retained_after_followup'] || $scenario['rejected_post_recovery_tail_key_retained_after_followup'],
            $scenarios
        );
    }
);

if (in_array('--self-test', $argv, true)) {
    assert($summary['scenarioCount'] === 4);
    assert($summary['preexistingWalScenarioCount'] === 4);
    assert($summary['tenantCollisionScenarioCount'] === 4);
    assert($summary['insertedSettingScenarioCount'] === 4);
    assert($summary['duplicateInsertedSettingScenarioCount'] === 4);
    assert($summary['malformedInsertedInitialValueScenarioCount'] === 4);
    assert($summary['deferredScenarioCount'] === 4);
    assert($summary['preexistingRetryScenarioCount'] === 4);
    assert($summary['missingWalTailScenarioCount'] === 4);
    assert($summary['partialWalTailScenarioCount'] === 4);
    assert($summary['frameHeaderMismatchScenarioCount'] === 4);
    assert($summary['frameChecksumMismatchScenarioCount'] === 4);
    assert($summary['headerChecksumMismatchScenarioCount'] === 4);
    assert($summary['fullRunMaterializedWalScenarioCount'] === 4);
    assert($summary['committedPrefixFailureScenarioCount'] === 4);
    assert($summary['rollbackDisabledMaterializedWalScenarioCount'] === 4);
    assert($summary['rollbackDisabledReopenedPrefixSuccessScenarioCount'] === 4);
    assert($summary['rollbackDisabledFollowupScenarioCount'] === 4);
    assert($summary['rollbackDisabledFollowupFailureScenarioCount'] === 4);
    assert($summary['rollbackDisabledFollowupRecoveryScenarioCount'] === 4);
    assert($summary['rollbackDisabledPostRecoveryFailureScenarioCount'] === 4);
    assert($summary['rollbackDisabledPostRecoveryRecoveryScenarioCount'] === 4);
    assert($summary['postRecoveryCheckpointScenarioCount'] === 4);
    assert($summary['postCheckpointFollowupScenarioCount'] === 4);
    assert($summary['statuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['preexistingWalStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['tenantCollisionStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['insertedSettingStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['duplicateInsertedSettingStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['malformedInsertedInitialValueStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['deferredStatuses'] === array_fill(0, 4, 'partial_rollback'));
    assert($summary['preexistingRetryStatuses'] === array_fill(0, 4, 'ready'));
    assert($summary['walFramesAfter'] === array_fill(0, 4, 0));
    assert($summary['preexistingWalFramesAfter'] === [3, 4, 5, 2]);
    assert($summary['preexistingWalTruncateBytes'][0] === 1640);
    assert($summary['tenantCollisionStablePages'] === [81, 82, 83, 84]);
    assert($summary['tenantCollisionRestoredPages'][0] === [19]);
    assert($summary['insertedSettingIds'][0] === [5003, 5004]);
    assert($summary['insertedSettingRestoredPages'][0] === [25, 181, 231]);
    assert($summary['insertedSettingWalFramesAfter'] === array_fill(0, 4, 0));
    assert($summary['duplicateInsertedSettingIds'] === [6002, 12002, 18002, 24002]);
    assert($summary['duplicateInsertedSettingRestoredPages'][0] === [35]);
    assert($summary['duplicateInsertedSettingErrors'][0] === 'SQLite Application JSON import inserted setting_id already exists: 6002');
    assert($summary['malformedInsertedInitialValueIds'] === [7002, 14002, 21002, 28002]);
    assert($summary['malformedInsertedInitialValueStatementRestoredPages'][0] === [431]);
    assert($summary['malformedInsertedInitialValueFailedFrames'] === [[2], [2], [2], [2]]);
    assert($summary['deferredWalFramesAfter'] === [5, 6, 7, 8]);
    assert($summary['preexistingRetryFailedWalFramesAfter'] === [2, 3, 4, 5]);
    assert($summary['preexistingRetryWalFramesAfter'] === [2, 3, 4, 5]);
    assert($summary['preexistingRetryMaterializedChecksumPairs'][0] !== [0, 0]);
    assert($summary['preexistingRetryMaterializedCommitMarkers'][0] === [0, 0, 391]);
    assert($summary['fullRunFailedStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['fullRunRetryWalFramesAfter'] === [5, 6, 7, 4]);
    assert($summary['fullRunFollowupWalFramesAfter'] === [7, 8, 9, 6]);
    assert($summary['fullRunFollowupPages'][0] === [721, 1021]);
    assert($summary['fullRunFollowupKeys'][0] === 'full_run_final_payload_1');
    assert($summary['committedPrefixFailureStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['committedPrefixFailureWalFramesBefore'] === [8, 9, 10, 7]);
    assert($summary['committedPrefixFailureWalFramesAfter'] === [5, 6, 7, 4]);
    assert($summary['committedPrefixFailureTailPages'][0] === [721, 1221]);
    assert($summary['committedPrefixFailureFailedStatements'][0] === ['committed_prefix_malformed_tail_1']);
    assert($summary['rollbackDisabledMaterializedWalStatuses'] === array_fill(0, 4, 'partial_rollback'));
    assert($summary['rollbackDisabledMaterializedWalFramesBefore'] === [2, 3, 4, 1]);
    assert($summary['rollbackDisabledMaterializedWalFramesAfter'] === [4, 5, 6, 3]);
    assert($summary['rollbackDisabledMaterializedWalAppliedPages'][0] === [63, 1321]);
    assert($summary['rollbackDisabledMaterializedWalFailedStatements'][0] === ['disabled_rollback_broken_payload_1']);
    assert($summary['rollbackDisabledReopenedPrefixSuccessStatuses'] === array_fill(0, 4, 'ready'));
    assert($summary['rollbackDisabledReopenedPrefixSuccessWalFramesBefore'] === [10, 11, 12, 9]);
    assert($summary['rollbackDisabledReopenedPrefixSuccessWalFramesAfter'] === [13, 14, 15, 12]);
    assert($summary['rollbackDisabledReopenedPrefixSuccessMaterializedFrames'] === array_fill(0, 4, 3));
    assert($summary['rollbackDisabledReopenedPrefixSuccessPages'][0] === [1321, 2121, 1921]);
    assert($summary['rollbackDisabledReopenedPrefixSuccessInsertedKeys'][0] === 'disabled_reopened_prefix_success_payload_1');
    assert($summary['rollbackDisabledReopenedPrefixSuccessPreviousRecoveryKeysRetained'] === array_fill(0, 4, true));
    assert($summary['rollbackDisabledReopenedPrefixSuccessPriorTailKeysRetained'] === array_fill(0, 4, false));
    assert($summary['rollbackDisabledReopenedPrefixSuccessPostTailKeysRetained'] === array_fill(0, 4, false));
    assert($summary['rollbackDisabledFollowupStatuses'] === array_fill(0, 4, 'ready'));
    assert($summary['rollbackDisabledFollowupWalFramesBefore'] === [4, 5, 6, 3]);
    assert($summary['rollbackDisabledFollowupWalFramesAfter'] === [6, 7, 8, 5]);
    assert($summary['rollbackDisabledFollowupPages'][0] === [1321, 1521]);
    assert($summary['rollbackDisabledFollowupInsertedKeys'][0] === 'disabled_rollback_followup_payload_1');
    assert($summary['rollbackDisabledFollowupFailureStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['rollbackDisabledFollowupFailureWalFramesBefore'] === [9, 10, 11, 8]);
    assert($summary['rollbackDisabledFollowupFailureWalFramesAfter'] === [6, 7, 8, 5]);
    assert($summary['rollbackDisabledFollowupFailureTailPages'][0] === [1321, 1621]);
    assert($summary['rollbackDisabledFollowupFailureFailedStatements'][0] === ['disabled_followup_tail_broken_payload_1']);
    assert($summary['rollbackDisabledFollowupFailureInsertedKeys'][0] === 'disabled_followup_tail_payload_1');
    assert($summary['rollbackDisabledFollowupRecoveryStatuses'] === array_fill(0, 4, 'ready'));
    assert($summary['rollbackDisabledFollowupRecoveryWalFramesBefore'] === [6, 7, 8, 5]);
    assert($summary['rollbackDisabledFollowupRecoveryWalFramesAfter'] === [8, 9, 10, 7]);
    assert($summary['rollbackDisabledFollowupRecoveryPages'][0] === [1321, 1721]);
    assert($summary['rollbackDisabledFollowupRecoveryInsertedKeys'][0] === 'disabled_followup_recovery_payload_1');
    assert($summary['rollbackDisabledFollowupRecoveryRejectedTailKeysRetained'] === array_fill(0, 4, false));
    assert($summary['rollbackDisabledPostRecoveryFailureStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['rollbackDisabledPostRecoveryFailureWalFramesBefore'] === [11, 12, 13, 10]);
    assert($summary['rollbackDisabledPostRecoveryFailureWalFramesAfter'] === [8, 9, 10, 7]);
    assert($summary['rollbackDisabledPostRecoveryFailureTailPages'][0] === [1321, 1821]);
    assert($summary['rollbackDisabledPostRecoveryFailureFailedStatements'][0] === ['disabled_post_recovery_tail_broken_payload_1']);
    assert($summary['rollbackDisabledPostRecoveryFailureInsertedKeys'][0] === 'disabled_post_recovery_tail_payload_1');
    assert($summary['rollbackDisabledPostRecoveryFailurePriorTailKeysRetained'] === array_fill(0, 4, false));
    assert($summary['rollbackDisabledPostRecoveryRecoveryStatuses'] === array_fill(0, 4, 'ready'));
    assert($summary['rollbackDisabledPostRecoveryRecoveryWalFramesBefore'] === [8, 9, 10, 7]);
    assert($summary['rollbackDisabledPostRecoveryRecoveryWalFramesAfter'] === [10, 11, 12, 9]);
    assert($summary['rollbackDisabledPostRecoveryRecoveryPages'][0] === [1321, 1921]);
    assert($summary['rollbackDisabledPostRecoveryRecoveryInsertedKeys'][0] === 'disabled_post_recovery_recovery_payload_1');
    assert($summary['rollbackDisabledPostRecoveryRecoveryPriorTailKeysRetained'] === array_fill(0, 4, false));
    assert($summary['rollbackDisabledPostRecoveryRecoveryPostTailKeysRetained'] === array_fill(0, 4, false));
    assert($summary['postRecoveryCheckpointModes'] === ['restart', 'truncate', 'restart', 'truncate']);
    assert($summary['postRecoveryCheckpointActions'] === ['restart_wal', 'truncate_wal', 'restart_wal', 'truncate_wal']);
    assert($summary['postRecoveryCheckpointReleasedWalBytes'] === [32, 0, 32, 0]);
    assert($summary['postRecoveryCheckpointPinnedBusy'] === array_fill(0, 4, true));
    assert($summary['postRecoveryCheckpointAppliedPages'][0] === [1321, 1921]);
    assert($summary['postRecoveryCheckpointPagesMaterialized'] === array_fill(0, 4, true));
    assert($summary['postRecoveryCheckpointRejectedKeysRetained'] === array_fill(0, 4, false));
    assert($summary['postCheckpointFollowupModes'] === ['restart', 'truncate', 'restart', 'truncate']);
    assert($summary['postCheckpointFollowupStartedNewWalHeaders'] === [false, true, false, true]);
    assert($summary['postCheckpointFollowupWalFramesBefore'] === array_fill(0, 4, 0));
    assert($summary['postCheckpointFollowupWalFramesAfter'] === array_fill(0, 4, 2));
    assert($summary['postCheckpointFollowupPages'][0] === [1321, 2021]);
    assert($summary['postCheckpointFollowupInsertedKeys'][0] === 'post_checkpoint_payload_1');
    assert($summary['postCheckpointFollowupRejectedKeysRetained'] === array_fill(0, 4, false));
    assert($summary['missingWalTailShortFrameCounts'] === [4, 6, 6, 4]);
    assert($summary['missingWalTailMessages'][0] === 'SQLite Application JSON import rollback WAL bytes are missing current batch frame(s): 5, 6');
    assert($summary['partialWalTailMessages'] === array_fill(0, 4, 'SQLite Application JSON import rollback WAL bytes have a partial frame tail'));
    assert($summary['partialWalTailCompleteFrameCounts'] === [4, 5, 6, 3]);
    assert($summary['partialWalTailPayloadBytes'] === [38, 75, 112, 149]);
    assert($summary['frameHeaderMismatchTargetFrames'] === [4, 5, 6, 3]);
    assert($summary['frameHeaderMismatchCorruptions'] === ['salt_mismatch', 'zero_page', 'salt_mismatch', 'zero_page']);
    assert($summary['frameHeaderMismatchMessages'][0] === 'SQLite Application JSON import rollback WAL frame 4 salt does not match the WAL header');
    assert($summary['frameHeaderMismatchMessages'][1] === 'SQLite Application JSON import rollback WAL frame 5 has an invalid page number');
    assert($summary['frameChecksumMismatchTargetFrames'] === [4, 5, 6, 3]);
    assert($summary['frameChecksumMismatchMessages'][0] === 'SQLite Application JSON import rollback WAL frame 4 checksum does not match the frame payload');
    assert($summary['frameChecksumMismatchMessages'][1] === 'SQLite Application JSON import rollback WAL frame 5 checksum does not match the frame payload');
    assert($summary['frameChecksumMismatchOffsets'][0] === 1664);
    assert($summary['headerChecksumMismatchMessages'] === array_fill(0, 4, 'SQLite Application JSON import rollback WAL header checksum does not match the header content'));
    assert($summary['headerChecksumMismatchOffsets'] === [28, 24, 28, 24]);
    assert($summary['restoredPages'][0] === [3, 11]);
    fwrite(STDOUT, "application-wal-rollback-json-dynamic-parity self-test passed\n");
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
