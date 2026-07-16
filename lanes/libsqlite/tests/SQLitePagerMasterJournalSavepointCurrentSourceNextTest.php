<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/srv/wp/database/main.sqlite';
$sitePath = '/srv/wp/database/site.sqlite';
$masterPath = '/srv/wp/database/main.sqlite-mj108';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$makeJournal = static function (array $pages, int $initialPageCount, int $nonce) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};
$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-options-import');
    $stack->recordPageImageWrite(1, $page('next108 outer clean schema before import'));
    $stack->savepoint('plugin-settings');
    $stack->recordPageImageWrite(3, $page('next108 plugin option before retry import'));

    return $stack;
};

$mainClean1 = $page('next108 clean main schema before crashed plugin import');
$mainClean2 = $page('next108 clean main active_plugins before retry savepoint');
$mainClean3 = $page('next108 clean main plugin settings before retry savepoint');
$mainDirty1 = $page('next108 dirty main schema after crashed plugin import');
$mainDirty2 = $page('next108 dirty main active_plugins after crashed retry');
$mainDirty3 = $page('next108 dirty main plugin settings after crashed retry');
$siteClean1 = $page('next108 clean site schema before attached import');
$siteDirty1 = $page('next108 dirty site schema after attached import');
$retry2 = $page('next108 retry writes active_plugins from recovered source');
$retry4 = $page('next108 retry appends new plugin autoload option');
$staleMain = $page('next108 stale dirty cache that must not seed savepoint');

$mainDatabase = $mainDirty1 . $mainDirty2 . $mainDirty3;
$siteDatabase = $siteDirty1;
$mainJournalBytes = $makeJournal([1 => $mainClean1, 2 => $mainClean2, 3 => $mainClean3], 3, 0x10800001);
$siteJournalBytes = $makeJournal([1 => $siteClean1], 1, 0x10800002);
$masterBytes = $mainPath . "-journal\n" . $sitePath . "-journal\n";
$databases = [
    [
        'database_path' => $mainPath,
        'current_database_bytes' => $mainDatabase,
        'current_journal_bytes' => $mainJournalBytes,
        'stale_database_bytes' => $staleMain . $mainDirty2,
    ],
    [
        'database_path' => $sitePath,
        'current_database_bytes' => $siteDatabase,
        'current_journal_bytes' => $siteJournalBytes,
    ],
];
$retryWrites = [2 => $retry2, 4 => $retry4];

$plan = static fn (?string $master = null, ?SQLiteSavepointStack $stack = null, array $input = null, array $writes = null): array => SQLitePagerMasterJournalSavepointCurrentSourceNextPlan::currentSourceNext(
    $masterPath,
    func_num_args() >= 1 ? $master : $masterBytes,
    $input ?? $databases,
    $pageSize,
    $mainPath,
    'plugin-settings',
    $stack ?? $makeStack(),
    $writes ?? $retryWrites
);
$reserved = static function () use ($databases, $masterBytes, $masterPath, $pageSize, $mainPath, $makeStack, $retryWrites): array {
    $copy = $databases;
    $copy[0]['reserved_lock'] = true;
    return SQLitePagerMasterJournalSavepointCurrentSourceNextPlan::currentSourceNext(
        $masterPath,
        $masterBytes,
        $copy,
        $pageSize,
        $mainPath,
        'plugin-settings',
        $makeStack(),
        $retryWrites
    );
};

