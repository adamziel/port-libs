<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/wp/database/wp-options-next118.sqlite';
$masterPath = '/srv/wp/database/wp-options-next118.sqlite-mj';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$makeJournal = static function (array $pages, int $initialPageCount, int $nonce) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$clean1 = $page('next118 clean sqlite schema before plugin import');
$clean2 = $page('next118 clean wp_options root before plugin import');
$clean3 = $page('next118 clean active_plugins before retry savepoint');
$clean4 = $page('next118 clean autoload index before retry savepoint');
$dirty1 = $page('next118 dirty sqlite schema after crashed plugin import');
$dirty2 = $page('next118 dirty wp_options root after crashed plugin import');
$dirty3 = $page('next118 dirty active_plugins after crashed plugin import');
$dirty4 = $page('next118 dirty autoload index after crashed plugin import');
$retry2 = $page('next118 retry wp_options root inside savepoint');
$retry3 = $page('next118 retry active_plugins inside savepoint');
$staleBefore3 = $page('next118 stale active_plugins before-image from dirty cache');

$databaseBytes = $dirty1 . $dirty2 . $dirty3 . $dirty4;
$journalBytes = $makeJournal([
    1 => $clean1,
    2 => $clean2,
    3 => $clean3,
    4 => $clean4,
], 4, 0x11800001);
$masterBytes = $databasePath . "-journal\n";
$beforeImages = [
    2 => $clean2,
    3 => $clean3,
];
$retryWrites = [
    2 => $retry2,
    3 => $retry3,
];

$plan = static fn (array $before = null, array $retry = null, ?string $master = null, bool $reserved = false, bool $requiresSuper = false, ?bool $superExists = null): array => SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext(
    $databasePath,
    $databaseBytes,
    $journalBytes,
    $pageSize,
    'plugin-retry',
    $before ?? $beforeImages,
    $retry ?? $retryWrites,
    $masterPath,
    func_num_args() >= 3 ? $master : $masterBytes,
    $reserved,
    $requiresSuper,
    $superExists
);

$mismatchPlan = static fn (): array => $plan([2 => $clean2, 3 => $staleBefore3]);
$missingMasterPlan = static fn (): array => $plan(null, null, "/tmp/other.sqlite-journal\n");
$reservedPlan = static fn (): array => $plan(null, null, $masterBytes, true);
$missingSuperPlan = static fn (): array => $plan(null, null, $masterBytes, false, true, false);
$superPlan = static fn (): array => $plan(null, null, $masterBytes, false, true, true);

