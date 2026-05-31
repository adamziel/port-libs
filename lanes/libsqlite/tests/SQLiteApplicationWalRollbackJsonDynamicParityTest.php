<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;

$scenarios = SQLiteJsonImportRollbackWalPlan::dynamicParityScenarios(24);
$preexistingWalScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalScenarios(24);
$tenantCollisionScenarios = SQLiteJsonImportRollbackWalPlan::dynamicTenantCollisionScenarios(24);
$insertedSettingScenarios = SQLiteJsonImportRollbackWalPlan::dynamicInsertedSettingRollbackScenarios(24);
$duplicateInsertedSettingScenarios = SQLiteJsonImportRollbackWalPlan::dynamicDuplicateInsertedSettingRollbackScenarios(24);
$malformedInsertedInitialValueScenarios = SQLiteJsonImportRollbackWalPlan::dynamicMalformedInsertedInitialValueScenarios(24);
$deferredScenarios = SQLiteJsonImportRollbackWalPlan::dynamicDeferredFailureScenarios(24);
$retryScenarios = SQLiteJsonImportRollbackWalPlan::dynamicRetryAfterRollbackScenarios(18);
$preexistingRetryScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalRetryScenarios(18);
$missingWalTailScenarios = SQLiteJsonImportRollbackWalPlan::dynamicMissingWalTailScenarios(18);
$partialWalTailScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPartialWalTailScenarios(18);
$frameHeaderMismatchScenarios = SQLiteJsonImportRollbackWalPlan::dynamicFrameHeaderMismatchScenarios(18);
$frameChecksumMismatchScenarios = SQLiteJsonImportRollbackWalPlan::dynamicFrameChecksumMismatchScenarios(18);
$headerChecksumMismatchScenarios = SQLiteJsonImportRollbackWalPlan::dynamicHeaderChecksumMismatchScenarios(18);
$successfulMaterializedWalScenarios = SQLiteJsonImportRollbackWalPlan::dynamicSuccessfulMaterializedWalScenarios(24);
$fullRunMaterializedWalScenarios = SQLiteJsonImportRollbackWalPlan::dynamicFullRunMaterializedWalScenarios(18);
$committedPrefixFailureScenarios = SQLiteJsonImportRollbackWalPlan::dynamicCommittedPrefixFailureScenarios(18);
$rollbackDisabledMaterializedWalScenarios = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledMaterializedWalScenarios(18);
$rollbackDisabledFollowupScenarios = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupScenarios(18);
$rollbackDisabledFollowupFailureScenarios = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupFailureScenarios(18);
$rollbackDisabledFollowupRecoveryScenarios = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupRecoveryScenarios(18);
$rollbackDisabledPostRecoveryFailureScenarios = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledPostRecoveryFailureScenarios(18);
$rollbackDisabledPostRecoveryRecoveryScenarios = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledPostRecoveryRecoveryScenarios(18);
$postRecoveryCheckpointScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostRecoveryCheckpointScenariosFromRecoveryScenarios($rollbackDisabledPostRecoveryRecoveryScenarios);
$postCheckpointFollowupScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointFollowupScenariosFromCheckpointScenarios($postRecoveryCheckpointScenarios);