$cases = [
    'status' => static fn (): mixed => $plan()['status'],
    'reason' => static fn (): mixed => $plan()['reason'],
    'primary path' => static fn (): mixed => $plan()['primary_database_path'],
    'savepoint name' => static fn (): mixed => $plan()['savepoint'],
    'current source verified' => static fn (): mixed => $plan()['current_source_verified'],
    'retry status' => static fn (): mixed => $plan()['retry_recovery']['status'],
    'master recovery status' => static fn (): mixed => $plan()['retry_recovery']['master_recovery']['status'],
    'master recovered count' => static fn (): mixed => $plan()['retry_recovery']['master_recovery']['recovered_database_count'],
    'master blocked count' => static fn (): mixed => $plan()['retry_recovery']['master_recovery']['blocked_database_count'],
    'stale candidate ignored count' => static fn (): mixed => $plan()['retry_recovery']['master_recovery']['stale_candidate_count'],
    'before found index' => static fn (): mixed => $plan()['savepoint_before']['found_index'],
    'before retained depth' => static fn (): mixed => $plan()['savepoint_before']['retained_depth'],
    'before rollback pages include outer and plugin' => static fn (): mixed => $plan()['savepoint_before']['rollback_page_numbers'],
    'after found index' => static fn (): mixed => $plan()['savepoint_after']['found_index'],
    'after retained depth' => static fn (): mixed => $plan()['savepoint_after']['retained_depth'],
    'after rollback page numbers sorted' => static fn (): mixed => $plan()['savepoint_after']['rollback_page_numbers'],
    'captured pages' => static fn (): mixed => $plan()['captured_page_numbers'],
    'captured page two source' => static fn (): mixed => $plan()['captured_sources'][2],
    'captured page four source' => static fn (): mixed => $plan()['captured_sources'][4],
    'retry captured count' => static fn (): mixed => count($plan()['retry_recovery']['captured_before_images']),
    'retry first capture source' => static fn (): mixed => $plan()['retry_recovery']['captured_before_images'][0]['source'],
    'retry second capture zero fill' => static fn (): mixed => $plan()['retry_recovery']['captured_before_images'][1]['zero_filled_short_read'],
    'retry final contains active plugins write' => static fn (): mixed => str_contains($plan()['retry_recovery']['final_database_bytes'], 'retry writes active_plugins'),
    'retry final contains append write' => static fn (): mixed => str_contains($plan()['retry_recovery']['final_database_bytes'], 'retry appends new plugin'),
    'retry recovered excludes retry active plugins' => static fn (): mixed => str_contains($plan()['retry_recovery']['recovered_database_bytes'], 'retry writes active_plugins'),
    'rollback page size' => static fn (): mixed => $plan()['rollback_preview']['page_size'],
    'rollback restored page numbers' => static fn (): mixed => $plan()['rollback_preview']['restored_page_numbers'],
    'rollback does not restore outer page one' => static fn (): mixed => array_key_exists(1, $plan()['rollback_preview']['restored_prefixes']),
    'rollback restored page two prefix' => static fn (): mixed => $plan()['rollback_preview']['restored_prefixes'][2],
    'rollback restored page three prefix' => static fn (): mixed => $plan()['rollback_preview']['restored_prefixes'][3],
    'rollback restored page four prefix' => static fn (): mixed => $plan()['rollback_preview']['restored_prefixes'][4],
    'rollback database bytes' => static fn (): mixed => $plan()['rollback_preview']['database_bytes'],
    'rollback excludes retry writes' => static fn (): mixed => $plan()['rollback_preview']['contains_retry_writes'],
    'rollback matches recovered prefix' => static fn (): mixed => $plan()['rollback_preview']['matches_recovered_prefix'],
    'operations count' => static fn (): mixed => count($plan()['operations']),
    'first operation recovers main' => static fn (): mixed => $plan()['operations'][0]['reason'],
    'apply retry write reason' => static fn (): mixed => $plan()['operations'][10]['reason'],
    'capture operation page two' => static fn (): mixed => $plan()['operations'][14]['page_number'],
    'capture operation source two' => static fn (): mixed => $plan()['operations'][14]['source'],
    'capture operation page four' => static fn (): mixed => $plan()['operations'][15]['page_number'],
    'preview operation reason' => static fn (): mixed => $plan()['operations'][16]['reason'],
    'payload has retry final' => static fn (): mixed => isset($plan()['payloads'][$mainPath . '#master-savepoint-current-source-next108']),
    'payload has rollback preview' => static fn (): mixed => isset($plan()['payloads'][$mainPath . '#master-savepoint-rollback-preview-next108']),
    'payload final contains retry' => static fn (): mixed => str_contains($plan()['payloads'][$mainPath . '#master-savepoint-current-source-next108'], 'retry writes active_plugins'),
    'payload rollback excludes retry' => static fn (): mixed => str_contains($plan()['payloads'][$mainPath . '#master-savepoint-rollback-preview-next108'], 'retry writes active_plugins'),
    'payload rollback has recovered active plugins' => static fn (): mixed => str_contains($plan()['payloads'][$mainPath . '#master-savepoint-rollback-preview-next108'], 'clean main active_plugins'),
    'dependency marker' => static fn (): mixed => in_array('sqlite-pager-master-journal-savepoint-current-source-next108', $plan()['dependencies'], true),
    'dependency before images' => static fn (): mixed => in_array('sqlite-savepoint-before-images-from-master-journal-current-source', $plan()['dependencies'], true),
    'dependency previous recovery' => static fn (): mixed => in_array('sqlite-pager-savepoint-master-journal-current-source', $plan()['dependencies'], true),
    'blocked status when master missing' => static fn (): mixed => $plan(null)['status'],
    'blocked current source false' => static fn (): mixed => $plan(null)['current_source_verified'],
    'blocked operations empty' => static fn (): mixed => count($plan(null)['operations']),
    'blocked retry preserves dirty' => static fn (): mixed => str_contains($plan(null)['retry_recovery']['final_database_bytes'], 'dirty main active_plugins'),
    'reserved still returns next status' => static fn (): mixed => $reserved()['status'],
    'reserved master partial' => static fn (): mixed => $reserved()['retry_recovery']['master_recovery']['status'],
    'reserved rollback preserves dirty blocked page two' => static fn (): mixed => $reserved()['rollback_preview']['restored_prefixes'][2],
    'single retry captured pages' => static fn (): mixed => $plan($masterBytes, null, null, [2 => $retry2])['captured_page_numbers'],
    'single retry restored pages' => static fn (): mixed => $plan($masterBytes, null, null, [2 => $retry2])['rollback_preview']['restored_page_numbers'],
    'single retry operations count' => static fn (): mixed => count($plan($masterBytes, null, null, [2 => $retry2])['operations']),
    'single retry excludes retry after rollback' => static fn (): mixed => $plan($masterBytes, null, null, [2 => $retry2])['rollback_preview']['contains_retry_writes'],
    'outer savepoint remains outside rollback restore set' => static fn (): mixed => array_key_exists(1, $plan()['rollback_preview']['restored_prefixes']),
    'zero fill page four restored blank' => static fn (): mixed => $plan()['rollback_preview']['restored_prefixes'][4],
    'final database expanded to page four' => static fn (): mixed => strlen($plan()['retry_recovery']['final_database_bytes']),
];