$cases = [
    'status' => static fn (): mixed => $plan()['status'],
    'reason' => static fn (): mixed => $plan()['reason'],
    'database path' => static fn (): mixed => $plan()['database_path'],
    'journal path' => static fn (): mixed => $plan()['journal_path'],
    'master path' => static fn (): mixed => $plan()['master_journal_path'],
    'master member count' => static fn (): mixed => count($plan()['master_journal_members']),
    'listed in master' => static fn (): mixed => $plan()['listed_in_master_journal'],
    'savepoint name' => static fn (): mixed => $plan()['savepoint'],
    'hot recovered' => static fn (): mixed => $plan()['hot_recovered'],
    'hot reason' => static fn (): mixed => $plan()['hot_journal_reason'],
    'journal action' => static fn (): mixed => $plan()['journal_action'],
    'current source verified' => static fn (): mixed => $plan()['current_source_verified'],
    'mismatch pages empty' => static fn (): mixed => $plan()['source_mismatch_pages'],
    'before pages' => static fn (): mixed => $plan()['savepoint_before_page_numbers'],
    'retry pages' => static fn (): mixed => $plan()['retry_page_numbers'],
    'recovered page two prefix' => static fn (): mixed => $plan()['recovered_prefixes'][2],
    'recovered page three prefix' => static fn (): mixed => $plan()['recovered_prefixes'][3],
    'retry page two prefix' => static fn (): mixed => $plan()['retry_prefixes'][2],
    'retry page three prefix' => static fn (): mixed => $plan()['retry_prefixes'][3],
    'rollback page two prefix' => static fn (): mixed => $plan()['rollback_prefixes'][2],
    'rollback page three prefix' => static fn (): mixed => $plan()['rollback_prefixes'][3],
    'dirty page two prefix' => static fn (): mixed => $plan()['dirty_prefixes'][2],
    'dirty page three prefix' => static fn (): mixed => $plan()['dirty_prefixes'][3],
    'rollback images match recovered' => static fn (): mixed => $plan()['images_match_after_rollback'],
    'recovered bytes include clean active plugins' => static fn (): mixed => str_contains($plan()['recovered_database_bytes'], 'clean active_plugins before retry'),
    'recovered bytes exclude dirty active plugins' => static fn (): mixed => str_contains($plan()['recovered_database_bytes'], 'dirty active_plugins after crashed'),
    'retry bytes include retry active plugins' => static fn (): mixed => str_contains($plan()['retry_database_bytes'], 'retry active_plugins inside savepoint'),
    'rollback bytes exclude retry active plugins' => static fn (): mixed => str_contains($plan()['rollback_database_bytes'], 'retry active_plugins inside savepoint'),
    'rollback bytes include clean active plugins' => static fn (): mixed => str_contains($plan()['rollback_database_bytes'], 'clean active_plugins before retry'),
    'payload count' => static fn (): mixed => count($plan()['payloads']),
    'payload key exists' => static fn (): mixed => array_key_exists($databasePath . '#hot-rollback-current-source-next118', $plan()['payloads']),
    'operation count' => static fn (): mixed => count($plan()['operations']),
    'operation zero restore' => static fn (): mixed => $plan()['operations'][0]['reason'],
    'operation one delete' => static fn (): mixed => $plan()['operations'][1]['reason'],
    'operation two capture page' => static fn (): mixed => $plan()['operations'][2]['page_number'],
    'operation two capture reason' => static fn (): mixed => $plan()['operations'][2]['reason'],
    'operation three capture matches' => static fn (): mixed => $plan()['operations'][3]['matches_recovered_current'],
    'operation four retry page' => static fn (): mixed => $plan()['operations'][4]['page_number'],
    'operation five retry reason' => static fn (): mixed => $plan()['operations'][5]['reason'],
    'operation six rollback page' => static fn (): mixed => $plan()['operations'][6]['page_number'],
    'operation seven rollback reason' => static fn (): mixed => $plan()['operations'][7]['reason'],
    'dependency includes next118' => static fn (): mixed => in_array('sqlite-pager-journal-savepoint-hot-rollback-current-source-next118', $plan()['dependencies'], true),
    'dependency includes hot recovery' => static fn (): mixed => in_array('sqlite-rollback-journal-hot-recovery', $plan()['dependencies'], true),
    'dependency includes savepoint rollback' => static fn (): mixed => in_array('sqlite-savepoint-page-image-rollback', $plan()['dependencies'], true),
    'dependency includes current source guard' => static fn (): mixed => in_array('sqlite-current-source-before-image-guard', $plan()['dependencies'], true),
    'mismatch status' => static fn (): mixed => $mismatchPlan()['status'],
    'mismatch reason' => static fn (): mixed => $mismatchPlan()['reason'],
    'mismatch pages' => static fn (): mixed => $mismatchPlan()['source_mismatch_pages'],
    'mismatch capture false' => static fn (): mixed => $mismatchPlan()['operations'][3]['matches_recovered_current'],
    'missing master listed false' => static fn (): mixed => $missingMasterPlan()['listed_in_master_journal'],
    'missing master blocked' => static fn (): mixed => $missingMasterPlan()['current_source_verified'],
    'reserved lock blocked status' => static fn (): mixed => $reservedPlan()['status'],
    'reserved lock reason' => static fn (): mixed => $reservedPlan()['hot_journal_reason'],
    'missing super blocked status' => static fn (): mixed => $missingSuperPlan()['status'],
    'missing super reason' => static fn (): mixed => $missingSuperPlan()['hot_journal_reason'],
    'present super status' => static fn (): mixed => $superPlan()['status'],
];