$tests = [
    'sqlite application wal rollback json dynamic parity exposes requested scenario count' => static function (TestRunner $t) use ($scenarios): void {
        $t->same(24, count($scenarios));
    },
    'sqlite application wal rollback json dynamic parity covers both page sizes' => static function (TestRunner $t) use ($scenarios): void {
        $pageSizes = array_values(array_unique(array_column($scenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity covers json text and jsonb rows' => static function (TestRunner $t) use ($scenarios): void {
        $t->same([false, true], array_values(array_unique(array_column($scenarios, 'jsonb_mode'))));
    },
    'sqlite application wal rollback json dynamic parity has unique tenant streams' => static function (TestRunner $t) use ($scenarios): void {
        $tenantIds = array_column($scenarios, 'tenant_id');
        $t->same(count($tenantIds), count(array_unique($tenantIds)));
    },
    'sqlite application wal rollback json dynamic parity deferred mode exposes requested scenario count' => static function (TestRunner $t) use ($deferredScenarios): void {
        $t->same(24, count($deferredScenarios));
    },
    'sqlite application wal rollback json dynamic parity preexisting WAL exposes requested scenario count' => static function (TestRunner $t) use ($preexistingWalScenarios): void {
        $t->same(24, count($preexistingWalScenarios));
    },
    'sqlite application wal rollback json dynamic parity preexisting WAL covers prefix frame counts' => static function (TestRunner $t) use ($preexistingWalScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($preexistingWalScenarios, 'preexisting_frames')));
        sort($prefixCounts);
        $t->same([2, 3, 4, 5], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity preexisting WAL covers json text and jsonb rows' => static function (TestRunner $t) use ($preexistingWalScenarios): void {
        $jsonModes = array_values(array_unique(array_column($preexistingWalScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity tenant collision exposes requested scenario count' => static function (TestRunner $t) use ($tenantCollisionScenarios): void {
        $t->same(24, count($tenantCollisionScenarios));
    },
    'sqlite application wal rollback json dynamic parity tenant collision covers both page sizes' => static function (TestRunner $t) use ($tenantCollisionScenarios): void {
        $pageSizes = array_values(array_unique(array_column($tenantCollisionScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity tenant collision covers json text and jsonb rows' => static function (TestRunner $t) use ($tenantCollisionScenarios): void {
        $jsonModes = array_values(array_unique(array_column($tenantCollisionScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity inserted setting exposes requested scenario count' => static function (TestRunner $t) use ($insertedSettingScenarios): void {
        $t->same(24, count($insertedSettingScenarios));
    },
    'sqlite application wal rollback json dynamic parity inserted setting covers both page sizes' => static function (TestRunner $t) use ($insertedSettingScenarios): void {
        $pageSizes = array_values(array_unique(array_column($insertedSettingScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity inserted setting covers json text and jsonb rows' => static function (TestRunner $t) use ($insertedSettingScenarios): void {
        $jsonModes = array_values(array_unique(array_column($insertedSettingScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity duplicate inserted setting exposes requested scenario count' => static function (TestRunner $t) use ($duplicateInsertedSettingScenarios): void {
        $t->same(24, count($duplicateInsertedSettingScenarios));
    },
    'sqlite application wal rollback json dynamic parity duplicate inserted setting covers both page sizes' => static function (TestRunner $t) use ($duplicateInsertedSettingScenarios): void {
        $pageSizes = array_values(array_unique(array_column($duplicateInsertedSettingScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity duplicate inserted setting has unique tenant streams' => static function (TestRunner $t) use ($duplicateInsertedSettingScenarios): void {
        $tenantIds = array_column($duplicateInsertedSettingScenarios, 'tenant_id');
        $t->same(count($tenantIds), count(array_unique($tenantIds)));
    },
    'sqlite application wal rollback json dynamic parity malformed inserted initial value exposes requested scenario count' => static function (TestRunner $t) use ($malformedInsertedInitialValueScenarios): void {
        $t->same(24, count($malformedInsertedInitialValueScenarios));
    },
    'sqlite application wal rollback json dynamic parity malformed inserted initial value covers both page sizes' => static function (TestRunner $t) use ($malformedInsertedInitialValueScenarios): void {
        $pageSizes = array_values(array_unique(array_column($malformedInsertedInitialValueScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity malformed inserted initial value covers json text and jsonb functions' => static function (TestRunner $t) use ($malformedInsertedInitialValueScenarios): void {
        $jsonModes = array_values(array_unique(array_column($malformedInsertedInitialValueScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity deferred mode covers json text and jsonb rows' => static function (TestRunner $t) use ($deferredScenarios): void {
        $t->same([false, true], array_values(array_unique(array_column($deferredScenarios, 'jsonb_mode'))));
    },
    'sqlite application wal rollback json dynamic parity retry mode exposes requested scenario count' => static function (TestRunner $t) use ($retryScenarios): void {
        $t->same(18, count($retryScenarios));
    },
    'sqlite application wal rollback json dynamic parity retry mode covers both page sizes' => static function (TestRunner $t) use ($retryScenarios): void {
        $pageSizes = array_values(array_unique(array_column($retryScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity retry mode covers json text and jsonb rows' => static function (TestRunner $t) use ($retryScenarios): void {
        $t->same([false, true], array_values(array_unique(array_column($retryScenarios, 'jsonb_mode'))));
    },
    'sqlite application wal rollback json dynamic parity preexisting retry mode exposes requested scenario count' => static function (TestRunner $t) use ($preexistingRetryScenarios): void {
        $t->same(18, count($preexistingRetryScenarios));
    },
    'sqlite application wal rollback json dynamic parity preexisting retry mode covers prefix frame counts' => static function (TestRunner $t) use ($preexistingRetryScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($preexistingRetryScenarios, 'preexisting_frames')));
        sort($prefixCounts);
        $t->same([1, 2, 3, 4, 5], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity preexisting retry mode covers json text and jsonb rows' => static function (TestRunner $t) use ($preexistingRetryScenarios): void {
        $jsonModes = array_values(array_unique(array_column($preexistingRetryScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity missing WAL tail exposes requested scenario count' => static function (TestRunner $t) use ($missingWalTailScenarios): void {
        $t->same(18, count($missingWalTailScenarios));
    },
    'sqlite application wal rollback json dynamic parity missing WAL tail covers one and two missing frames' => static function (TestRunner $t) use ($missingWalTailScenarios): void {
        $missingFrames = array_values(array_unique(array_column($missingWalTailScenarios, 'missing_frames')));
        sort($missingFrames);
        $t->same([1, 2], $missingFrames);
    },
    'sqlite application wal rollback json dynamic parity missing WAL tail covers both page sizes' => static function (TestRunner $t) use ($missingWalTailScenarios): void {
        $pageSizes = array_values(array_unique(array_column($missingWalTailScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity partial WAL tail exposes requested scenario count' => static function (TestRunner $t) use ($partialWalTailScenarios): void {
        $t->same(18, count($partialWalTailScenarios));
    },
    'sqlite application wal rollback json dynamic parity partial WAL tail covers both page sizes' => static function (TestRunner $t) use ($partialWalTailScenarios): void {
        $pageSizes = array_values(array_unique(array_column($partialWalTailScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity partial WAL tail covers varied partial byte counts' => static function (TestRunner $t) use ($partialWalTailScenarios): void {
        $partialByteCounts = array_values(array_unique(array_column($partialWalTailScenarios, 'partial_payload_bytes')));
        $t->same(true, count($partialByteCounts) > 8);
    },
    'sqlite application wal rollback json dynamic parity frame-header mismatch exposes requested scenario count' => static function (TestRunner $t) use ($frameHeaderMismatchScenarios): void {
        $t->same(18, count($frameHeaderMismatchScenarios));
    },
    'sqlite application wal rollback json dynamic parity frame-header mismatch covers both corruption classes' => static function (TestRunner $t) use ($frameHeaderMismatchScenarios): void {
        $corruptions = array_values(array_unique(array_column($frameHeaderMismatchScenarios, 'corruption')));
        sort($corruptions);
        $t->same(['salt_mismatch', 'zero_page'], $corruptions);
    },
    'sqlite application wal rollback json dynamic parity frame-header mismatch covers both page sizes' => static function (TestRunner $t) use ($frameHeaderMismatchScenarios): void {
        $pageSizes = array_values(array_unique(array_column($frameHeaderMismatchScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity frame-checksum mismatch exposes requested scenario count' => static function (TestRunner $t) use ($frameChecksumMismatchScenarios): void {
        $t->same(18, count($frameChecksumMismatchScenarios));
    },
    'sqlite application wal rollback json dynamic parity frame-checksum mismatch covers prefix frame counts' => static function (TestRunner $t) use ($frameChecksumMismatchScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($frameChecksumMismatchScenarios, 'preexisting_frames')));
        sort($prefixCounts);
        $t->same([2, 3, 4, 5], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity frame-checksum mismatch covers both page sizes' => static function (TestRunner $t) use ($frameChecksumMismatchScenarios): void {
        $pageSizes = array_values(array_unique(array_column($frameChecksumMismatchScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity header-checksum mismatch exposes requested scenario count' => static function (TestRunner $t) use ($headerChecksumMismatchScenarios): void {
        $t->same(18, count($headerChecksumMismatchScenarios));
    },
    'sqlite application wal rollback json dynamic parity header-checksum mismatch covers prefix frame counts' => static function (TestRunner $t) use ($headerChecksumMismatchScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($headerChecksumMismatchScenarios, 'preexisting_frames')));
        sort($prefixCounts);
        $t->same([2, 3, 4, 5], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity header-checksum mismatch covers both checksum fields' => static function (TestRunner $t) use ($headerChecksumMismatchScenarios): void {
        $t->same([28, 24], array_values(array_unique(array_column($headerChecksumMismatchScenarios, 'checksum_offset'))));
    },
    'sqlite application wal rollback json dynamic parity successful materialized WAL exposes requested scenario count' => static function (TestRunner $t) use ($successfulMaterializedWalScenarios): void {
        $t->same(24, count($successfulMaterializedWalScenarios));
    },
    'sqlite application wal rollback json dynamic parity successful materialized WAL covers clean and prefix streams' => static function (TestRunner $t) use ($successfulMaterializedWalScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($successfulMaterializedWalScenarios, 'preexisting_frames')));
        sort($prefixCounts);
        $t->same([0, 1, 2, 3], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity successful materialized WAL covers both page sizes' => static function (TestRunner $t) use ($successfulMaterializedWalScenarios): void {
        $pageSizes = array_values(array_unique(array_column($successfulMaterializedWalScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity successful materialized WAL covers json text and jsonb rows' => static function (TestRunner $t) use ($successfulMaterializedWalScenarios): void {
        $jsonModes = array_values(array_unique(array_column($successfulMaterializedWalScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity full-run materialized WAL exposes requested scenario count' => static function (TestRunner $t) use ($fullRunMaterializedWalScenarios): void {
        $t->same(18, count($fullRunMaterializedWalScenarios));
    },
    'sqlite application wal rollback json dynamic parity full-run materialized WAL covers prefix frame counts' => static function (TestRunner $t) use ($fullRunMaterializedWalScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($fullRunMaterializedWalScenarios, 'preexisting_frames')));
        sort($prefixCounts);
        $t->same([1, 2, 3, 4], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity full-run materialized WAL covers both page sizes' => static function (TestRunner $t) use ($fullRunMaterializedWalScenarios): void {
        $pageSizes = array_values(array_unique(array_column($fullRunMaterializedWalScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity full-run materialized WAL covers json text and jsonb rows' => static function (TestRunner $t) use ($fullRunMaterializedWalScenarios): void {
        $jsonModes = array_values(array_unique(array_column($fullRunMaterializedWalScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity committed prefix failure exposes requested scenario count' => static function (TestRunner $t) use ($committedPrefixFailureScenarios): void {
        $t->same(18, count($committedPrefixFailureScenarios));
    },
    'sqlite application wal rollback json dynamic parity committed prefix failure covers prefix frame counts' => static function (TestRunner $t) use ($committedPrefixFailureScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($committedPrefixFailureScenarios, 'preexisting_frames')));
        sort($prefixCounts);
        $t->same([1, 2, 3, 4], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity committed prefix failure covers both page sizes' => static function (TestRunner $t) use ($committedPrefixFailureScenarios): void {
        $pageSizes = array_values(array_unique(array_column($committedPrefixFailureScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity committed prefix failure covers json text and jsonb rows' => static function (TestRunner $t) use ($committedPrefixFailureScenarios): void {
        $jsonModes = array_values(array_unique(array_column($committedPrefixFailureScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled materialized WAL exposes requested scenario count' => static function (TestRunner $t) use ($rollbackDisabledMaterializedWalScenarios): void {
        $t->same(18, count($rollbackDisabledMaterializedWalScenarios));
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled materialized WAL covers prefix frame counts' => static function (TestRunner $t) use ($rollbackDisabledMaterializedWalScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($rollbackDisabledMaterializedWalScenarios, 'preexisting_frames')));
        sort($prefixCounts);
        $t->same([1, 2, 3, 4], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled materialized WAL covers both page sizes' => static function (TestRunner $t) use ($rollbackDisabledMaterializedWalScenarios): void {
        $pageSizes = array_values(array_unique(array_column($rollbackDisabledMaterializedWalScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled materialized WAL covers json text and jsonb rows' => static function (TestRunner $t) use ($rollbackDisabledMaterializedWalScenarios): void {
        $jsonModes = array_values(array_unique(array_column($rollbackDisabledMaterializedWalScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled followup exposes requested scenario count' => static function (TestRunner $t) use ($rollbackDisabledFollowupScenarios): void {
        $t->same(18, count($rollbackDisabledFollowupScenarios));
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled followup covers prefix frame counts' => static function (TestRunner $t) use ($rollbackDisabledFollowupScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($rollbackDisabledFollowupScenarios, 'partial_frame_count')));
        sort($prefixCounts);
        $t->same([3, 4, 5, 6], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled followup covers both page sizes' => static function (TestRunner $t) use ($rollbackDisabledFollowupScenarios): void {
        $pageSizes = array_values(array_unique(array_column($rollbackDisabledFollowupScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled followup covers json text and jsonb rows' => static function (TestRunner $t) use ($rollbackDisabledFollowupScenarios): void {
        $jsonModes = array_values(array_unique(array_column($rollbackDisabledFollowupScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled followup failure exposes requested scenario count' => static function (TestRunner $t) use ($rollbackDisabledFollowupFailureScenarios): void {
        $t->same(18, count($rollbackDisabledFollowupFailureScenarios));
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled followup failure covers committed prefix frame counts' => static function (TestRunner $t) use ($rollbackDisabledFollowupFailureScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($rollbackDisabledFollowupFailureScenarios, 'committed_prefix_frame_count')));
        sort($prefixCounts);
        $t->same([5, 6, 7, 8], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled followup failure covers both page sizes' => static function (TestRunner $t) use ($rollbackDisabledFollowupFailureScenarios): void {
        $pageSizes = array_values(array_unique(array_column($rollbackDisabledFollowupFailureScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled followup failure covers json text and jsonb rows' => static function (TestRunner $t) use ($rollbackDisabledFollowupFailureScenarios): void {
        $jsonModes = array_values(array_unique(array_column($rollbackDisabledFollowupFailureScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled followup recovery exposes requested scenario count' => static function (TestRunner $t) use ($rollbackDisabledFollowupRecoveryScenarios): void {
        $t->same(18, count($rollbackDisabledFollowupRecoveryScenarios));
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled followup recovery covers committed prefix frame counts' => static function (TestRunner $t) use ($rollbackDisabledFollowupRecoveryScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($rollbackDisabledFollowupRecoveryScenarios, 'committed_prefix_frame_count')));
        sort($prefixCounts);
        $t->same([5, 6, 7, 8], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled followup recovery covers both page sizes' => static function (TestRunner $t) use ($rollbackDisabledFollowupRecoveryScenarios): void {
        $pageSizes = array_values(array_unique(array_column($rollbackDisabledFollowupRecoveryScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled followup recovery covers json text and jsonb rows' => static function (TestRunner $t) use ($rollbackDisabledFollowupRecoveryScenarios): void {
        $jsonModes = array_values(array_unique(array_column($rollbackDisabledFollowupRecoveryScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled post-recovery failure exposes requested scenario count' => static function (TestRunner $t) use ($rollbackDisabledPostRecoveryFailureScenarios): void {
        $t->same(18, count($rollbackDisabledPostRecoveryFailureScenarios));
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled post-recovery failure covers committed prefix frame counts' => static function (TestRunner $t) use ($rollbackDisabledPostRecoveryFailureScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($rollbackDisabledPostRecoveryFailureScenarios, 'committed_prefix_frame_count')));
        sort($prefixCounts);
        $t->same([7, 8, 9, 10], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled post-recovery failure covers both page sizes' => static function (TestRunner $t) use ($rollbackDisabledPostRecoveryFailureScenarios): void {
        $pageSizes = array_values(array_unique(array_column($rollbackDisabledPostRecoveryFailureScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled post-recovery failure covers json text and jsonb rows' => static function (TestRunner $t) use ($rollbackDisabledPostRecoveryFailureScenarios): void {
        $jsonModes = array_values(array_unique(array_column($rollbackDisabledPostRecoveryFailureScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled post-recovery recovery exposes requested scenario count' => static function (TestRunner $t) use ($rollbackDisabledPostRecoveryRecoveryScenarios): void {
        $t->same(18, count($rollbackDisabledPostRecoveryRecoveryScenarios));
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled post-recovery recovery covers committed prefix frame counts' => static function (TestRunner $t) use ($rollbackDisabledPostRecoveryRecoveryScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($rollbackDisabledPostRecoveryRecoveryScenarios, 'committed_prefix_frame_count')));
        sort($prefixCounts);
        $t->same([7, 8, 9, 10], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled post-recovery recovery covers both page sizes' => static function (TestRunner $t) use ($rollbackDisabledPostRecoveryRecoveryScenarios): void {
        $pageSizes = array_values(array_unique(array_column($rollbackDisabledPostRecoveryRecoveryScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity rollback-disabled post-recovery recovery covers json text and jsonb rows' => static function (TestRunner $t) use ($rollbackDisabledPostRecoveryRecoveryScenarios): void {
        $jsonModes = array_values(array_unique(array_column($rollbackDisabledPostRecoveryRecoveryScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity post-recovery checkpoint exposes requested scenario count' => static function (TestRunner $t) use ($postRecoveryCheckpointScenarios): void {
        $t->same(18, count($postRecoveryCheckpointScenarios));
    },
    'sqlite application wal rollback json dynamic parity post-recovery checkpoint covers both checkpoint reset modes' => static function (TestRunner $t) use ($postRecoveryCheckpointScenarios): void {
        $modes = array_values(array_unique(array_column($postRecoveryCheckpointScenarios, 'checkpoint_mode')));
        sort($modes);
        $t->same(['restart', 'truncate'], $modes);
    },
    'sqlite application wal rollback json dynamic parity post-recovery checkpoint covers restart and truncate WAL actions' => static function (TestRunner $t) use ($postRecoveryCheckpointScenarios): void {
        $actions = array_values(array_unique(array_column($postRecoveryCheckpointScenarios, 'expected_checkpoint_action')));
        sort($actions);
        $t->same(['restart_wal', 'truncate_wal'], $actions);
    },
    'sqlite application wal rollback json dynamic parity post-recovery checkpoint covers both page sizes' => static function (TestRunner $t) use ($postRecoveryCheckpointScenarios): void {
        $pageSizes = array_values(array_unique(array_column($postRecoveryCheckpointScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity post-recovery checkpoint covers json text and jsonb rows' => static function (TestRunner $t) use ($postRecoveryCheckpointScenarios): void {
        $jsonModes = array_values(array_unique(array_column($postRecoveryCheckpointScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity post-checkpoint followup exposes requested scenario count' => static function (TestRunner $t) use ($postCheckpointFollowupScenarios): void {
        $t->same(18, count($postCheckpointFollowupScenarios));
    },
    'sqlite application wal rollback json dynamic parity post-checkpoint followup covers restart and truncate reset sources' => static function (TestRunner $t) use ($postCheckpointFollowupScenarios): void {
        $modes = array_values(array_unique(array_column($postCheckpointFollowupScenarios, 'checkpoint_mode')));
        sort($modes);
        $t->same(['restart', 'truncate'], $modes);
    },
    'sqlite application wal rollback json dynamic parity post-checkpoint followup creates headers only after truncate' => static function (TestRunner $t) use ($postCheckpointFollowupScenarios): void {
        $started = array_values(array_unique(array_column($postCheckpointFollowupScenarios, 'post_checkpoint_started_new_wal_header')));
        sort($started);
        $t->same([false, true], $started);
    },
    'sqlite application wal rollback json dynamic parity post-checkpoint followup covers both page sizes' => static function (TestRunner $t) use ($postCheckpointFollowupScenarios): void {
        $pageSizes = array_values(array_unique(array_column($postCheckpointFollowupScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity post-checkpoint followup covers json text and jsonb rows' => static function (TestRunner $t) use ($postCheckpointFollowupScenarios): void {
        $jsonModes = array_values(array_unique(array_column($postCheckpointFollowupScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
];

foreach ($rollbackDisabledMaterializedWalScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity rollback disabled materialized wal seed ' . $seed . ' ';

    $tests[$prefix . 'keeps partial rollback status without outer rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same('partial_rollback', $plan['status']);
        $t->same(false, $plan['rollback_required']);
    };
    $tests[$prefix . 'uses rollback-disabled transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_disabled_rollback_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses rollback-disabled savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('disabled_rollback_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'records failed statement but materializes applied rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $plan['failed_statements']);
        $t->same(2, $plan['applied_statement_count']);
        $t->same(1, $plan['failed_statement_count']);
    };
    $tests[$prefix . 'does not restore database to before image'] = static function (TestRunner $t) use ($plan): void {
        $t->same(false, $plan['database_restored_to_before']);
        $t->same(true, $plan['database_changed_before_rollback']);
    };
    $tests[$prefix . 'preserves preexisting WAL prefix'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_bytes'], substr($plan['wal_bytes_after'], 0, strlen($scenario['wal_bytes'])));
        $t->same($scenario['preexisting_frames'], $plan['wal_frame_count_before']);
    };
    $tests[$prefix . 'appends only successful statement frames'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same(2, $plan['materialized_wal_frame_count']);
        $t->same($scenario['preexisting_frames'] + 2, $plan['wal_frame_count_after']);
        $t->same(0, $plan['discarded_wal_frame_count']);
        $t->same(false, $plan['wal_truncated']);
    };
    $tests[$prefix . 'records applied page numbers'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_applied_pages'], array_column($plan['import_plan']['applied'], 'page_number'));
    };
    $tests[$prefix . 'retains tenant id in applied rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($plan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'keeps JSONB mode on catalog row'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $value = $plan['import_plan']['applied'][1]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'statement-level failed frame remains isolated'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $failedFrames = array_column($plan['import_plan']['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index');
        $t->same([$scenario['preexisting_frames'] + 3], $failedFrames);
    };
    $tests[$prefix . 'appended frame pages match successful applied pages'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $pages = [];
        for ($index = 1; $index <= 2; $index++) {
            $frameOffset = 32 + (($scenario['preexisting_frames'] + $index - 1) * $frameSize);
            $frameHeader = unpack('Npage_number', substr($plan['wal_bytes_after'], $frameOffset, 4));
            $pages[] = (int) $frameHeader['page_number'];
        }
        $t->same($scenario['expected_applied_pages'], $pages);
    };
    $tests[$prefix . 'only final materialized frame is a commit frame'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        for ($index = 1; $index <= 2; $index++) {
            $frameOffset = 32 + (($scenario['preexisting_frames'] + $index - 1) * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($plan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, intdiv(strlen($plan['database_bytes_after_import']), $pageSize)], $commits);
    };
}

foreach ($rollbackDisabledFollowupScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $partialPlan = $scenario['partial_plan'];
    $followupPlan = $scenario['followup_plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity rollback disabled followup seed ' . $seed . ' ';

    $tests[$prefix . 'starts from partial rollback-disabled state'] = static function (TestRunner $t) use ($partialPlan): void {
        $t->same('partial_rollback', $partialPlan['status']);
        $t->same(false, $partialPlan['rollback_required']);
    };
    $tests[$prefix . 'followup commits without rollback'] = static function (TestRunner $t) use ($followupPlan): void {
        $t->same('ready', $followupPlan['status']);
        $t->same(false, $followupPlan['rollback_required']);
    };
    $tests[$prefix . 'uses followup transaction name'] = static function (TestRunner $t) use ($followupPlan, $seed): void {
        $t->same('application_disabled_followup_json_import_' . $seed, $followupPlan['transaction']);
    };
    $tests[$prefix . 'uses followup savepoint name'] = static function (TestRunner $t) use ($followupPlan, $seed): void {
        $t->same('disabled_followup_json_batch_' . $seed, $followupPlan['savepoint']);
    };
    $tests[$prefix . 'starts from partial database and wal bytes'] = static function (TestRunner $t) use ($partialPlan, $followupPlan): void {
        $t->same($partialPlan['database_bytes_after_import'], $followupPlan['database_bytes_before']);
        $t->same($partialPlan['wal_bytes_after'], $followupPlan['wal_bytes_before']);
    };
    $tests[$prefix . 'preserves partial wal prefix'] = static function (TestRunner $t) use ($partialPlan, $followupPlan): void {
        $t->same($partialPlan['wal_bytes_after'], substr($followupPlan['wal_bytes_after'], 0, strlen($partialPlan['wal_bytes_after'])));
    };
    $tests[$prefix . 'appends only followup success frames'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same(2, $followupPlan['materialized_wal_frame_count']);
        $t->same($scenario['partial_frame_count'] + 2, $followupPlan['wal_frame_count_after']);
        $t->same(false, $followupPlan['wal_truncated']);
    };
    $tests[$prefix . 'records followup applied pages'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same($scenario['expected_followup_pages'], array_column($followupPlan['import_plan']['applied'], 'page_number'));
    };
    $tests[$prefix . 'keeps previous failed statement out of followup failures'] = static function (TestRunner $t) use ($partialPlan, $followupPlan, $scenario): void {
        $t->same([$scenario['tenant_id']], array_column($partialPlan['import_plan']['failed'], 'tenant_id'));
        $t->same([], $followupPlan['failed_statements']);
        $t->same(0, $followupPlan['failed_statement_count']);
    };
    $tests[$prefix . 'retains tenant id in followup applied rows'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($followupPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'preserves jsonb mode on continued catalog row'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $value = $followupPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'records inserted followup summary row'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same([false, true], array_column($followupPlan['import_plan']['applied'], 'inserted_setting'));
        $t->same($scenario['expected_inserted_key'], $followupPlan['import_plan']['applied'][1]['key_name']);
    };
    $tests[$prefix . 'rollback preview begins after partial prefix'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same($scenario['partial_frame_count'], $followupPlan['wal_rollback_to_savepoint']['rollback_to_frame']);
        $t->same($scenario['expected_followup_pages'], $followupPlan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'savepoint boundary carries partial prefix pages'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $transactionPages = $followupPlan['import_plan']['savepoint_state'][0]['page_numbers'];
        $expectedPages = $scenario['committed_prefix_pages'];
        sort($transactionPages);
        sort($expectedPages);
        $t->same($expectedPages, $transactionPages);
    };
    $tests[$prefix . 'appended frame pages follow partial prefix'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        foreach ($scenario['expected_followup_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['partial_frame_count'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number', substr($followupPlan['wal_bytes_after'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'appended checksums continue after partial prefix'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($followupPlan['wal_bytes_after'], 0, 24), false);
        for ($frameIndex = 0; $frameIndex < $scenario['partial_frame_count']; $frameIndex++) {
            $frameOffset = 32 + ($frameIndex * $frameSize);
            $frame = substr($followupPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
        }
        foreach ($scenario['expected_followup_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['partial_frame_count'] + $index) * $frameSize);
            $frame = substr($followupPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frame, 0, 24));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
            $t->same($checksumSeed, [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
    $tests[$prefix . 'only final followup frame is a commit frame'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        foreach ($scenario['expected_followup_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + (($scenario['partial_frame_count'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($followupPlan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, intdiv(strlen($followupPlan['database_bytes_after_import']), $pageSize)], $commits);
    };
}

foreach ($rollbackDisabledFollowupFailureScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $partialPlan = $scenario['partial_plan'];
    $followupPlan = $scenario['followup_plan'];
    $tailPlan = $scenario['tail_plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity rollback disabled followup failure seed ' . $seed . ' ';

    $tests[$prefix . 'starts after partial failure and successful followup'] = static function (TestRunner $t) use ($partialPlan, $followupPlan): void {
        $t->same('partial_rollback', $partialPlan['status']);
        $t->same('ready', $followupPlan['status']);
        $t->same(false, $followupPlan['rollback_required']);
    };
    $tests[$prefix . 'rolls back only the later tail batch'] = static function (TestRunner $t) use ($tailPlan): void {
        $t->same('rolled_back_current_json_batch', $tailPlan['status']);
        $t->same(true, $tailPlan['rollback_required']);
    };
    $tests[$prefix . 'uses tail transaction and savepoint names'] = static function (TestRunner $t) use ($tailPlan, $seed): void {
        $t->same('application_disabled_followup_tail_json_import_' . $seed, $tailPlan['transaction']);
        $t->same('disabled_followup_tail_json_batch_' . $seed, $tailPlan['savepoint']);
    };
    $tests[$prefix . 'starts from followup database and wal bytes'] = static function (TestRunner $t) use ($followupPlan, $tailPlan): void {
        $t->same($followupPlan['database_bytes_after_import'], $tailPlan['database_bytes_before']);
        $t->same($followupPlan['wal_bytes_after'], substr($tailPlan['wal_bytes_before'], 0, strlen($followupPlan['wal_bytes_after'])));
    };
    $tests[$prefix . 'preserves committed followup wal prefix after rollback'] = static function (TestRunner $t) use ($followupPlan, $tailPlan): void {
        $t->same($followupPlan['wal_frame_count_after'], $tailPlan['wal_frame_count_after']);
        $t->same($followupPlan['wal_bytes_after'], $tailPlan['wal_bytes_after']);
    };
    $tests[$prefix . 'truncates to committed prefix byte boundary'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $expectedBytes = 32 + ($scenario['committed_prefix_frame_count'] * (24 + $scenario['page_size']));
        $t->same($expectedBytes, $tailPlan['wal_truncate_to_bytes']);
        $t->same($expectedBytes, strlen($tailPlan['wal_bytes_after']));
    };
    $tests[$prefix . 'discards only applied tail frames from savepoint'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $start = $scenario['committed_prefix_frame_count'] + 1;
        $t->same([$start, $start + 1], array_column($tailPlan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
        $t->same($scenario['expected_tail_pages'], $tailPlan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'statement rollback discards malformed final frame'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $start = $scenario['committed_prefix_frame_count'] + 1;
        $discarded = array_column($tailPlan['import_plan']['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index');
        $t->same([$start + 2], $discarded);
        $t->same([$scenario['tail_broken_page']], $tailPlan['import_plan']['failed'][0]['rollback']['restored_page_numbers']);
    };
    $tests[$prefix . 'restores only tail-mutated pages'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['expected_tail_pages'], $tailPlan['rollback_to_savepoint']['restored_page_numbers']);
        $t->same([], $tailPlan['rollback_to_savepoint']['missing_page_numbers']);
    };
    $tests[$prefix . 'records malformed tail statement'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $tailPlan['failed_statements']);
        $t->same('SQLite JSON5 input ended before a value', $tailPlan['import_plan']['failed'][0]['error']);
    };
    $tests[$prefix . 'restores database to followup image'] = static function (TestRunner $t) use ($followupPlan, $tailPlan): void {
        $t->same($followupPlan['database_bytes_after_import'], $tailPlan['restored_database_bytes']);
        $t->same(true, $tailPlan['database_restored_to_before']);
        $t->same(true, $tailPlan['database_changed_before_rollback']);
    };
    $tests[$prefix . 'applies catalog update and inserted tail row before failure'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same(2, $tailPlan['applied_statement_count']);
        $t->same($scenario['expected_tail_pages'], array_column($tailPlan['import_plan']['applied'], 'page_number'));
        $t->same([false, true], array_column($tailPlan['import_plan']['applied'], 'inserted_setting'));
    };
    $tests[$prefix . 'records inserted tail key before outer rollback'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['expected_tail_inserted_key'], $tailPlan['import_plan']['applied'][1]['key_name']);
        $t->same(true, in_array($scenario['expected_tail_inserted_key'], array_column($tailPlan['import_plan']['final_rows'], 'key_name'), true));
    };
    $tests[$prefix . 'retains tenant id in tail applied rows'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($tailPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'keeps jsonb mode on tail catalog update'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $value = $tailPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'tail wal contains malformed frame before rollback'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['committed_prefix_frame_count'] + 3, $tailPlan['wal_frame_count_before']);
        $t->same(3, $tailPlan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'savepoint boundary carries partial and followup prefix pages'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $transactionPages = $tailPlan['import_plan']['savepoint_state'][0]['page_numbers'];
        $expectedPages = array_values(array_unique($scenario['committed_prefix_pages']));
        sort($transactionPages);
        sort($expectedPages);
        $t->same($expectedPages, $transactionPages);
    };
    $tests[$prefix . 'tail wal frame pages follow committed prefix'] = static function (TestRunner $t) use ($scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $expectedPages = [$scenario['expected_tail_pages'][0], $scenario['expected_tail_pages'][1], $scenario['tail_broken_page']];
        foreach ($expectedPages as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number', substr($scenario['tail_wal_bytes'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'tail wal checksums continue after committed prefix'] = static function (TestRunner $t) use ($scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($scenario['tail_wal_bytes'], 0, 24), false);
        for ($frameIndex = 0; $frameIndex < $scenario['committed_prefix_frame_count']; $frameIndex++) {
            $frameOffset = 32 + ($frameIndex * $frameSize);
            $frame = substr($scenario['tail_wal_bytes'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
        }
        $expectedPages = [$scenario['expected_tail_pages'][0], $scenario['expected_tail_pages'][1], $scenario['tail_broken_page']];
        foreach ($expectedPages as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frame = substr($scenario['tail_wal_bytes'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frame, 0, 24));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
            $t->same($checksumSeed, [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
}

foreach ($rollbackDisabledFollowupRecoveryScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $partialPlan = $scenario['partial_plan'];
    $followupPlan = $scenario['followup_plan'];
    $tailPlan = $scenario['tail_plan'];
    $recoveryPlan = $scenario['recovery_plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity rollback disabled followup recovery seed ' . $seed . ' ';

    $tests[$prefix . 'starts after partial followup and rolled back tail'] = static function (TestRunner $t) use ($partialPlan, $followupPlan, $tailPlan): void {
        $t->same('partial_rollback', $partialPlan['status']);
        $t->same('ready', $followupPlan['status']);
        $t->same('rolled_back_current_json_batch', $tailPlan['status']);
    };
    $tests[$prefix . 'commits corrected recovery batch without rollback'] = static function (TestRunner $t) use ($recoveryPlan): void {
        $t->same('ready', $recoveryPlan['status']);
        $t->same(false, $recoveryPlan['rollback_required']);
    };
    $tests[$prefix . 'uses recovery transaction and savepoint names'] = static function (TestRunner $t) use ($recoveryPlan, $seed): void {
        $t->same('application_disabled_followup_recovery_json_import_' . $seed, $recoveryPlan['transaction']);
        $t->same('disabled_followup_recovery_json_batch_' . $seed, $recoveryPlan['savepoint']);
    };
    $tests[$prefix . 'starts from tail rollback database and wal bytes'] = static function (TestRunner $t) use ($tailPlan, $recoveryPlan): void {
        $t->same($tailPlan['restored_database_bytes'], $recoveryPlan['database_bytes_before']);
        $t->same($tailPlan['wal_bytes_after'], $recoveryPlan['wal_bytes_before']);
    };
    $tests[$prefix . 'preserves committed prefix wal bytes'] = static function (TestRunner $t) use ($tailPlan, $recoveryPlan): void {
        $t->same($tailPlan['wal_bytes_after'], substr($recoveryPlan['wal_bytes_after'], 0, strlen($tailPlan['wal_bytes_after'])));
    };
    $tests[$prefix . 'appends only recovery success frames'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same(2, $recoveryPlan['materialized_wal_frame_count']);
        $t->same($scenario['committed_prefix_frame_count'] + 2, $recoveryPlan['wal_frame_count_after']);
        $t->same(false, $recoveryPlan['wal_truncated']);
        $t->same(0, $recoveryPlan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'has no failed recovery statements'] = static function (TestRunner $t) use ($recoveryPlan): void {
        $t->same([], $recoveryPlan['failed_statements']);
        $t->same(0, $recoveryPlan['failed_statement_count']);
    };
    $tests[$prefix . 'records recovery applied pages'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['expected_recovery_pages'], array_column($recoveryPlan['import_plan']['applied'], 'page_number'));
    };
    $tests[$prefix . 'records inserted recovery row'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same([false, true], array_column($recoveryPlan['import_plan']['applied'], 'inserted_setting'));
        $t->same($scenario['expected_recovery_inserted_key'], $recoveryPlan['import_plan']['applied'][1]['key_name']);
        $t->same(true, in_array($scenario['expected_recovery_inserted_id'], array_column($recoveryPlan['import_plan']['final_rows'], 'setting_id'), true));
    };
    $tests[$prefix . 'does not revive rolled back tail insert'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same(false, in_array($scenario['rejected_tail_inserted_key'], array_column($recoveryPlan['import_plan']['final_rows'], 'key_name'), true));
    };
    $tests[$prefix . 'retains tenant id in recovery applied rows'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($recoveryPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'keeps jsonb mode on recovery catalog update'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $value = $recoveryPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'savepoint boundary carries committed prefix pages'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $transactionPages = $recoveryPlan['import_plan']['savepoint_state'][0]['page_numbers'];
        $expectedPages = array_values(array_unique($scenario['committed_prefix_pages']));
        sort($transactionPages);
        sort($expectedPages);
        $t->same($expectedPages, $transactionPages);
    };
    $tests[$prefix . 'rollback preview starts after committed prefix'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['committed_prefix_frame_count'], $recoveryPlan['wal_rollback_to_savepoint']['rollback_to_frame']);
        $t->same($scenario['expected_recovery_pages'], $recoveryPlan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'appended frame pages follow committed prefix'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        foreach ($scenario['expected_recovery_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number', substr($recoveryPlan['wal_bytes_after'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'appended checksums continue after committed prefix'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($recoveryPlan['wal_bytes_after'], 0, 24), false);
        for ($frameIndex = 0; $frameIndex < $scenario['committed_prefix_frame_count']; $frameIndex++) {
            $frameOffset = 32 + ($frameIndex * $frameSize);
            $frame = substr($recoveryPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
        }
        foreach ($scenario['expected_recovery_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frame = substr($recoveryPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frame, 0, 24));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
            $t->same($checksumSeed, [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
    $tests[$prefix . 'only final recovery frame is a commit frame'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        foreach ($scenario['expected_recovery_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($recoveryPlan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, intdiv(strlen($recoveryPlan['database_bytes_after_import']), $pageSize)], $commits);
    };
    $tests[$prefix . 'extends database image for inserted recovery page'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same(true, intdiv(strlen($recoveryPlan['database_bytes_after_import']), (int) $scenario['page_size']) >= $scenario['expected_recovery_pages'][1]);
    };
}

foreach ($rollbackDisabledPostRecoveryFailureScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $partialPlan = $scenario['partial_plan'];
    $followupPlan = $scenario['followup_plan'];
    $previousTailPlan = $scenario['previous_tail_plan'];
    $recoveryPlan = $scenario['recovery_plan'];
    $postFailurePlan = $scenario['post_recovery_failure_plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity rollback disabled post-recovery failure seed ' . $seed . ' ';

    $tests[$prefix . 'starts after partial followup tail rollback and recovery'] = static function (TestRunner $t) use ($partialPlan, $followupPlan, $previousTailPlan, $recoveryPlan): void {
        $t->same('partial_rollback', $partialPlan['status']);
        $t->same('ready', $followupPlan['status']);
        $t->same('rolled_back_current_json_batch', $previousTailPlan['status']);
        $t->same('ready', $recoveryPlan['status']);
    };
    $tests[$prefix . 'rolls back only the post recovery tail batch'] = static function (TestRunner $t) use ($postFailurePlan): void {
        $t->same('rolled_back_current_json_batch', $postFailurePlan['status']);
        $t->same(true, $postFailurePlan['rollback_required']);
    };
    $tests[$prefix . 'uses post recovery transaction and savepoint names'] = static function (TestRunner $t) use ($postFailurePlan, $seed): void {
        $t->same('application_disabled_post_recovery_tail_json_import_' . $seed, $postFailurePlan['transaction']);
        $t->same('disabled_post_recovery_tail_json_batch_' . $seed, $postFailurePlan['savepoint']);
    };
    $tests[$prefix . 'starts from recovery database and wal bytes'] = static function (TestRunner $t) use ($recoveryPlan, $postFailurePlan): void {
        $t->same($recoveryPlan['database_bytes_after_import'], $postFailurePlan['database_bytes_before']);
        $t->same($recoveryPlan['wal_bytes_after'], substr($postFailurePlan['wal_bytes_before'], 0, strlen($recoveryPlan['wal_bytes_after'])));
    };
    $tests[$prefix . 'preserves recovery wal prefix after rollback'] = static function (TestRunner $t) use ($recoveryPlan, $postFailurePlan): void {
        $t->same($recoveryPlan['wal_frame_count_after'], $postFailurePlan['wal_frame_count_after']);
        $t->same($recoveryPlan['wal_bytes_after'], $postFailurePlan['wal_bytes_after']);
    };
    $tests[$prefix . 'truncates to recovery committed prefix byte boundary'] = static function (TestRunner $t) use ($postFailurePlan, $scenario): void {
        $expectedBytes = 32 + ($scenario['committed_prefix_frame_count'] * (24 + $scenario['page_size']));
        $t->same($expectedBytes, $postFailurePlan['wal_truncate_to_bytes']);
        $t->same($expectedBytes, strlen($postFailurePlan['wal_bytes_after']));
    };
    $tests[$prefix . 'discards only post recovery applied frames from savepoint'] = static function (TestRunner $t) use ($postFailurePlan, $scenario): void {
        $start = $scenario['committed_prefix_frame_count'] + 1;
        $t->same([$start, $start + 1], array_column($postFailurePlan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
        $t->same($scenario['expected_post_recovery_pages'], $postFailurePlan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'statement rollback discards malformed final frame'] = static function (TestRunner $t) use ($postFailurePlan, $scenario): void {
        $start = $scenario['committed_prefix_frame_count'] + 1;
        $discarded = array_column($postFailurePlan['import_plan']['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index');
        $t->same([$start + 2], $discarded);
        $t->same([$scenario['tail_broken_page']], $postFailurePlan['import_plan']['failed'][0]['rollback']['restored_page_numbers']);
    };
    $tests[$prefix . 'restores only post recovery tail pages'] = static function (TestRunner $t) use ($postFailurePlan, $scenario): void {
        $t->same($scenario['expected_post_recovery_pages'], $postFailurePlan['rollback_to_savepoint']['restored_page_numbers']);
        $t->same([], $postFailurePlan['rollback_to_savepoint']['missing_page_numbers']);
    };
    $tests[$prefix . 'records malformed post recovery statement'] = static function (TestRunner $t) use ($postFailurePlan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $postFailurePlan['failed_statements']);
        $t->same('SQLite JSON5 input ended before a value', $postFailurePlan['import_plan']['failed'][0]['error']);
    };
    $tests[$prefix . 'restores database to recovery image'] = static function (TestRunner $t) use ($recoveryPlan, $postFailurePlan): void {
        $t->same($recoveryPlan['database_bytes_after_import'], $postFailurePlan['restored_database_bytes']);
        $t->same(true, $postFailurePlan['database_restored_to_before']);
        $t->same(true, $postFailurePlan['database_changed_before_rollback']);
    };
    $tests[$prefix . 'applies catalog update and inserted post recovery row before failure'] = static function (TestRunner $t) use ($postFailurePlan, $scenario): void {
        $t->same(2, $postFailurePlan['applied_statement_count']);
        $t->same($scenario['expected_post_recovery_pages'], array_column($postFailurePlan['import_plan']['applied'], 'page_number'));
        $t->same([false, true], array_column($postFailurePlan['import_plan']['applied'], 'inserted_setting'));
    };
    $tests[$prefix . 'records inserted post recovery key before outer rollback'] = static function (TestRunner $t) use ($postFailurePlan, $scenario): void {
        $t->same($scenario['expected_post_recovery_inserted_key'], $postFailurePlan['import_plan']['applied'][1]['key_name']);
        $t->same(true, in_array($scenario['expected_post_recovery_inserted_id'], array_column($postFailurePlan['import_plan']['final_rows'], 'setting_id'), true));
    };
    $tests[$prefix . 'does not revive earlier rolled back tail insert'] = static function (TestRunner $t) use ($postFailurePlan, $scenario): void {
        $t->same(false, in_array($scenario['rejected_prior_tail_inserted_key'], array_column($postFailurePlan['import_plan']['final_rows'], 'key_name'), true));
    };
    $tests[$prefix . 'retains tenant id in post recovery applied rows'] = static function (TestRunner $t) use ($postFailurePlan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($postFailurePlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'keeps jsonb mode on post recovery catalog update'] = static function (TestRunner $t) use ($postFailurePlan, $scenario): void {
        $value = $postFailurePlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'post recovery wal contains malformed frame before rollback'] = static function (TestRunner $t) use ($postFailurePlan, $scenario): void {
        $t->same($scenario['committed_prefix_frame_count'] + 3, $postFailurePlan['wal_frame_count_before']);
        $t->same(3, $postFailurePlan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'savepoint boundary carries recovery prefix pages'] = static function (TestRunner $t) use ($postFailurePlan, $scenario): void {
        $t->same($scenario['committed_prefix_frame_count'], $postFailurePlan['wal_rollback_to_savepoint']['rollback_to_frame']);
        $transactionPages = $postFailurePlan['import_plan']['savepoint_state'][0]['page_numbers'];
        $expectedPages = array_values(array_unique($scenario['committed_prefix_pages']));
        sort($transactionPages);
        sort($expectedPages);
        $t->same($expectedPages, $transactionPages);
    };
    $tests[$prefix . 'post recovery wal frame pages follow committed prefix'] = static function (TestRunner $t) use ($scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $expectedPages = [$scenario['expected_post_recovery_pages'][0], $scenario['expected_post_recovery_pages'][1], $scenario['tail_broken_page']];
        foreach ($expectedPages as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number', substr($scenario['post_recovery_wal_bytes'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'post recovery wal checksums continue after recovery prefix'] = static function (TestRunner $t) use ($scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($scenario['post_recovery_wal_bytes'], 0, 24), false);
        for ($frameIndex = 0; $frameIndex < $scenario['committed_prefix_frame_count']; $frameIndex++) {
            $frameOffset = 32 + ($frameIndex * $frameSize);
            $frame = substr($scenario['post_recovery_wal_bytes'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
        }
        $expectedPages = [$scenario['expected_post_recovery_pages'][0], $scenario['expected_post_recovery_pages'][1], $scenario['tail_broken_page']];
        foreach ($expectedPages as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frame = substr($scenario['post_recovery_wal_bytes'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frame, 0, 24));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
            $t->same($checksumSeed, [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
}

foreach ($rollbackDisabledPostRecoveryRecoveryScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $statusChain = $scenario['status_chain'];
    $recoveryPlan = $scenario['post_recovery_recovery_plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity rollback disabled post-recovery recovery seed ' . $seed . ' ';

    $tests[$prefix . 'starts after partial followup tail recovery and post recovery failure'] = static function (TestRunner $t) use ($statusChain): void {
        $t->same('partial_rollback', $statusChain['partial']);
        $t->same('ready', $statusChain['followup']);
        $t->same('rolled_back_current_json_batch', $statusChain['previous_tail']);
        $t->same('ready', $statusChain['previous_recovery']);
        $t->same('rolled_back_current_json_batch', $statusChain['post_recovery_failure']);
    };
    $tests[$prefix . 'commits corrected recovery after post recovery failure'] = static function (TestRunner $t) use ($recoveryPlan): void {
        $t->same('ready', $recoveryPlan['status']);
        $t->same(false, $recoveryPlan['rollback_required']);
        $t->same(2, $recoveryPlan['applied_statement_count']);
        $t->same(0, $recoveryPlan['failed_statement_count']);
    };
    $tests[$prefix . 'uses post recovery recovery transaction and savepoint names'] = static function (TestRunner $t) use ($recoveryPlan, $seed): void {
        $t->same('application_disabled_post_recovery_recovery_json_import_' . $seed, $recoveryPlan['transaction']);
        $t->same('disabled_post_recovery_recovery_json_batch_' . $seed, $recoveryPlan['savepoint']);
    };
    $tests[$prefix . 'starts from post failure restored database and wal bytes'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['post_failure_database_hash'], hash('sha256', (string) $recoveryPlan['database_bytes_before']));
        $t->same($scenario['post_failure_wal_hash'], hash('sha256', (string) $recoveryPlan['wal_bytes_before']));
    };
    $tests[$prefix . 'preserves committed prefix before appending recovery frames'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['committed_prefix_frame_count'], $recoveryPlan['wal_frame_count_before']);
        $prefixLength = 32 + ($scenario['committed_prefix_frame_count'] * (24 + $scenario['page_size']));
        $t->same($scenario['post_failure_wal_hash'], hash('sha256', substr($recoveryPlan['wal_bytes_after'], 0, $prefixLength)));
    };
    $tests[$prefix . 'appends only corrected recovery frames'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same(2, $recoveryPlan['materialized_wal_frame_count']);
        $t->same($scenario['committed_prefix_frame_count'] + 2, $recoveryPlan['wal_frame_count_after']);
        $t->same(0, $recoveryPlan['discarded_wal_frame_count']);
        $t->same(false, $recoveryPlan['wal_truncated']);
    };
    $tests[$prefix . 'records corrected recovery page numbers'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['expected_recovery_pages'], array_column($recoveryPlan['import_plan']['applied'], 'page_number'));
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($recoveryPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'records corrected inserted recovery row'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['expected_recovery_inserted_key'], $recoveryPlan['import_plan']['applied'][1]['key_name']);
        $t->same(true, in_array($scenario['expected_recovery_inserted_id'], array_column($recoveryPlan['import_plan']['final_rows'], 'setting_id'), true));
    };
    $tests[$prefix . 'does not revive earlier rolled back tail inserts'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $finalKeys = array_column($recoveryPlan['import_plan']['final_rows'], 'key_name');
        $t->same(false, in_array($scenario['rejected_prior_tail_inserted_key'], $finalKeys, true));
        $t->same(false, in_array($scenario['rejected_post_recovery_tail_inserted_key'], $finalKeys, true));
    };
    $tests[$prefix . 'keeps jsonb mode on corrected catalog update'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $value = $recoveryPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'savepoint boundary excludes rolled back post recovery pages'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $transactionPages = $recoveryPlan['import_plan']['savepoint_state'][0]['page_numbers'];
        $expectedPages = array_values(array_unique($scenario['committed_prefix_pages']));
        sort($transactionPages);
        sort($expectedPages);
        $t->same($expectedPages, $transactionPages);
        $t->same(false, in_array($scenario['rejected_post_recovery_tail_inserted_key'], array_column($recoveryPlan['import_plan']['final_rows'], 'key_name'), true));
    };
    $tests[$prefix . 'appended wal frame pages follow post failure prefix'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        foreach ($scenario['expected_recovery_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number', substr($recoveryPlan['wal_bytes_after'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'wal checksums continue after post failure prefix'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($recoveryPlan['wal_bytes_after'], 0, 24), false);
        for ($frameIndex = 0; $frameIndex < $scenario['committed_prefix_frame_count']; $frameIndex++) {
            $frameOffset = 32 + ($frameIndex * $frameSize);
            $frame = substr($recoveryPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
        }
        foreach ($scenario['expected_recovery_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frame = substr($recoveryPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frame, 0, 24));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
            $t->same($checksumSeed, [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
    $tests[$prefix . 'only final corrected recovery frame is a commit frame'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        foreach ($scenario['expected_recovery_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($recoveryPlan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, intdiv(strlen($recoveryPlan['database_bytes_after_import']), $pageSize)], $commits);
    };
    $tests[$prefix . 'extends database image for corrected inserted recovery page'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same(true, intdiv(strlen($recoveryPlan['database_bytes_after_import']), (int) $scenario['page_size']) >= $scenario['expected_recovery_pages'][1]);
    };
}

foreach ($postRecoveryCheckpointScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $recoveryPlan = $scenario['post_recovery_recovery_plan'];
    $checkpointPlan = $scenario['checkpoint_plan'];
    $releasedCheckpoint = $scenario['released_checkpoint'];
    $pinnedCheckpoint = $scenario['pinned_checkpoint'];
    $prefix = 'sqlite application wal rollback json dynamic parity post-recovery checkpoint seed ' . $seed . ' ';

    $tests[$prefix . 'starts from ready post-recovery recovery WAL'] = static function (TestRunner $t) use ($scenario, $recoveryPlan): void {
        $t->same('ready', $recoveryPlan['status']);
        $t->same(false, $recoveryPlan['rollback_required']);
        $t->same($scenario['committed_prefix_frame_count'] + 2, $recoveryPlan['wal_frame_count_after']);
    };
    $tests[$prefix . 'records checkpoint input database hash'] = static function (TestRunner $t) use ($scenario, $recoveryPlan): void {
        $t->same($scenario['checkpoint_database_bytes_before_hash'], hash('sha256', (string) $recoveryPlan['database_bytes_before']));
    };
    $tests[$prefix . 'uses selected checkpoint reset mode and action'] = static function (TestRunner $t) use ($scenario, $releasedCheckpoint): void {
        $t->same($scenario['checkpoint_mode'], $releasedCheckpoint['mode']);
        $t->same($scenario['expected_checkpoint_action'], $releasedCheckpoint['wal_action']);
        $t->same($scenario['expected_released_wal_bytes_length'], $releasedCheckpoint['wal_bytes_length']);
    };
    $tests[$prefix . 'checkpoint plan ends at corrected recovery commit frame'] = static function (TestRunner $t) use ($scenario, $recoveryPlan, $checkpointPlan): void {
        $t->same($recoveryPlan['wal_frame_count_after'], $checkpointPlan['last_commit_frame']);
        $t->same(intdiv(strlen((string) $recoveryPlan['database_bytes_after_import']), (int) $scenario['page_size']), $checkpointPlan['database_page_count']);
        $t->same(strlen((string) $recoveryPlan['database_bytes_after_import']), $checkpointPlan['final_database_bytes']);
    };
    $tests[$prefix . 'checkpoint applies final recovery pages from WAL images'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['expected_recovery_pages_checkpointed']);
        foreach ($scenario['expected_recovery_pages'] as $pageNumber) {
            $t->same(true, in_array($pageNumber, $scenario['applied_page_numbers'], true));
        }
    };
    $tests[$prefix . 'checkpoint supersedes stale catalog frames before the corrected image'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, count($scenario['superseded_frame_indexes']) > 0);
        $t->same(true, in_array($scenario['expected_recovery_pages'][0], $scenario['superseded_page_numbers'], true));
    };
    $tests[$prefix . 'released checkpoint completes all committed frames'] = static function (TestRunner $t) use ($scenario, $releasedCheckpoint): void {
        $t->same(false, $releasedCheckpoint['busy']);
        $t->same(true, $releasedCheckpoint['can_reset']);
        $t->same(0, $releasedCheckpoint['remaining_committed_frame_count']);
        $t->same(count($scenario['applied_frame_indexes']), $releasedCheckpoint['checkpointed_frame_count']);
    };
    $tests[$prefix . 'released checkpoint records durable sidecar dependencies'] = static function (TestRunner $t) use ($releasedCheckpoint): void {
        $t->same(['sqlite-wal-checkpoint', 'durable-sidecar-write'], $releasedCheckpoint['dependencies']);
    };
    $tests[$prefix . 'released checkpoint sidecar shape matches restart or truncate mode'] = static function (TestRunner $t) use ($scenario, $releasedCheckpoint): void {
        if ($scenario['checkpoint_mode'] === 'truncate') {
            $t->same(null, $releasedCheckpoint['wal_header']);
            $t->same('', $releasedCheckpoint['wal_bytes']);
            return;
        }

        $t->same(true, is_array($releasedCheckpoint['wal_header']));
        $t->same(32, strlen($releasedCheckpoint['wal_bytes']));
    };
    $tests[$prefix . 'reader pinned checkpoint preserves WAL and reports busy reset'] = static function (TestRunner $t) use ($scenario, $recoveryPlan, $pinnedCheckpoint): void {
        $t->same(true, $pinnedCheckpoint['busy']);
        $t->same(false, $pinnedCheckpoint['can_reset']);
        $t->same('preserve_wal', $pinnedCheckpoint['wal_action']);
        $t->same((string) $recoveryPlan['wal_bytes_after'], $pinnedCheckpoint['wal_bytes']);
        $t->same($scenario['reader_end_frame'], $pinnedCheckpoint['reader_end_frame']);
    };
    $tests[$prefix . 'reader pinned checkpoint leaves final inserted recovery page unapplied'] = static function (TestRunner $t) use ($scenario, $pinnedCheckpoint): void {
        $t->same(false, $scenario['pinned_insert_page_matches_corrected_recovery']);
        $t->same(1, $pinnedCheckpoint['remaining_committed_frame_count']);
        $t->same(count($scenario['applied_frame_indexes']) - 1, $pinnedCheckpoint['checkpointed_frame_count']);
    };
    $tests[$prefix . 'released checkpoint publishes final database page count'] = static function (TestRunner $t) use ($checkpointPlan, $releasedCheckpoint): void {
        $t->same($checkpointPlan['database_page_count'], $releasedCheckpoint['database_page_count']);
        $t->same($checkpointPlan['final_database_bytes'], $releasedCheckpoint['final_database_bytes']);
    };
    $tests[$prefix . 'final row set keeps recovered insert and excludes both rolled back tails'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['recovery_inserted_key_retained']);
        $t->same(false, $scenario['rejected_prior_tail_key_retained']);
        $t->same(false, $scenario['rejected_post_recovery_tail_key_retained']);
    };
}

foreach ($postCheckpointFollowupScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $checkpoint = $scenario['released_checkpoint'];
    $followupPlan = $scenario['post_checkpoint_followup_plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity post-checkpoint followup seed ' . $seed . ' ';

    $tests[$prefix . 'starts after released restart or truncate checkpoint'] = static function (TestRunner $t) use ($checkpoint): void {
        $t->same(false, $checkpoint['busy']);
        $t->same(true, $checkpoint['can_reset']);
        $t->same(true, in_array($checkpoint['wal_action'], ['restart_wal', 'truncate_wal'], true));
    };
    $tests[$prefix . 'uses checkpointed database bytes as import base'] = static function (TestRunner $t) use ($scenario, $followupPlan): void {
        $t->same($scenario['post_checkpoint_input_database_hash'], hash('sha256', (string) $followupPlan['database_bytes_before']));
        $t->same(false, $followupPlan['database_restored_to_before']);
    };
    $tests[$prefix . 'starts from an empty post-checkpoint wal generation'] = static function (TestRunner $t) use ($scenario, $followupPlan): void {
        $t->same(32, $scenario['post_checkpoint_wal_header_length']);
        $t->same($scenario['post_checkpoint_input_wal_hash'], hash('sha256', (string) $followupPlan['wal_bytes_before']));
        $t->same(0, $followupPlan['wal_frame_count_before']);
    };
    $tests[$prefix . 'preserves checkpoint reset mode in scenario'] = static function (TestRunner $t) use ($scenario, $checkpoint): void {
        $t->same($scenario['checkpoint_mode'], $checkpoint['mode']);
        $t->same($scenario['checkpoint_mode'] === 'truncate', $scenario['post_checkpoint_started_new_wal_header']);
    };
    $tests[$prefix . 'commits post-checkpoint followup without rollback'] = static function (TestRunner $t) use ($followupPlan): void {
        $t->same('ready', $followupPlan['status']);
        $t->same(false, $followupPlan['rollback_required']);
        $t->same(2, $followupPlan['applied_statement_count']);
        $t->same(0, $followupPlan['failed_statement_count']);
    };
    $tests[$prefix . 'uses post-checkpoint transaction and savepoint names'] = static function (TestRunner $t) use ($followupPlan, $seed): void {
        $t->same('application_post_checkpoint_json_import_' . $seed, $followupPlan['transaction']);
        $t->same('post_checkpoint_json_batch_' . $seed, $followupPlan['savepoint']);
    };
    $tests[$prefix . 'appends only post-checkpoint followup frames'] = static function (TestRunner $t) use ($followupPlan): void {
        $t->same(2, $followupPlan['materialized_wal_frame_count']);
        $t->same(2, $followupPlan['wal_frame_count_after']);
        $t->same(0, $followupPlan['discarded_wal_frame_count']);
        $t->same(false, $followupPlan['wal_truncated']);
    };
    $tests[$prefix . 'records followup page numbers'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same($scenario['expected_followup_pages'], array_column($followupPlan['import_plan']['applied'], 'page_number'));
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($followupPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'records inserted followup row'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same($scenario['expected_followup_inserted_key'], $followupPlan['import_plan']['applied'][1]['key_name']);
        $t->same(true, in_array($scenario['expected_followup_inserted_id'], array_column($followupPlan['import_plan']['final_rows'], 'setting_id'), true));
        $t->same(true, $scenario['followup_inserted_key_retained']);
    };
    $tests[$prefix . 'does not revive rolled back tail inserts'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(false, $scenario['rejected_prior_tail_key_retained_after_followup']);
        $t->same(false, $scenario['rejected_post_recovery_tail_key_retained_after_followup']);
    };
    $tests[$prefix . 'keeps jsonb mode on catalog update'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $value = $followupPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'post-checkpoint savepoint starts from a clean wal boundary'] = static function (TestRunner $t) use ($followupPlan): void {
        $t->same(0, $followupPlan['wal_rollback_to_savepoint']['rollback_to_frame']);
        $t->same([1, 2], array_column($followupPlan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
        $t->same([], $followupPlan['import_plan']['savepoint_state'][0]['page_numbers']);
    };
    $tests[$prefix . 'appended wal frame pages begin at frame one'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        foreach ($scenario['expected_followup_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + ($index * $frameSize);
            $frameHeader = unpack('Npage_number', substr($followupPlan['wal_bytes_after'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'wal checksums chain from reset header'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($followupPlan['wal_bytes_after'], 0, 24), false);
        foreach ($scenario['expected_followup_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + ($index * $frameSize);
            $frame = substr($followupPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frame, 0, 24));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
            $t->same($checksumSeed, [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
    $tests[$prefix . 'only final followup frame is a commit frame'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        foreach ($scenario['expected_followup_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + ($index * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($followupPlan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, intdiv(strlen($followupPlan['database_bytes_after_import']), $pageSize)], $commits);
    };
    $tests[$prefix . 'extends database image for post-checkpoint inserted page'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same(true, intdiv(strlen($followupPlan['database_bytes_after_import']), (int) $scenario['page_size']) >= $scenario['expected_followup_pages'][1]);
    };
}

foreach ($successfulMaterializedWalScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity successful materialized wal seed ' . $seed . ' ';

    $tests[$prefix . 'commits without rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same('ready', $plan['status']);
        $t->same(false, $plan['rollback_required']);
    };
    $tests[$prefix . 'uses success transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_success_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses success savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('success_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'has no failed statements'] = static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan['failed_statements']);
        $t->same(0, $plan['failed_statement_count']);
    };
    $tests[$prefix . 'applies all successful statements'] = static function (TestRunner $t) use ($plan): void {
        $t->same(3, $plan['applied_statement_count']);
    };
    $tests[$prefix . 'does not restore database to before image'] = static function (TestRunner $t) use ($plan): void {
        $t->same(false, $plan['database_restored_to_before']);
        $t->same(true, $plan['database_changed_before_rollback']);
    };
    $tests[$prefix . 'preserves WAL prefix and appends current frames'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_bytes'], substr($plan['wal_bytes_after'], 0, strlen($scenario['wal_bytes'])));
        $t->same($scenario['preexisting_frames'] + 3, $plan['wal_frame_count_after']);
    };
    $tests[$prefix . 'materializes exactly three WAL frames'] = static function (TestRunner $t) use ($plan): void {
        $t->same(3, $plan['materialized_wal_frame_count']);
        $t->same(false, $plan['wal_truncated']);
        $t->same(0, $plan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'records expected applied pages'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_applied_pages'], array_column($plan['import_plan']['applied'], 'page_number'));
    };
    $tests[$prefix . 'records inserted audit row'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([false, false, true], array_column($plan['import_plan']['applied'], 'inserted_setting'));
        $t->same($scenario['expected_inserted_key'], $plan['import_plan']['applied'][2]['key_name']);
    };
    $tests[$prefix . 'retains tenant id in all applied rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id'], $scenario['tenant_id']], array_column($plan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'preserves jsonb mode on catalog row'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['jsonb_mode'], $plan['import_plan']['applied'][1]['key_value'] instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'keeps unapplied savepoint rollback preview'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_applied_pages'], $plan['rollback_to_savepoint']['restored_page_numbers']);
        $t->same($scenario['expected_applied_pages'], $plan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'appended frame pages match applied pages'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $pages = [];
        for ($index = 1; $index <= 3; $index++) {
            $frameOffset = 32 + (($scenario['preexisting_frames'] + $index - 1) * $frameSize);
            $frameHeader = unpack('Npage_number', substr($plan['wal_bytes_after'], $frameOffset, 4));
            $pages[] = (int) $frameHeader['page_number'];
        }
        $t->same($scenario['expected_applied_pages'], $pages);
    };
    $tests[$prefix . 'appended frame checksums chain from prefix'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($plan['wal_bytes_after'], 0, 24), false);
        for ($frame = 1; $frame <= $scenario['preexisting_frames']; $frame++) {
            $frameOffset = 32 + (($frame - 1) * $frameSize);
            $frameBytes = substr($plan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frameBytes, 0, 8) . substr($frameBytes, 24, $pageSize), false, $checksumSeed[0], $checksumSeed[1]);
        }
        for ($index = 1; $index <= 3; $index++) {
            $frameOffset = 32 + (($scenario['preexisting_frames'] + $index - 1) * $frameSize);
            $frameBytes = substr($plan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frameBytes, 0, 8) . substr($frameBytes, 24, $pageSize), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frameBytes, 0, 24));
            $t->same([$checksumSeed[0], $checksumSeed[1]], [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
    $tests[$prefix . 'only final appended frame is a commit frame'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        for ($index = 1; $index <= 3; $index++) {
            $frameOffset = 32 + (($scenario['preexisting_frames'] + $index - 1) * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($plan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, 0, intdiv(strlen($plan['database_bytes_after_import']), $pageSize)], $commits);
    };
}

foreach ($scenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity seed ' . $seed . ' ';

    $tests[$prefix . 'rolls back current json batch'] = static function (TestRunner $t) use ($plan): void {
        $t->same('rolled_back_current_json_batch', $plan['status']);
    };
    $tests[$prefix . 'requires rollback after malformed dynamic JSON'] = static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan['rollback_required']);
    };
    $tests[$prefix . 'uses dynamic transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_dynamic_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses dynamic savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('dynamic_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'preserves generated page size'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['page_size'], $plan['page_size']);
    };
    $tests[$prefix . 'applies two statements before failure'] = static function (TestRunner $t) use ($plan): void {
        $t->same(2, $plan['applied_statement_count']);
    };
    $tests[$prefix . 'records one failed statement'] = static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan['failed_statement_count']);
    };
    $tests[$prefix . 'names failed statement'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $plan['failed_statements']);
    };
    $tests[$prefix . 'restores original database bytes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['database_bytes'], $plan['restored_database_bytes']);
    };
    $tests[$prefix . 'marks database changed before rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan['database_changed_before_rollback']);
    };
    $tests[$prefix . 'marks database restored after rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan['database_restored_to_before']);
    };
    $tests[$prefix . 'counts dynamic WAL frames before rollback'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['wal_frame_count_before']);
    };
    $tests[$prefix . 'truncates WAL to dynamic savepoint offset'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_truncate_bytes'], $plan['wal_truncate_to_bytes']);
    };
    $tests[$prefix . 'has zero WAL frames after rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan['wal_frame_count_after']);
    };
    $tests[$prefix . 'discards all current batch WAL frames'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'reports WAL truncation'] = static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan['wal_truncated']);
    };
    $tests[$prefix . 'keeps WAL header bytes after rollback'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same(substr($scenario['wal_bytes'], 0, 32), $plan['wal_bytes_after']);
    };
    $tests[$prefix . 'restores applied dynamic pages'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'has no missing dynamic page images'] = static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan['rollback_to_savepoint']['missing_page_numbers']);
    };
    $tests[$prefix . 'rolls WAL back to frame zero'] = static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan['wal_rollback_to_savepoint']['rollback_to_frame']);
    };
    $tests[$prefix . 'discards applied frame indexes'] = static function (TestRunner $t) use ($plan): void {
        $t->same([1, 2], array_column($plan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'discards applied page numbers'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'keeps statement-level malformed rollback isolated'] = static function (TestRunner $t) use ($plan): void {
        $t->same([3], array_column($plan['import_plan']['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'retains tenant id in applied rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($plan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'keeps JSONB mode on generated scenario'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $value = $plan['import_plan']['applied'][1]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
}

foreach ($preexistingWalScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity preexisting wal seed ' . $seed . ' ';

    $tests[$prefix . 'rolls back current json batch'] = static function (TestRunner $t) use ($plan): void {
        $t->same('rolled_back_current_json_batch', $plan['status']);
    };
    $tests[$prefix . 'uses prefix transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_prefix_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses prefix savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('prefix_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'records dynamic preexisting wal frame count'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['wal_frame_count_before']);
    };
    $tests[$prefix . 'rolls back to preexisting frame boundary'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['preexisting_frames'], $plan['wal_rollback_to_savepoint']['rollback_to_frame']);
    };
    $tests[$prefix . 'truncates wal after preexisting frames'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_truncate_bytes'], $plan['wal_truncate_to_bytes']);
        $t->same($scenario['expected_truncate_bytes'], strlen($plan['wal_bytes_after']));
    };
    $tests[$prefix . 'preserves preexisting wal prefix bytes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same(substr($scenario['wal_bytes'], 0, $scenario['expected_truncate_bytes']), $plan['wal_bytes_after']);
    };
    $tests[$prefix . 'keeps only preexisting frames after rollback'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['preexisting_frames'], $plan['wal_frame_count_after']);
    };
    $tests[$prefix . 'discards only current json batch frames'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['batch_frames'], $plan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'discards applied frame indexes after prefix'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $start = $scenario['preexisting_frames'] + 1;
        $t->same([$start, $start + 1], array_column($plan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'does not discard preexisting wal pages'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $discardedPages = $plan['wal_rollback_to_savepoint']['discarded_page_numbers'];
        foreach ($scenario['pre_savepoint_wal_pages'] as $pageNumber) {
            $t->same(false, in_array($pageNumber, $discardedPages, true));
        }
    };
    $tests[$prefix . 'restores only mutated current json pages'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'records malformed statement after prefix writes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $plan['failed_statements']);
        $t->same(1, $plan['failed_statement_count']);
    };
    $tests[$prefix . 'keeps applied statements before failure'] = static function (TestRunner $t) use ($plan): void {
        $t->same(2, $plan['applied_statement_count']);
    };
    $tests[$prefix . 'restores original database bytes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['database_bytes'], $plan['restored_database_bytes']);
    };
    $tests[$prefix . 'retains tenant id in applied rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($plan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'keeps JSONB mode on generated scenario'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $value = $plan['import_plan']['applied'][1]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
}

foreach ($tenantCollisionScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity tenant collision seed ' . $seed . ' ';

    $tests[$prefix . 'rolls back target tenant json batch'] = static function (TestRunner $t) use ($plan): void {
        $t->same('rolled_back_current_json_batch', $plan['status']);
    };
    $tests[$prefix . 'uses tenant collision transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_tenant_collision_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses tenant collision savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('tenant_collision_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'records shared key with target tenant setting key'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['target_tenant_id'] . ':' . $scenario['shared_key'], $plan['import_plan']['applied'][0]['setting_key']);
    };
    $tests[$prefix . 'does not apply stable tenant row with same key'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['target_tenant_id']], array_column($plan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'stable tenant row keeps original tenant id'] = static function (TestRunner $t) use ($scenario): void {
        $t->same($scenario['stable_tenant_id'], $scenario['stable_row_after_import']['tenant_id']);
    };
    $tests[$prefix . 'stable tenant row keeps shared key name'] = static function (TestRunner $t) use ($scenario): void {
        $t->same($scenario['shared_key'], $scenario['stable_row_after_import']['key_name']);
    };
    $tests[$prefix . 'stable tenant row keeps original page'] = static function (TestRunner $t) use ($scenario): void {
        $t->same($scenario['stable_page'], $scenario['stable_row_after_import']['page_number']);
    };
    $tests[$prefix . 'names failed target tenant statement'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $plan['failed_statements']);
    };
    $tests[$prefix . 'restores original database bytes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['database_bytes'], $plan['restored_database_bytes']);
    };
    $tests[$prefix . 'rolls wal back to header'] = static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan['wal_frame_count_after']);
    };
    $tests[$prefix . 'discards all current wal frames after failed tenant batch'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'restores only target tenant page'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'does not discard stable tenant page'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same(false, in_array($scenario['stable_page'], $plan['wal_rollback_to_savepoint']['discarded_page_numbers'], true));
    };
    $tests[$prefix . 'keeps jsonb mode isolated to target row'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $value = $plan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
}

foreach ($insertedSettingScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity inserted setting seed ' . $seed . ' ';

    $tests[$prefix . 'rolls back inserted json batch'] = static function (TestRunner $t) use ($plan): void {
        $t->same('rolled_back_current_json_batch', $plan['status']);
    };
    $tests[$prefix . 'uses inserted setting transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_inserted_setting_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses inserted setting savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('inserted_setting_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'applies base row and two inserted rows before failure'] = static function (TestRunner $t) use ($plan): void {
        $t->same(3, $plan['applied_statement_count']);
    };
    $tests[$prefix . 'records one failed malformed inserted batch statement'] = static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan['failed_statement_count']);
    };
    $tests[$prefix . 'names failed inserted batch statement'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $plan['failed_statements']);
    };
    $tests[$prefix . 'records inserted key names in applied rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['inserted_key_names'], array_slice(array_column($plan['import_plan']['applied'], 'key_name'), 1));
    };
    $tests[$prefix . 'marks inserted settings as inserted'] = static function (TestRunner $t) use ($plan): void {
        $t->same([false, true, true], array_column($plan['import_plan']['applied'], 'inserted_setting'));
    };
    $tests[$prefix . 'assigns deterministic inserted setting ids'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $finalRows = $plan['import_plan']['final_rows'];
        $inserted = array_values(array_filter(
            $finalRows,
            static fn (array $row): bool => in_array($row['key_name'], $scenario['inserted_key_names'], true)
        ));
        $t->same($scenario['inserted_setting_ids'], array_column($inserted, 'setting_id'));
    };
    $tests[$prefix . 'retains tenant id for inserted rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id'], $scenario['tenant_id']], array_column($plan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'restores original database bytes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['database_bytes'], $plan['restored_database_bytes']);
    };
    $tests[$prefix . 'marks database restored after inserted rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan['database_restored_to_before']);
    };
    $tests[$prefix . 'truncates wal to header'] = static function (TestRunner $t) use ($plan): void {
        $t->same(32, strlen($plan['wal_bytes_after']));
    };
    $tests[$prefix . 'has zero wal frames after rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan['wal_frame_count_after']);
    };
    $tests[$prefix . 'discards all current wal frames'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'restores base and inserted pages'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'wal rollback discards inserted page numbers'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'wal rollback discards three applied frames'] = static function (TestRunner $t) use ($plan): void {
        $t->same([1, 2, 3], array_column($plan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'keeps malformed statement rollback isolated to fourth frame'] = static function (TestRunner $t) use ($plan): void {
        $t->same([4], array_column($plan['import_plan']['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'preserves jsonb mode on first inserted row'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $value = $plan['import_plan']['applied'][1]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
}

foreach ($duplicateInsertedSettingScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity duplicate inserted setting seed ' . $seed . ' ';

    $tests[$prefix . 'rolls back duplicate inserted id batch'] = static function (TestRunner $t) use ($plan): void {
        $t->same('rolled_back_current_json_batch', $plan['status']);
    };
    $tests[$prefix . 'uses duplicate insert transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_duplicate_insert_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses duplicate insert savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('duplicate_insert_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'applies only base row before duplicate id failure'] = static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan['applied_statement_count']);
    };
    $tests[$prefix . 'records one failed duplicate id statement'] = static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan['failed_statement_count']);
    };
    $tests[$prefix . 'names failed duplicate id statement'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $plan['failed_statements']);
    };
    $tests[$prefix . 'reports duplicate id error'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_error'], $plan['import_plan']['failed'][0]['error']);
    };
    $tests[$prefix . 'failure happens before statement wal frame'] = static function (TestRunner $t) use ($plan): void {
        $t->same([], array_column($plan['import_plan']['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'failure happens before statement page image'] = static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan['import_plan']['failed'][0]['rollback']['restored_page_numbers']);
    };
    $tests[$prefix . 'restores original database bytes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['database_bytes'], $plan['restored_database_bytes']);
    };
    $tests[$prefix . 'outer rollback restores applied base page only'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'wal rollback discards applied base frame only'] = static function (TestRunner $t) use ($plan): void {
        $t->same([1], array_column($plan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'wal rollback discards applied base page only'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'duplicate insert page is not materialized'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same(false, in_array($scenario['duplicate_insert_page'], $plan['wal_rollback_to_savepoint']['discarded_page_numbers'], true));
    };
    $tests[$prefix . 'existing duplicate id row remains in final rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $rows = array_values(array_filter(
            $plan['import_plan']['final_rows'],
            static fn (array $row): bool => $row['setting_id'] === $scenario['duplicate_setting_id']
        ));
        $t->same(1, count($rows));
        $t->same($scenario['existing_insert_id_page'], $rows[0]['page_number']);
    };
    $tests[$prefix . 'failed inserted key is not retained in final rows'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same(false, in_array('duplicate_insert_new_payload_' . $seed, array_column($plan['import_plan']['final_rows'], 'key_name'), true));
    };
    $tests[$prefix . 'truncates wal to header after duplicate id rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(32, strlen($plan['wal_bytes_after']));
    };
    $tests[$prefix . 'discards all original wal frames from current batch stream'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['discarded_wal_frame_count']);
    };
}

foreach ($malformedInsertedInitialValueScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity malformed inserted initial value seed ' . $seed . ' ';

    $tests[$prefix . 'rolls back inserted initial value failure'] = static function (TestRunner $t) use ($plan): void {
        $t->same('rolled_back_current_json_batch', $plan['status']);
    };
    $tests[$prefix . 'uses malformed insert transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_malformed_insert_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses malformed insert savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('malformed_insert_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'applies only base row before malformed insert failure'] = static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan['applied_statement_count']);
    };
    $tests[$prefix . 'records one failed malformed insert statement'] = static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan['failed_statement_count']);
    };
    $tests[$prefix . 'names failed malformed insert statement'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $plan['failed_statements']);
    };
    $tests[$prefix . 'reports malformed inserted initial value error'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_error'], $plan['import_plan']['failed'][0]['error']);
    };
    $tests[$prefix . 'statement rollback discards staged insert frame'] = static function (TestRunner $t) use ($plan): void {
        $t->same([2], array_column($plan['import_plan']['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'statement rollback restores inserted page image'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['insert_page']], $plan['import_plan']['failed'][0]['rollback']['restored_page_numbers']);
    };
    $tests[$prefix . 'inserted setting is removed from final rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same(false, in_array($scenario['insert_setting_id'], array_column($plan['import_plan']['final_rows'], 'setting_id'), true));
    };
    $tests[$prefix . 'inserted key is removed from final rows'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same(false, in_array('malformed_insert_new_payload_' . $seed, array_column($plan['import_plan']['final_rows'], 'key_name'), true));
    };
    $tests[$prefix . 'restores original database bytes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['database_bytes'], $plan['restored_database_bytes']);
    };
    $tests[$prefix . 'outer rollback restores applied base page only'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'outer wal rollback discards applied base frame only'] = static function (TestRunner $t) use ($plan): void {
        $t->same([1], array_column($plan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'outer wal rollback does not discard failed insert page'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same(false, in_array($scenario['insert_page'], $plan['wal_rollback_to_savepoint']['discarded_page_numbers'], true));
    };
    $tests[$prefix . 'truncates wal to header after malformed insert rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(32, strlen($plan['wal_bytes_after']));
    };
    $tests[$prefix . 'has zero wal frames after malformed insert rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan['wal_frame_count_after']);
    };
    $tests[$prefix . 'discards all original wal frames from current batch stream'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'preserves jsonb mode selector in scenario'] = static function (TestRunner $t) use ($scenario): void {
        $t->same($scenario['seed'] % 2 === 1, $scenario['jsonb_mode']);
    };
}

foreach ($deferredScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity deferred seed ' . $seed . ' ';

    $tests[$prefix . 'keeps batch open after statement rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same('partial_rollback', $plan['status']);
    };
    $tests[$prefix . 'does not request batch rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(false, $plan['rollback_required']);
    };
    $tests[$prefix . 'uses deferred transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_deferred_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses deferred savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('deferred_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'applies two statements before failure'] = static function (TestRunner $t) use ($plan): void {
        $t->same(2, $plan['applied_statement_count']);
    };
    $tests[$prefix . 'records one failed statement'] = static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan['failed_statement_count']);
    };
    $tests[$prefix . 'names failed statement'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $plan['failed_statements']);
    };
    $tests[$prefix . 'keeps imported database bytes'] = static function (TestRunner $t) use ($plan): void {
        $t->same($plan['database_bytes_after_import'], $plan['restored_database_bytes']);
    };
    $tests[$prefix . 'does not restore database to before image'] = static function (TestRunner $t) use ($plan): void {
        $t->same(false, $plan['database_restored_to_before']);
    };
    $tests[$prefix . 'keeps original wal byte stream'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_bytes'], $plan['wal_bytes_after']);
    };
    $tests[$prefix . 'preserves wal frame count'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['wal_frame_count_after']);
    };
    $tests[$prefix . 'does not mark wal truncated'] = static function (TestRunner $t) use ($plan): void {
        $t->same(false, $plan['wal_truncated']);
    };
    $tests[$prefix . 'does not discard existing wal frames'] = static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'still records full savepoint rollback preview'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'keeps statement-level malformed rollback isolated'] = static function (TestRunner $t) use ($plan): void {
        $t->same([3], array_column($plan['import_plan']['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'retains tenant id in applied rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($plan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'keeps JSONB mode on generated scenario'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $value = $plan['import_plan']['applied'][1]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
}

foreach ($retryScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $failedPlan = $scenario['failed_plan'];
    $retryPlan = $scenario['retry_plan'];
    $materializedRetryPlan = $scenario['materialized_retry_plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity retry seed ' . $seed . ' ';

    $tests[$prefix . 'first batch rolls back current json writes'] = static function (TestRunner $t) use ($failedPlan): void {
        $t->same('rolled_back_current_json_batch', $failedPlan['status']);
    };
    $tests[$prefix . 'first batch restores original database bytes'] = static function (TestRunner $t) use ($failedPlan, $scenario): void {
        $t->same($scenario['database_bytes'], $failedPlan['restored_database_bytes']);
    };
    $tests[$prefix . 'first batch truncates wal to header'] = static function (TestRunner $t) use ($failedPlan): void {
        $t->same(32, strlen($failedPlan['wal_bytes_after']));
    };
    $tests[$prefix . 'first batch discards dynamic wal frames'] = static function (TestRunner $t) use ($failedPlan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $failedPlan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'retry starts from restored database bytes'] = static function (TestRunner $t) use ($failedPlan, $retryPlan): void {
        $t->same($failedPlan['restored_database_bytes'], $retryPlan['database_bytes_before']);
    };
    $tests[$prefix . 'retry starts from rolled back wal bytes'] = static function (TestRunner $t) use ($failedPlan, $retryPlan): void {
        $t->same($failedPlan['wal_bytes_after'], $retryPlan['wal_bytes_before']);
    };
    $tests[$prefix . 'retry succeeds without rollback request'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same('ready', $retryPlan['status']);
        $t->same(false, $retryPlan['rollback_required']);
    };
    $tests[$prefix . 'retry has no failed statements'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same([], $retryPlan['failed_statements']);
        $t->same(0, $retryPlan['failed_statement_count']);
    };
    $tests[$prefix . 'retry applies all corrected statements'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same(3, $retryPlan['applied_statement_count']);
    };
    $tests[$prefix . 'retry mutates database image'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same(true, $retryPlan['database_changed_before_rollback']);
        $t->same(false, $retryPlan['database_restored_to_before']);
    };
    $tests[$prefix . 'retry keeps wal header after successful import'] = static function (TestRunner $t) use ($failedPlan, $retryPlan): void {
        $t->same($failedPlan['wal_bytes_after'], $retryPlan['wal_bytes_after']);
    };
    $tests[$prefix . 'retry records three applied page numbers'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same($scenario['expected_retry_pages'], array_column($retryPlan['import_plan']['applied'], 'page_number'));
    };
    $tests[$prefix . 'retry records ordered wal frame indexes'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same([1, 2, 3], array_column($retryPlan['import_plan']['applied'], 'wal_frame_index'));
    };
    $tests[$prefix . 'retry keeps tenant isolation'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id'], $scenario['tenant_id']], array_column($retryPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'retry savepoint rollback preview covers corrected pages'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same($scenario['expected_retry_pages'], $retryPlan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'retry wal rollback preview covers corrected frames'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same([1, 2, 3], array_column($retryPlan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'retry preserves jsonb mode on catalog row'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $value = $retryPlan['import_plan']['applied'][1]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'retry fixes formerly malformed payload row'] = static function (TestRunner $t) use ($retryPlan, $seed): void {
        $t->same('retry_mark_fixed_payload_' . $seed, $retryPlan['import_plan']['applied'][2]['statement']);
    };
    $tests[$prefix . 'materialized retry appends corrected wal frames'] = static function (TestRunner $t) use ($materializedRetryPlan): void {
        $t->same(3, $materializedRetryPlan['materialized_wal_frame_count']);
        $t->same(3, $materializedRetryPlan['wal_frame_count_after']);
    };
    $tests[$prefix . 'materialized retry keeps rollback header prefix'] = static function (TestRunner $t) use ($failedPlan, $materializedRetryPlan): void {
        $t->same($failedPlan['wal_bytes_after'], substr($materializedRetryPlan['wal_bytes_after'], 0, strlen($failedPlan['wal_bytes_after'])));
    };
    $tests[$prefix . 'materialized retry frame bytes include corrected pages'] = static function (TestRunner $t) use ($materializedRetryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        foreach ($scenario['expected_retry_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + ($index * $frameSize);
            $frameHeader = unpack('Npage_number', substr($materializedRetryPlan['wal_bytes_after'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'materialized retry writes chained wal checksums'] = static function (TestRunner $t) use ($materializedRetryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($materializedRetryPlan['wal_bytes_after'], 0, 24), false);
        foreach ($scenario['expected_retry_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + ($index * $frameSize);
            $frame = substr($materializedRetryPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frame, 0, 24));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
            $t->same($checksumSeed, [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
    $tests[$prefix . 'materialized retry marks only final wal frame as commit'] = static function (TestRunner $t) use ($materializedRetryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        foreach ($scenario['expected_retry_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + ($index * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($materializedRetryPlan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, 0, intdiv(strlen($materializedRetryPlan['database_bytes_after_import']), $pageSize)], $commits);
    };
    $tests[$prefix . 'materialized retry commit marker matches imported database page count'] = static function (TestRunner $t) use ($materializedRetryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $lastFrameOffset = 32 + ((count($scenario['expected_retry_pages']) - 1) * $frameSize);
        $frameHeader = unpack('Npage_number/Ncommit', substr($materializedRetryPlan['wal_bytes_after'], $lastFrameOffset, 8));
        $t->same(intdiv(strlen($materializedRetryPlan['database_bytes_after_import']), $pageSize), (int) $frameHeader['commit']);
        $t->same($scenario['expected_retry_pages'][2], (int) $frameHeader['page_number']);
    };
}

foreach ($fullRunMaterializedWalScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $failedPlan = $scenario['failed_plan'];
    $retryPlan = $scenario['retry_plan'];
    $followupPlan = $scenario['followup_plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity full-run materialized wal seed ' . $seed . ' ';

    $tests[$prefix . 'first batch rolls back current json frames'] = static function (TestRunner $t) use ($failedPlan): void {
        $t->same('rolled_back_current_json_batch', $failedPlan['status']);
        $t->same(true, $failedPlan['rollback_required']);
    };
    $tests[$prefix . 'first batch preserves only preexisting wal prefix'] = static function (TestRunner $t) use ($failedPlan, $scenario): void {
        $t->same($scenario['preexisting_frames'], $failedPlan['wal_frame_count_after']);
        $t->same(substr($scenario['wal_bytes'], 0, strlen($failedPlan['wal_bytes_after'])), $failedPlan['wal_bytes_after']);
    };
    $tests[$prefix . 'retry succeeds and materializes three frames'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same('ready', $retryPlan['status']);
        $t->same(3, $retryPlan['materialized_wal_frame_count']);
        $t->same($scenario['preexisting_frames'] + 3, $retryPlan['wal_frame_count_after']);
    };
    $tests[$prefix . 'retry starts from restored database and preserved wal'] = static function (TestRunner $t) use ($failedPlan, $retryPlan): void {
        $t->same($failedPlan['restored_database_bytes'], $retryPlan['database_bytes_before']);
        $t->same($failedPlan['wal_bytes_after'], $retryPlan['wal_bytes_before']);
    };
    $tests[$prefix . 'retry records expected page order'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same($scenario['expected_retry_pages'], array_column($retryPlan['import_plan']['applied'], 'page_number'));
    };
    $tests[$prefix . 'followup starts from materialized retry wal'] = static function (TestRunner $t) use ($retryPlan, $followupPlan): void {
        $t->same($retryPlan['database_bytes_after_import'], $followupPlan['database_bytes_before']);
        $t->same($retryPlan['wal_bytes_after'], $followupPlan['wal_bytes_before']);
    };
    $tests[$prefix . 'followup succeeds and appends two frames'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same('ready', $followupPlan['status']);
        $t->same(2, $followupPlan['materialized_wal_frame_count']);
        $t->same($scenario['preexisting_frames'] + 5, $followupPlan['wal_frame_count_after']);
    };
    $tests[$prefix . 'followup preserves retry wal byte prefix'] = static function (TestRunner $t) use ($retryPlan, $followupPlan): void {
        $t->same($retryPlan['wal_bytes_after'], substr($followupPlan['wal_bytes_after'], 0, strlen($retryPlan['wal_bytes_after'])));
    };
    $tests[$prefix . 'followup applies catalog and final pages'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same($scenario['expected_followup_pages'], array_column($followupPlan['import_plan']['applied'], 'page_number'));
        $t->same($scenario['expected_final_key'], $followupPlan['import_plan']['applied'][1]['key_name']);
    };
    $tests[$prefix . 'followup uses contiguous wal frame indexes after retry'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $start = $scenario['preexisting_frames'] + 4;
        $t->same([$start, $start + 1], array_column($followupPlan['import_plan']['applied'], 'wal_frame_index'));
    };
    $tests[$prefix . 'followup rollback preview begins after retry frames'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same($scenario['preexisting_frames'] + 3, $followupPlan['wal_rollback_to_savepoint']['rollback_to_frame']);
        $t->same($scenario['expected_followup_pages'], $followupPlan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'followup tenant isolation remains intact'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($followupPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'followup keeps jsonb mode on catalog row'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $value = $followupPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'followup frame bytes append after retry frames'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        foreach ($scenario['expected_followup_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['preexisting_frames'] + 3 + $index) * $frameSize);
            $frameHeader = unpack('Npage_number', substr($followupPlan['wal_bytes_after'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'followup checksums continue after retry frames'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($followupPlan['wal_bytes_after'], 0, 24), false);
        for ($frameIndex = 0; $frameIndex < $scenario['preexisting_frames'] + 3; $frameIndex++) {
            $frameOffset = 32 + ($frameIndex * $frameSize);
            $frame = substr($followupPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
        }
        foreach ($scenario['expected_followup_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['preexisting_frames'] + 3 + $index) * $frameSize);
            $frame = substr($followupPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frame, 0, 24));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
            $t->same($checksumSeed, [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
    $tests[$prefix . 'followup marks only final appended frame as commit'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        foreach ($scenario['expected_followup_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + (($scenario['preexisting_frames'] + 3 + $index) * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($followupPlan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, intdiv(strlen($followupPlan['database_bytes_after_import']), $pageSize)], $commits);
    };
}

foreach ($committedPrefixFailureScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $tailPlan = $scenario['tail_plan'];
    $retryPlan = $scenario['retry_plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity committed prefix failure seed ' . $seed . ' ';

    $tests[$prefix . 'rolls back only the failed tail batch'] = static function (TestRunner $t) use ($tailPlan): void {
        $t->same('rolled_back_current_json_batch', $tailPlan['status']);
        $t->same(true, $tailPlan['rollback_required']);
    };
    $tests[$prefix . 'preserves materialized retry wal prefix'] = static function (TestRunner $t) use ($tailPlan, $retryPlan): void {
        $t->same($retryPlan['wal_frame_count_after'], $tailPlan['wal_frame_count_after']);
        $t->same($retryPlan['wal_bytes_after'], $tailPlan['wal_bytes_after']);
    };
    $tests[$prefix . 'truncates to committed prefix byte boundary'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $expectedBytes = 32 + ($scenario['committed_prefix_frame_count'] * (24 + $scenario['page_size']));
        $t->same($expectedBytes, $tailPlan['wal_truncate_to_bytes']);
        $t->same($expectedBytes, strlen($tailPlan['wal_bytes_after']));
    };
    $tests[$prefix . 'discards only tail frames after committed retry'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $discardedFrames = array_column($tailPlan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index');
        $start = $scenario['committed_prefix_frame_count'] + 1;
        $t->same([$start, $start + 1], $discardedFrames);
        $t->same($scenario['expected_tail_pages'], $tailPlan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'restores only tail-mutated pages'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['expected_tail_pages'], $tailPlan['rollback_to_savepoint']['restored_page_numbers']);
        $t->same([], $tailPlan['rollback_to_savepoint']['missing_page_numbers']);
    };
    $tests[$prefix . 'records malformed final statement failure'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $tailPlan['failed_statements']);
        $t->same('SQLite JSON5 input ended before a value', $tailPlan['import_plan']['failed'][0]['error']);
    };
    $tests[$prefix . 'keeps retry database image after rollback'] = static function (TestRunner $t) use ($tailPlan, $retryPlan): void {
        $t->same($retryPlan['database_bytes_after_import'], $tailPlan['restored_database_bytes']);
        $t->same(true, $tailPlan['database_restored_to_before']);
    };
    $tests[$prefix . 'tail import changed database before rollback'] = static function (TestRunner $t) use ($tailPlan): void {
        $t->same(true, $tailPlan['database_changed_before_rollback']);
        $t->same(true, $tailPlan['database_bytes_after_import'] !== $tailPlan['database_bytes_before']);
    };
    $tests[$prefix . 'applies catalog update and inserted audit before failure'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same(2, $tailPlan['applied_statement_count']);
        $t->same($scenario['expected_tail_pages'], array_column($tailPlan['import_plan']['applied'], 'page_number'));
    };
    $tests[$prefix . 'does not count malformed statement as applied frame'] = static function (TestRunner $t) use ($tailPlan): void {
        $t->same(1, $tailPlan['failed_statement_count']);
        $t->same(2, count($tailPlan['wal_rollback_to_savepoint']['discarded_wal_frames']));
    };
    $tests[$prefix . 'tail wal bytes contain one additional malformed frame before rollback'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['committed_prefix_frame_count'] + 3, $tailPlan['wal_frame_count_before']);
        $t->same($scenario['committed_prefix_frame_count'], $tailPlan['wal_frame_count_after']);
    };
    $tests[$prefix . 'committed prefix pages are carried into savepoint boundary'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['committed_prefix_frame_count'], $tailPlan['wal_rollback_to_savepoint']['rollback_to_frame']);
        $transactionPages = $tailPlan['import_plan']['savepoint_state'][0]['page_numbers'];
        $expectedPages = $scenario['committed_prefix_pages'];
        sort($transactionPages);
        sort($expectedPages);
        $t->same($expectedPages, $transactionPages);
    };
}

foreach ($preexistingRetryScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $failedPlan = $scenario['failed_plan'];
    $retryPlan = $scenario['retry_plan'];
    $materializedRetryPlan = $scenario['materialized_retry_plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity preexisting retry seed ' . $seed . ' ';

    $tests[$prefix . 'first batch rolls back current json writes'] = static function (TestRunner $t) use ($failedPlan): void {
        $t->same('rolled_back_current_json_batch', $failedPlan['status']);
    };
    $tests[$prefix . 'first batch preserves preexisting wal frame count'] = static function (TestRunner $t) use ($failedPlan, $scenario): void {
        $t->same($scenario['preexisting_frames'], $failedPlan['wal_frame_count_after']);
    };
    $tests[$prefix . 'first batch truncates only after prefix'] = static function (TestRunner $t) use ($failedPlan, $scenario): void {
        $t->same($scenario['expected_truncate_bytes'], $failedPlan['wal_truncate_to_bytes']);
        $t->same($scenario['expected_truncate_bytes'], strlen($failedPlan['wal_bytes_after']));
    };
    $tests[$prefix . 'first batch keeps committed prefix bytes'] = static function (TestRunner $t) use ($failedPlan, $scenario): void {
        $t->same(substr($scenario['wal_bytes'], 0, $scenario['expected_truncate_bytes']), $failedPlan['wal_bytes_after']);
    };
    $tests[$prefix . 'first batch discards only current json frames'] = static function (TestRunner $t) use ($failedPlan): void {
        $t->same(3, $failedPlan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'retry starts from restored database bytes'] = static function (TestRunner $t) use ($failedPlan, $retryPlan): void {
        $t->same($failedPlan['restored_database_bytes'], $retryPlan['database_bytes_before']);
    };
    $tests[$prefix . 'retry starts from preserved wal prefix'] = static function (TestRunner $t) use ($failedPlan, $retryPlan): void {
        $t->same($failedPlan['wal_bytes_after'], $retryPlan['wal_bytes_before']);
    };
    $tests[$prefix . 'retry succeeds without rollback request'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same('ready', $retryPlan['status']);
        $t->same(false, $retryPlan['rollback_required']);
    };
    $tests[$prefix . 'retry leaves prefix wal byte stream intact'] = static function (TestRunner $t) use ($failedPlan, $retryPlan): void {
        $t->same($failedPlan['wal_bytes_after'], $retryPlan['wal_bytes_after']);
    };
    $tests[$prefix . 'retry reports prefix frame count after success'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same($scenario['preexisting_frames'], $retryPlan['wal_frame_count_after']);
    };
    $tests[$prefix . 'retry savepoint rollback preview starts after prefix'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same($scenario['preexisting_frames'], $retryPlan['wal_rollback_to_savepoint']['rollback_to_frame']);
    };
    $tests[$prefix . 'retry records corrected frame indexes after prefix'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $start = $scenario['preexisting_frames'] + 1;
        $t->same([$start, $start + 1, $start + 2], array_column($retryPlan['import_plan']['applied'], 'wal_frame_index'));
    };
    $tests[$prefix . 'retry savepoint rollback preview covers corrected pages'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same($scenario['expected_retry_pages'], $retryPlan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'retry wal rollback preview covers corrected pages'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same($scenario['expected_retry_pages'], $retryPlan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'retry keeps tenant isolation'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id'], $scenario['tenant_id']], array_column($retryPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'retry preserves jsonb mode on catalog row'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $value = $retryPlan['import_plan']['applied'][1]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'retry fixes formerly malformed payload row'] = static function (TestRunner $t) use ($retryPlan, $seed): void {
        $t->same('prefix_retry_fixed_payload_success_' . $seed, $retryPlan['import_plan']['applied'][2]['statement']);
    };
    $tests[$prefix . 'materialized retry appends after preserved prefix'] = static function (TestRunner $t) use ($materializedRetryPlan, $scenario): void {
        $t->same(3, $materializedRetryPlan['materialized_wal_frame_count']);
        $t->same($scenario['preexisting_frames'] + 3, $materializedRetryPlan['wal_frame_count_after']);
    };
    $tests[$prefix . 'materialized retry preserves prefix bytes'] = static function (TestRunner $t) use ($failedPlan, $materializedRetryPlan): void {
        $t->same($failedPlan['wal_bytes_after'], substr($materializedRetryPlan['wal_bytes_after'], 0, strlen($failedPlan['wal_bytes_after'])));
    };
    $tests[$prefix . 'materialized retry frame bytes continue after prefix'] = static function (TestRunner $t) use ($materializedRetryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        foreach ($scenario['expected_retry_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['preexisting_frames'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number', substr($materializedRetryPlan['wal_bytes_after'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'materialized retry checksums continue after prefix'] = static function (TestRunner $t) use ($materializedRetryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($materializedRetryPlan['wal_bytes_after'], 0, 24), false);
        for ($frameIndex = 0; $frameIndex < $scenario['preexisting_frames']; $frameIndex++) {
            $frameOffset = 32 + ($frameIndex * $frameSize);
            $frame = substr($materializedRetryPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
        }
        foreach ($scenario['expected_retry_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['preexisting_frames'] + $index) * $frameSize);
            $frame = substr($materializedRetryPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frame, 0, 24));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
            $t->same($checksumSeed, [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
    $tests[$prefix . 'materialized retry marks only final appended frame as commit'] = static function (TestRunner $t) use ($materializedRetryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        foreach ($scenario['expected_retry_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + (($scenario['preexisting_frames'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($materializedRetryPlan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, 0, intdiv(strlen($materializedRetryPlan['database_bytes_after_import']), $pageSize)], $commits);
    };
    $tests[$prefix . 'materialized retry final commit follows preserved prefix'] = static function (TestRunner $t) use ($materializedRetryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $lastFrameOffset = 32 + (($scenario['preexisting_frames'] + count($scenario['expected_retry_pages']) - 1) * $frameSize);
        $frameHeader = unpack('Npage_number/Ncommit', substr($materializedRetryPlan['wal_bytes_after'], $lastFrameOffset, 8));
        $t->same($scenario['expected_retry_pages'][2], (int) $frameHeader['page_number']);
        $t->same(intdiv(strlen($materializedRetryPlan['database_bytes_after_import']), $pageSize), (int) $frameHeader['commit']);
    };
}

foreach ($missingWalTailScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $prefix = 'sqlite application wal rollback json dynamic parity missing wal tail seed ' . $seed . ' ';

    $tests[$prefix . 'rejects truncated current batch wal bytes'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(
            'SQLite Application JSON import rollback WAL bytes are missing current batch frame(s): ' . implode(', ', $scenario['missing_frame_indexes']),
            $scenario['exception_message']
        );
    };
    $tests[$prefix . 'keeps committed prefix frame count in short wal'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['short_frame_count'] >= $scenario['preexisting_frames']);
    };
    $tests[$prefix . 'short wal is still frame aligned'] = static function (TestRunner $t) use ($scenario): void {
        $frameSize = 24 + $scenario['page_size'];
        $t->same(0, (strlen($scenario['short_wal_bytes']) - 32) % $frameSize);
    };
    $tests[$prefix . 'short wal frame count reflects missing tail'] = static function (TestRunner $t) use ($scenario): void {
        $t->same($scenario['expected_frame_count'] - $scenario['missing_frames'], $scenario['short_frame_count']);
    };
}

foreach ($partialWalTailScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $prefix = 'sqlite application wal rollback json dynamic parity partial wal tail seed ' . $seed . ' ';

    $tests[$prefix . 'rejects unaligned wal bytes'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(
            'SQLite Application JSON import rollback WAL bytes have a partial frame tail',
            $scenario['exception_message']
        );
    };
    $tests[$prefix . 'keeps at least one current batch frame before partial tail'] = static function (TestRunner $t) use ($scenario): void {
        $t->same((int) $scenario['preexisting_frames'] + 1, $scenario['complete_frame_count']);
    };
    $tests[$prefix . 'partial wal has frame remainder'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(
            $scenario['partial_payload_bytes'],
            (strlen($scenario['partial_wal_bytes']) - 32) % $scenario['frame_size']
        );
    };
    $tests[$prefix . 'partial wal tail is not full frame sized'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['partial_payload_bytes'] > 0);
        $t->same(true, $scenario['partial_payload_bytes'] < $scenario['frame_size']);
    };
}

foreach ($frameHeaderMismatchScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $prefix = 'sqlite application wal rollback json dynamic parity frame-header mismatch seed ' . $seed . ' ';

    $tests[$prefix . 'rejects corrupt frame header'] = static function (TestRunner $t) use ($scenario): void {
        $expected = $scenario['corruption'] === 'zero_page'
            ? 'SQLite Application JSON import rollback WAL frame ' . $scenario['target_frame'] . ' has an invalid page number'
            : 'SQLite Application JSON import rollback WAL frame ' . $scenario['target_frame'] . ' salt does not match the WAL header';
        $t->same($expected, $scenario['exception_message']);
    };
    $tests[$prefix . 'targets first current-batch frame after prefix'] = static function (TestRunner $t) use ($scenario): void {
        $t->same((int) $scenario['preexisting_frames'] + 1, $scenario['target_frame']);
    };
    $tests[$prefix . 'keeps corrupt wal frame aligned'] = static function (TestRunner $t) use ($scenario): void {
        $frameSize = 24 + $scenario['page_size'];
        $t->same(0, (strlen($scenario['corrupt_wal_bytes']) - 32) % $frameSize);
    };
    $tests[$prefix . 'records deterministic frame header offset'] = static function (TestRunner $t) use ($scenario): void {
        $frameSize = 24 + $scenario['page_size'];
        $t->same(32 + (($scenario['target_frame'] - 1) * $frameSize), $scenario['frame_offset']);
    };
}

foreach ($frameChecksumMismatchScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $prefix = 'sqlite application wal rollback json dynamic parity frame-checksum mismatch seed ' . $seed . ' ';

    $tests[$prefix . 'rejects corrupt frame checksum'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(
            'SQLite Application JSON import rollback WAL frame ' . $scenario['target_frame'] . ' checksum does not match the frame payload',
            $scenario['exception_message']
        );
    };
    $tests[$prefix . 'targets first current-batch frame after prefix'] = static function (TestRunner $t) use ($scenario): void {
        $t->same((int) $scenario['preexisting_frames'] + 1, $scenario['target_frame']);
    };
    $tests[$prefix . 'keeps corrupt wal frame aligned'] = static function (TestRunner $t) use ($scenario): void {
        $frameSize = 24 + $scenario['page_size'];
        $t->same(0, (strlen($scenario['corrupt_wal_bytes']) - 32) % $frameSize);
    };
    $tests[$prefix . 'records checksum field offset inside target frame'] = static function (TestRunner $t) use ($scenario): void {
        $frameSize = 24 + $scenario['page_size'];
        $frameOffset = 32 + (($scenario['target_frame'] - 1) * $frameSize);
        $t->same(true, $scenario['checksum_offset'] === $frameOffset + 16 || $scenario['checksum_offset'] === $frameOffset + 20);
    };
}

foreach ($headerChecksumMismatchScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $prefix = 'sqlite application wal rollback json dynamic parity header-checksum mismatch seed ' . $seed . ' ';

    $tests[$prefix . 'rejects corrupt wal header checksum'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(
            'SQLite Application JSON import rollback WAL header checksum does not match the header content',
            $scenario['exception_message']
        );
    };
    $tests[$prefix . 'keeps corrupt wal frame aligned'] = static function (TestRunner $t) use ($scenario): void {
        $frameSize = 24 + $scenario['page_size'];
        $t->same(0, (strlen($scenario['corrupt_wal_bytes']) - 32) % $frameSize);
    };
    $tests[$prefix . 'corrupts only the header checksum area'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['checksum_offset'] === 24 || $scenario['checksum_offset'] === 28);
    };
    $tests[$prefix . 'preserves full wal frame count before rejection'] = static function (TestRunner $t) use ($scenario): void {
        $frameSize = 24 + $scenario['page_size'];
        $t->same($scenario['wal_frames_before'], intdiv(strlen($scenario['corrupt_wal_bytes']) - 32, $frameSize));
    };
}

$tests['sqlite application wal rollback json dynamic parity rejects zero scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicParityScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero deferred scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicDeferredFailureScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero preexisting wal scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero tenant collision scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicTenantCollisionScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero inserted setting scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicInsertedSettingRollbackScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero duplicate inserted setting scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicDuplicateInsertedSettingRollbackScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero malformed inserted initial value scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicMalformedInsertedInitialValueScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero retry scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicRetryAfterRollbackScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero preexisting retry scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalRetryScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero missing wal tail scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicMissingWalTailScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero partial wal tail scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicPartialWalTailScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero frame-header mismatch scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicFrameHeaderMismatchScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero frame-checksum mismatch scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicFrameChecksumMismatchScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero header-checksum mismatch scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicHeaderChecksumMismatchScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero full-run materialized wal scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicFullRunMaterializedWalScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero committed prefix failure scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicCommittedPrefixFailureScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero rollback-disabled materialized wal scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledMaterializedWalScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero rollback-disabled followup scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero rollback-disabled followup failure scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupFailureScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero rollback-disabled followup recovery scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupRecoveryScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero rollback-disabled post-recovery failure scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledPostRecoveryFailureScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero rollback-disabled post-recovery recovery scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledPostRecoveryRecoveryScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero post-recovery checkpoint scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicPostRecoveryCheckpointScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero post-checkpoint followup scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointFollowupScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity explicit small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicParityScenarios(3);
    $t->same([101, 102, 103], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([6, 7, 8], array_column($smallBatch, 'wal_frames_before'));
};

$tests['sqlite application wal rollback json dynamic parity deferred small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicDeferredFailureScenarios(3);
    $t->same([701, 702, 703], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([5, 6, 7], array_column($smallBatch, 'wal_frames_before'));
};

$tests['sqlite application wal rollback json dynamic parity preexisting wal small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalScenarios(3);
    $t->same([1201, 1202, 1203], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([3, 4, 5], array_column($smallBatch, 'preexisting_frames'));
    $t->same([6, 7, 8], array_column($smallBatch, 'wal_frames_before'));
};

$tests['sqlite application wal rollback json dynamic parity tenant collision small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicTenantCollisionScenarios(3);
    $t->same([2101, 2102, 2103], array_column($smallBatch, 'target_tenant_id'));
    $t->same([3101, 3102, 3103], array_column($smallBatch, 'stable_tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
};

$tests['sqlite application wal rollback json dynamic parity inserted setting small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicInsertedSettingRollbackScenarios(3);
    $t->same([4101, 4102, 4103], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([[5003, 5004], [10003, 10004], [15003, 15004]], array_column($smallBatch, 'inserted_setting_ids'));
};

$tests['sqlite application wal rollback json dynamic parity duplicate inserted setting small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicDuplicateInsertedSettingRollbackScenarios(3);
    $t->same([5101, 5102, 5103], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([6002, 12002, 18002], array_column($smallBatch, 'duplicate_setting_id'));
};

$tests['sqlite application wal rollback json dynamic parity malformed inserted initial value small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicMalformedInsertedInitialValueScenarios(3);
    $t->same([6101, 6102, 6103], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([7002, 14002, 21002], array_column($smallBatch, 'insert_setting_id'));
};

$tests['sqlite application wal rollback json dynamic parity retry small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicRetryAfterRollbackScenarios(3);
    $t->same([901, 902, 903], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([7, 8, 9], array_column($smallBatch, 'wal_frames_before'));
};

$tests['sqlite application wal rollback json dynamic parity preexisting retry small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalRetryScenarios(3);
    $t->same([1501, 1502, 1503], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([2, 3, 4], array_column($smallBatch, 'preexisting_frames'));
};

$tests['sqlite application wal rollback json dynamic parity missing wal tail small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicMissingWalTailScenarios(3);
    $t->same([1201, 1202, 1203], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([4, 6, 6], array_column($smallBatch, 'short_frame_count'));
};

$tests['sqlite application wal rollback json dynamic parity partial wal tail small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPartialWalTailScenarios(3);
    $t->same([1201, 1202, 1203], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([4, 5, 6], array_column($smallBatch, 'complete_frame_count'));
};

$tests['sqlite application wal rollback json dynamic parity frame-header mismatch small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicFrameHeaderMismatchScenarios(4);
    $t->same([1201, 1202, 1203, 1204], array_column($smallBatch, 'tenant_id'));
    $t->same([4, 5, 6, 3], array_column($smallBatch, 'target_frame'));
    $t->same(['salt_mismatch', 'zero_page', 'salt_mismatch', 'zero_page'], array_column($smallBatch, 'corruption'));
};

$tests['sqlite application wal rollback json dynamic parity frame-checksum mismatch small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicFrameChecksumMismatchScenarios(4);
    $t->same([1201, 1202, 1203, 1204], array_column($smallBatch, 'tenant_id'));
    $t->same([4, 5, 6, 3], array_column($smallBatch, 'target_frame'));
    $t->same([512, 1024, 512, 1024], array_column($smallBatch, 'page_size'));
};

$tests['sqlite application wal rollback json dynamic parity header-checksum mismatch small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicHeaderChecksumMismatchScenarios(4);
    $t->same([1201, 1202, 1203, 1204], array_column($smallBatch, 'tenant_id'));
    $t->same([28, 24, 28, 24], array_column($smallBatch, 'checksum_offset'));
    $t->same([6, 7, 8, 5], array_column($smallBatch, 'wal_frames_before'));
};

$tests['sqlite application wal rollback json dynamic parity full-run materialized wal small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicFullRunMaterializedWalScenarios(4);
    $t->same([7101, 7102, 7103, 7104], array_column($smallBatch, 'tenant_id'));
    $t->same([2, 3, 4, 1], array_column($smallBatch, 'preexisting_frames'));
    $t->same([[51, 721, 821], [52, 722, 822], [53, 723, 823], [54, 724, 824]], array_column($smallBatch, 'expected_retry_pages'));
    $t->same([[721, 1021], [722, 1022], [723, 1023], [724, 1024]], array_column($smallBatch, 'expected_followup_pages'));
};

$tests['sqlite application wal rollback json dynamic parity committed prefix failure small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicCommittedPrefixFailureScenarios(4);
    $t->same([7101, 7102, 7103, 7104], array_column($smallBatch, 'tenant_id'));
    $t->same([5, 6, 7, 4], array_column($smallBatch, 'committed_prefix_frame_count'));
    $t->same([[721, 1221], [722, 1222], [723, 1223], [724, 1224]], array_column($smallBatch, 'expected_tail_pages'));
};

$tests['sqlite application wal rollback json dynamic parity rollback-disabled materialized wal small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledMaterializedWalScenarios(4);
    $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
    $t->same([2, 3, 4, 1], array_column($smallBatch, 'preexisting_frames'));
    $t->same([[63, 1321], [64, 1322], [65, 1323], [66, 1324]], array_column($smallBatch, 'expected_applied_pages'));
    $t->same([4, 5, 6, 3], array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $smallBatch));
};

$tests['sqlite application wal rollback json dynamic parity rollback-disabled followup small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupScenarios(4);
    $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
    $t->same([4, 5, 6, 3], array_column($smallBatch, 'partial_frame_count'));
    $t->same([[1321, 1521], [1322, 1522], [1323, 1523], [1324, 1524]], array_column($smallBatch, 'expected_followup_pages'));
    $t->same([6, 7, 8, 5], array_map(static fn (array $scenario): int => $scenario['followup_plan']['wal_frame_count_after'], $smallBatch));
};

$tests['sqlite application wal rollback json dynamic parity rollback-disabled followup failure small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupFailureScenarios(4);
    $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
    $t->same([6, 7, 8, 5], array_column($smallBatch, 'committed_prefix_frame_count'));
    $t->same([[1321, 1621], [1322, 1622], [1323, 1623], [1324, 1624]], array_column($smallBatch, 'expected_tail_pages'));
    $t->same([9, 10, 11, 8], array_map(static fn (array $scenario): int => $scenario['tail_plan']['wal_frame_count_before'], $smallBatch));
};

$tests['sqlite application wal rollback json dynamic parity rollback-disabled followup recovery small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupRecoveryScenarios(4);
    $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
    $t->same([6, 7, 8, 5], array_column($smallBatch, 'committed_prefix_frame_count'));
    $t->same([[1321, 1721], [1322, 1722], [1323, 1723], [1324, 1724]], array_column($smallBatch, 'expected_recovery_pages'));
    $t->same([8, 9, 10, 7], array_map(static fn (array $scenario): int => $scenario['recovery_plan']['wal_frame_count_after'], $smallBatch));
};

$tests['sqlite application wal rollback json dynamic parity rollback-disabled post-recovery failure small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledPostRecoveryFailureScenarios(4);
    $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
    $t->same([8, 9, 10, 7], array_column($smallBatch, 'committed_prefix_frame_count'));
    $t->same([[1321, 1821], [1322, 1822], [1323, 1823], [1324, 1824]], array_column($smallBatch, 'expected_post_recovery_pages'));
    $t->same([11, 12, 13, 10], array_map(static fn (array $scenario): int => $scenario['post_recovery_failure_plan']['wal_frame_count_before'], $smallBatch));
};

$tests['sqlite application wal rollback json dynamic parity rollback-disabled post-recovery recovery small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledPostRecoveryRecoveryScenarios(4);
    $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
    $t->same([8, 9, 10, 7], array_column($smallBatch, 'committed_prefix_frame_count'));
    $t->same([[1321, 1921], [1322, 1922], [1323, 1923], [1324, 1924]], array_column($smallBatch, 'expected_recovery_pages'));
    $t->same([10, 11, 12, 9], array_map(static fn (array $scenario): int => $scenario['post_recovery_recovery_plan']['wal_frame_count_after'], $smallBatch));
};

$tests['sqlite application wal rollback json dynamic parity post-recovery checkpoint small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPostRecoveryCheckpointScenarios(4);
    $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
    $t->same(['restart', 'truncate', 'restart', 'truncate'], array_column($smallBatch, 'checkpoint_mode'));
    $t->same(['restart_wal', 'truncate_wal', 'restart_wal', 'truncate_wal'], array_column($smallBatch, 'expected_checkpoint_action'));
    $t->same([10, 11, 12, 9], array_map(static fn (array $scenario): int => $scenario['checkpoint_plan']['last_commit_frame'], $smallBatch));
};

$tests['sqlite application wal rollback json dynamic parity post-checkpoint followup small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointFollowupScenarios(4);
    $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
    $t->same(['restart', 'truncate', 'restart', 'truncate'], array_column($smallBatch, 'checkpoint_mode'));
    $t->same([false, true, false, true], array_column($smallBatch, 'post_checkpoint_started_new_wal_header'));
    $t->same([[1321, 2021], [1322, 2022], [1323, 2023], [1324, 2024]], array_column($smallBatch, 'expected_followup_pages'));
    $t->same([2, 2, 2, 2], array_map(static fn (array $scenario): int => $scenario['post_checkpoint_followup_plan']['wal_frame_count_after'], $smallBatch));
};

$tests['sqlite application wal rollback json dynamic parity rejects wal header page size mismatch'] = static function (TestRunner $t) use ($scenarios): void {
    $scenario = $scenarios[0];
    $walBytes = substr_replace(
        $scenario['wal_bytes'],
        pack('N', 1024),
        8,
        4
    );

    try {
        SQLiteJsonImportRollbackWalPlan::plan(
            [],
            [],
            [
                'database_bytes' => $scenario['database_bytes'],
                'page_size' => 512,
                'wal_bytes' => $walBytes,
            ]
        );
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite Application JSON import rollback WAL page size must match the database page size', $exception->getMessage());
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects invalid wal magic'] = static function (TestRunner $t) use ($scenarios): void {
    $scenario = $scenarios[0];
    $walBytes = substr_replace(
        $scenario['wal_bytes'],
        pack('N', 0x12345678),
        0,
        4
    );

    try {
        SQLiteJsonImportRollbackWalPlan::plan(
            [],
            [],
            [
                'database_bytes' => $scenario['database_bytes'],
                'page_size' => $scenario['page_size'],
                'wal_bytes' => $walBytes,
            ]
        );
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite Application JSON import rollback WAL bytes require a valid WAL header', $exception->getMessage());
        return;
    }

    $t->same('rejected', 'accepted');
};

$rollbackDisabledReopenedPrefixSuccessScenarios = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledReopenedPrefixSuccessScenariosFrom($rollbackDisabledPostRecoveryRecoveryScenarios);

$tests['sqlite application wal rollback json dynamic parity rollback-disabled reopened prefix success exposes requested scenario count'] = static function (TestRunner $t) use ($rollbackDisabledReopenedPrefixSuccessScenarios): void {
    $t->same(18, count($rollbackDisabledReopenedPrefixSuccessScenarios));
};

$tests['sqlite application wal rollback json dynamic parity rollback-disabled reopened prefix success covers committed prefix frame counts'] = static function (TestRunner $t) use ($rollbackDisabledReopenedPrefixSuccessScenarios): void {
    $prefixCounts = array_values(array_unique(array_column($rollbackDisabledReopenedPrefixSuccessScenarios, 'committed_prefix_frame_count')));
    sort($prefixCounts);
    $t->same([9, 10, 11, 12], $prefixCounts);
};

$tests['sqlite application wal rollback json dynamic parity rollback-disabled reopened prefix success covers both page sizes'] = static function (TestRunner $t) use ($rollbackDisabledReopenedPrefixSuccessScenarios): void {
    $pageSizes = array_values(array_unique(array_column($rollbackDisabledReopenedPrefixSuccessScenarios, 'page_size')));
    sort($pageSizes);
    $t->same([512, 1024], $pageSizes);
};

$tests['sqlite application wal rollback json dynamic parity rollback-disabled reopened prefix success covers json text and jsonb rows'] = static function (TestRunner $t) use ($rollbackDisabledReopenedPrefixSuccessScenarios): void {
    $jsonModes = array_values(array_unique(array_column($rollbackDisabledReopenedPrefixSuccessScenarios, 'jsonb_mode')));
    sort($jsonModes);
    $t->same([false, true], $jsonModes);
};

foreach ($rollbackDisabledReopenedPrefixSuccessScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $statusChain = $scenario['status_chain'];
    $successPlan = $scenario['reopened_success_plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity rollback disabled reopened prefix success seed ' . $seed . ' ';

    $tests[$prefix . 'starts after the full rollback-disabled recovery chain'] = static function (TestRunner $t) use ($statusChain): void {
        $t->same('partial_rollback', $statusChain['partial']);
        $t->same('ready', $statusChain['followup']);
        $t->same('rolled_back_current_json_batch', $statusChain['previous_tail']);
        $t->same('ready', $statusChain['previous_recovery']);
        $t->same('rolled_back_current_json_batch', $statusChain['post_recovery_failure']);
        $t->same('ready', $statusChain['post_recovery_recovery']);
    };
    $tests[$prefix . 'commits reopened savepoint without rollback'] = static function (TestRunner $t) use ($successPlan): void {
        $t->same('ready', $successPlan['status']);
        $t->same(false, $successPlan['rollback_required']);
        $t->same(3, $successPlan['applied_statement_count']);
        $t->same(0, $successPlan['failed_statement_count']);
    };
    $tests[$prefix . 'uses reopened success transaction and savepoint names'] = static function (TestRunner $t) use ($successPlan, $seed): void {
        $t->same('application_disabled_reopened_success_json_import_' . $seed, $successPlan['transaction']);
        $t->same('disabled_reopened_success_json_batch_' . $seed, $successPlan['savepoint']);
    };
    $tests[$prefix . 'starts from previous recovery database and wal hashes'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $t->same($scenario['previous_recovery_database_hash'], hash('sha256', (string) $successPlan['database_bytes_before']));
        $t->same($scenario['previous_recovery_wal_hash'], hash('sha256', (string) $successPlan['wal_bytes_before']));
    };
    $tests[$prefix . 'preserves committed prefix before appending reopened frames'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $prefixLength = 32 + ($scenario['committed_prefix_frame_count'] * (24 + $scenario['page_size']));
        $t->same($scenario['committed_prefix_frame_count'], $successPlan['wal_frame_count_before']);
        $t->same($scenario['previous_recovery_wal_hash'], hash('sha256', substr($successPlan['wal_bytes_after'], 0, $prefixLength)));
    };
    $tests[$prefix . 'appends exactly reopened success frames'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $t->same(3, $successPlan['materialized_wal_frame_count']);
        $t->same($scenario['committed_prefix_frame_count'] + 3, $successPlan['wal_frame_count_after']);
        $t->same(0, $successPlan['discarded_wal_frame_count']);
        $t->same(false, $successPlan['wal_truncated']);
    };
    $tests[$prefix . 'records reopened applied page numbers and tenant ids'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $t->same($scenario['expected_reopened_pages'], array_column($successPlan['import_plan']['applied'], 'page_number'));
        $t->same([$scenario['tenant_id'], $scenario['tenant_id'], $scenario['tenant_id']], array_column($successPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'records reopened inserted row'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $t->same($scenario['expected_reopened_inserted_key'], $successPlan['import_plan']['applied'][1]['key_name']);
        $t->same(true, in_array($scenario['expected_reopened_inserted_id'], array_column($successPlan['import_plan']['final_rows'], 'setting_id'), true));
    };
    $tests[$prefix . 'updates prior corrected recovery row'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $t->same($scenario['expected_previous_recovery_inserted_key'], $successPlan['import_plan']['applied'][2]['key_name']);
        $value = $successPlan['import_plan']['applied'][2]['key_value'];
        $decoded = is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : [];
        $t->same(true, $decoded['reopened_success_seen'] ?? null);
    };
    $tests[$prefix . 'retains previous recovery key and excludes rolled back tails'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $finalKeys = array_column($successPlan['import_plan']['final_rows'], 'key_name');
        $t->same(true, in_array($scenario['expected_previous_recovery_inserted_key'], $finalKeys, true));
        $t->same(false, in_array($scenario['rejected_prior_tail_inserted_key'], $finalKeys, true));
        $t->same(false, in_array($scenario['rejected_post_recovery_tail_inserted_key'], $finalKeys, true));
    };
    $tests[$prefix . 'keeps jsonb mode on reopened catalog update'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $value = $successPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'keeps inserted reopened row as canonical json text'] = static function (TestRunner $t) use ($successPlan): void {
        $value = $successPlan['import_plan']['applied'][1]['key_value'];
        $t->same(true, is_string($value));
        $t->same(['committed' => true], json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR));
    };
    $tests[$prefix . 'savepoint boundary carries recovered prefix pages'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $transactionPages = $successPlan['import_plan']['savepoint_state'][0]['page_numbers'];
        $expectedPages = array_values(array_unique($scenario['committed_prefix_pages']));
        sort($transactionPages);
        sort($expectedPages);
        $t->same($expectedPages, $transactionPages);
    };
    $tests[$prefix . 'keeps rollback preview scoped to reopened pages'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $expectedPages = $scenario['expected_reopened_pages'];
        $restoredPages = $successPlan['rollback_to_savepoint']['restored_page_numbers'];
        $discardedPages = $successPlan['wal_rollback_to_savepoint']['discarded_page_numbers'];
        sort($expectedPages);
        sort($restoredPages);
        sort($discardedPages);
        $t->same($expectedPages, $restoredPages);
        $t->same($expectedPages, $discardedPages);
    };
    $tests[$prefix . 'appended wal frame pages match reopened applied pages'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        foreach ($scenario['expected_reopened_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number', substr($successPlan['wal_bytes_after'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'reopened wal checksums continue after recovered prefix'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($successPlan['wal_bytes_after'], 0, 24), false);
        for ($frameIndex = 0; $frameIndex < $scenario['committed_prefix_frame_count']; $frameIndex++) {
            $frameOffset = 32 + ($frameIndex * $frameSize);
            $frame = substr($successPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
        }
        foreach ($scenario['expected_reopened_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frame = substr($successPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = PortLibs\LibSqlite\SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frame, 0, 24));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
            $t->same($checksumSeed, [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
    $tests[$prefix . 'only final reopened frame is a commit frame'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        foreach ($scenario['expected_reopened_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($successPlan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, 0, intdiv(strlen($successPlan['database_bytes_after_import']), $pageSize)], $commits);
    };
    $tests[$prefix . 'extends database image for reopened inserted page'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $t->same(true, intdiv(strlen($successPlan['database_bytes_after_import']), (int) $scenario['page_size']) >= $scenario['expected_reopened_pages'][1]);
    };
    $tests[$prefix . 'adds one final row over previous recovery'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $t->same($scenario['previous_recovery_final_row_count'] + 1, count($successPlan['import_plan']['final_rows']));
    };
    $tests[$prefix . 'materialized wal bytes grow by three frames'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $frameSize = 24 + (int) $scenario['page_size'];
        $t->same(strlen((string) $successPlan['wal_bytes_before']) + (3 * $frameSize), strlen((string) $successPlan['wal_bytes_after']));
    };
    $tests[$prefix . 'records reopened dependencies'] = static function (TestRunner $t) use ($successPlan): void {
        $t->same(true, in_array('sqlite-application-json-import-savepoint-current', $successPlan['dependencies'], true));
        $t->same(true, in_array('sqlite-savepoint-wal-rollback-current', $successPlan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-current-batch-byte-truncation', $successPlan['dependencies'], true));
    };
    $tests[$prefix . 'records reopened statement names'] = static function (TestRunner $t) use ($successPlan, $seed): void {
        $t->same([
            'disabled_reopened_success_catalog_' . $seed,
            'disabled_reopened_success_insert_' . $seed,
            'disabled_reopened_success_previous_recovery_' . $seed,
        ], array_column($successPlan['import_plan']['applied'], 'statement'));
    };
    $tests[$prefix . 'records reopened json functions'] = static function (TestRunner $t) use ($successPlan, $scenario): void {
        $t->same([
            $scenario['jsonb_mode'] ? 'jsonb_set' : 'json_set',
            'json_set',
            'json_set',
        ], array_column($successPlan['import_plan']['applied'], 'json_function'));
    };
    $tests[$prefix . 'has no failed statements after reopened commit'] = static function (TestRunner $t) use ($successPlan): void {
        $t->same([], $successPlan['failed_statements']);
        $t->same([], $successPlan['import_plan']['failed']);
    };
}

$tests['sqlite application wal rollback json dynamic parity rejects zero rollback-disabled reopened prefix success scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledReopenedPrefixSuccessScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects empty rollback-disabled reopened prefix success base scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledReopenedPrefixSuccessScenariosFrom([]);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rollback-disabled reopened prefix success small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledReopenedPrefixSuccessScenarios(4);
    $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
    $t->same([10, 11, 12, 9], array_column($smallBatch, 'committed_prefix_frame_count'));
    $t->same([[1321, 2121, 1921], [1322, 2122, 1922], [1323, 2123, 1923], [1324, 2124, 1924]], array_column($smallBatch, 'expected_reopened_pages'));
    $t->same([13, 14, 15, 12], array_map(static fn (array $scenario): int => $scenario['reopened_success_plan']['wal_frame_count_after'], $smallBatch));
};

return $tests;