$expected = [
    'status' => 'master_journal_savepoint_current_source_next',
    'reason' => 'savepoint_before_images_use_master_journal_recovered_current_source',
    'primary path' => $mainPath,
    'savepoint name' => 'plugin-settings',
    'current source verified' => true,
    'retry status' => 'master_journal_recovered_retry_savepoint_current_source',
    'master recovery status' => 'master_journal_current_source_hot_rollback_complete',
    'master recovered count' => 2,
    'master blocked count' => 0,
    'stale candidate ignored count' => 1,
    'before found index' => 1,
    'before retained depth' => 2,
    'before rollback pages include outer and plugin' => [3],
    'after found index' => 1,
    'after retained depth' => 2,
    'after rollback page numbers sorted' => [2, 3, 4],
    'captured pages' => [2, 4],
    'captured page two source' => 'master-journal-recovered-database',
    'captured page four source' => 'zero-fill',
    'retry captured count' => 2,
    'retry first capture source' => 'master-journal-recovered-database',
    'retry second capture zero fill' => true,
    'retry final contains active plugins write' => true,
    'retry final contains append write' => true,
    'retry recovered excludes retry active plugins' => false,
    'rollback page size' => $pageSize,
    'rollback restored page numbers' => [2, 3, 4],
    'rollback does not restore outer page one' => false,
    'rollback restored page two prefix' => 'next108 clean main active_plugins before retry savepoint',
    'rollback restored page three prefix' => 'next108 plugin option before retry import',
    'rollback restored page four prefix' => '',
    'rollback database bytes' => $pageSize * 4,
    'rollback excludes retry writes' => false,
    'rollback matches recovered prefix' => false,
    'operations count' => 17,
    'first operation recovers main' => 'restore_current_source_database_from_master_hot_journal',
    'apply retry write reason' => 'write_retry_savepoint_after_master_current_source_recovery',
    'capture operation page two' => 2,
    'capture operation source two' => 'master-journal-recovered-database',
    'capture operation page four' => 4,
    'preview operation reason' => 'prove_retry_pages_restore_to_master_journal_recovered_source',
    'payload has retry final' => true,
    'payload has rollback preview' => true,
    'payload final contains retry' => true,
    'payload rollback excludes retry' => false,
    'payload rollback has recovered active plugins' => true,
    'dependency marker' => true,
    'dependency before images' => true,
    'dependency previous recovery' => true,
    'blocked status when master missing' => 'master_journal_savepoint_current_source_blocked',
    'blocked current source false' => false,
    'blocked operations empty' => 0,
    'blocked retry preserves dirty' => true,
    'reserved still returns next status' => 'master_journal_savepoint_current_source_next',
    'reserved master partial' => 'master_journal_current_source_hot_rollback_partial',
    'reserved rollback preserves dirty blocked page two' => 'next108 dirty main active_plugins after crashed retry',
    'single retry captured pages' => [2],
    'single retry restored pages' => [2, 3],
    'single retry operations count' => 16,
    'single retry excludes retry after rollback' => false,
    'outer savepoint remains outside rollback restore set' => false,
    'zero fill page four restored blank' => '',
    'final database expanded to page four' => $pageSize * 4,
];

foreach ($cases as $name => $callback) {
    $tests['pager master journal savepoint current source next108 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

$throws = [
    'empty savepoint rejected' => static fn () => SQLitePagerMasterJournalSavepointCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, $mainPath, '', $makeStack(), $retryWrites),
    'unknown savepoint rejected' => static fn () => SQLitePagerMasterJournalSavepointCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, $mainPath, 'missing', $makeStack(), $retryWrites),
    'empty retry writes rejected' => static fn () => SQLitePagerMasterJournalSavepointCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, $mainPath, 'plugin-settings', $makeStack(), []),
    'short retry page rejected' => static fn () => SQLitePagerMasterJournalSavepointCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, $mainPath, 'plugin-settings', $makeStack(), [2 => 'short']),
    'bad page size rejected' => static fn () => SQLitePagerMasterJournalSavepointCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, 500, $mainPath, 'plugin-settings', $makeStack(), $retryWrites),
    'missing primary rejected' => static fn () => SQLitePagerMasterJournalSavepointCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, '/missing.sqlite', 'plugin-settings', $makeStack(), $retryWrites),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal savepoint current source next108 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