$expected = [
    'status' => 'pager_journal_savepoint_hot_rollback_current_source_next118',
    'reason' => 'savepoint_retry_uses_hot_rollback_current_source',
    'database path' => $databasePath,
    'journal path' => $databasePath . '-journal',
    'master path' => $masterPath,
    'master member count' => 1,
    'listed in master' => true,
    'savepoint name' => 'plugin-retry',
    'hot recovered' => true,
    'hot reason' => 'hot_journal_recovery_required',
    'journal action' => 'delete_journal_after_recovery',
    'current source verified' => true,
    'mismatch pages empty' => [],
    'before pages' => [2, 3],
    'retry pages' => [2, 3],
    'recovered page two prefix' => 'next118 clean wp_options root before plugin import',
    'recovered page three prefix' => 'next118 clean active_plugins before retry savepoint',
    'retry page two prefix' => 'next118 retry wp_options root inside savepoint',
    'retry page three prefix' => 'next118 retry active_plugins inside savepoint',
    'rollback page two prefix' => 'next118 clean wp_options root before plugin import',
    'rollback page three prefix' => 'next118 clean active_plugins before retry savepoint',
    'dirty page two prefix' => 'next118 dirty wp_options root after crashed plugin import',
    'dirty page three prefix' => 'next118 dirty active_plugins after crashed plugin import',
    'rollback images match recovered' => true,
    'recovered bytes include clean active plugins' => true,
    'recovered bytes exclude dirty active plugins' => false,
    'retry bytes include retry active plugins' => true,
    'rollback bytes exclude retry active plugins' => false,
    'rollback bytes include clean active plugins' => true,
    'payload count' => 1,
    'payload key exists' => true,
    'operation count' => 8,
    'operation zero restore' => 'restore_hot_rollback_before_savepoint_retry_current_source',
    'operation one delete' => 'delete_hot_journal_before_savepoint_retry_current_source',
    'operation two capture page' => 2,
    'operation two capture reason' => 'savepoint_before_image_must_use_recovered_current_source',
    'operation three capture matches' => true,
    'operation four retry page' => 2,
    'operation five retry reason' => 'retry_statement_writes_after_hot_rollback_current_source',
    'operation six rollback page' => 2,
    'operation seven rollback reason' => 'rollback_to_savepoint_restores_hot_rollback_current_source',
    'dependency includes next118' => true,
    'dependency includes hot recovery' => true,
    'dependency includes savepoint rollback' => true,
    'dependency includes current source guard' => true,
    'mismatch status' => 'pager_journal_savepoint_hot_rollback_current_source_blocked_next118',
    'mismatch reason' => 'savepoint_retry_current_source_not_verified_after_hot_rollback',
    'mismatch pages' => [3],
    'mismatch capture false' => false,
    'missing master listed false' => false,
    'missing master blocked' => false,
    'reserved lock blocked status' => 'pager_journal_savepoint_hot_rollback_current_source_blocked_next118',
    'reserved lock reason' => 'database_has_reserved_lock',
    'missing super blocked status' => 'pager_journal_savepoint_hot_rollback_current_source_blocked_next118',
    'missing super reason' => 'missing_super_journal',
    'present super status' => 'pager_journal_savepoint_hot_rollback_current_source_next118',
];

foreach ($cases as $name => $callback) {
    $tests['pager journal savepoint hot rollback current source next118 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext('', $databaseBytes, $journalBytes, $pageSize, 'plugin-retry', $beforeImages, $retryWrites),
    'unaligned database rejected' => static fn () => SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext($databasePath, 'short', $journalBytes, $pageSize, 'plugin-retry', $beforeImages, $retryWrites),
    'empty journal rejected' => static fn () => SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext($databasePath, $databaseBytes, '', $pageSize, 'plugin-retry', $beforeImages, $retryWrites),
    'bad page size rejected' => static fn () => SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext($databasePath, $databaseBytes, $journalBytes, 513, 'plugin-retry', $beforeImages, $retryWrites),
    'empty savepoint rejected' => static fn () => SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext($databasePath, $databaseBytes, $journalBytes, $pageSize, '', $beforeImages, $retryWrites),
    'empty before images rejected' => static fn () => SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext($databasePath, $databaseBytes, $journalBytes, $pageSize, 'plugin-retry', [], $retryWrites),
    'empty retry writes rejected' => static fn () => SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext($databasePath, $databaseBytes, $journalBytes, $pageSize, 'plugin-retry', $beforeImages, []),
    'retry without before image rejected' => static fn () => SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext($databasePath, $databaseBytes, $journalBytes, $pageSize, 'plugin-retry', [2 => $clean2], $retryWrites),
    'bad before page rejected' => static fn () => SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext($databasePath, $databaseBytes, $journalBytes, $pageSize, 'plugin-retry', [0 => $clean2], $retryWrites),
    'short before image rejected' => static fn () => SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext($databasePath, $databaseBytes, $journalBytes, $pageSize, 'plugin-retry', [2 => 'short'], $retryWrites),
    'bad retry page rejected' => static fn () => SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext($databasePath, $databaseBytes, $journalBytes, $pageSize, 'plugin-retry', $beforeImages, [0 => $retry2]),
    'short retry image rejected' => static fn () => SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext($databasePath, $databaseBytes, $journalBytes, $pageSize, 'plugin-retry', $beforeImages, [2 => 'short']),
];

foreach ($throws as $name => $callback) {
    $tests['pager journal savepoint hot rollback current source next118 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
