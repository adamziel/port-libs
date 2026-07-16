<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp/database/wp.sqlite';
$journalPath = '/srv/wp/database/wp.sqlite-journal';
$masterPath = '/srv/wp/database/wp.sqlite-mj142';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$dirty1 = $page('next142 dirty sqlite_schema after crashed plugin import');
$dirty2 = $page('next142 dirty active_plugins after crashed plugin import');
$dirty3 = $page('next142 dirty plugin settings after crashed plugin import');
$clean1 = $page('next142 clean sqlite_schema from hot journal');
$clean2 = $page('next142 clean active_plugins from hot journal');
$clean4 = $page('next142 clean autoload index from hot journal');
$retry2 = $page('next142 retry active_plugins value inside savepoint');
$retry4 = $page('next142 retry autoload index inside savepoint');
$retry5 = $page('next142 retry newly allocated option overflow page');

$plan = static fn (): array => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan(
    $pageSize,
    $databasePath,
    $journalPath,
    $masterPath,
    $journalPath . "\n/srv/wp/database/site.sqlite-journal\n",
    $dirty1 . $dirty2 . $dirty3,
    'plugin-import-retry',
    [
        1 => $clean1,
        2 => $clean2,
        4 => $clean4,
    ],
    [
        2 => $retry2,
        4 => $retry4,
        5 => $retry5,
    ],
    [1, 2, 3, 4, 5, 6]
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_savepoint_hot_journal_master_current_source_next142'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'savepoint_retry_before_images_follow_master_hot_journal_recovery'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $journalPath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'master members collapse lines' => [static fn (): mixed => $plan()['master_members'], [$journalPath, '/srv/wp/database/site.sqlite-journal']],
    'savepoint name' => [static fn (): mixed => $plan()['savepoint']['name'], 'plugin-import-retry'],
    'savepoint retry pages' => [static fn (): mixed => $plan()['savepoint']['retry_page_numbers'], [2, 4, 5]],
    'savepoint captured pages' => [static fn (): mixed => $plan()['savepoint']['captured_page_numbers'], [2, 4, 5]],
    'savepoint restored pages' => [static fn (): mixed => $plan()['savepoint']['rollback_restored_page_numbers'], [2, 4, 5]],
    'hot pages' => [static fn (): mixed => $plan()['hot_journal_page_numbers'], [1, 2, 4]],
    'dirty page one prefix' => [static fn (): mixed => $plan()['dirty_prefixes'][1], 'next142 dirty sqlite_schema after crashed plugin import'],
    'dirty page two prefix' => [static fn (): mixed => $plan()['dirty_prefixes'][2], 'next142 dirty active_plugins after crashed plugin import'],
    'hot page one prefix' => [static fn (): mixed => $plan()['hot_recovered_prefixes'][1], 'next142 clean sqlite_schema from hot journal'],
    'hot page two prefix' => [static fn (): mixed => $plan()['hot_recovered_prefixes'][2], 'next142 clean active_plugins from hot journal'],
    'hot page three remains dirty current' => [static fn (): mixed => $plan()['hot_recovered_prefixes'][3], 'next142 dirty plugin settings after crashed plugin impor'],
    'hot page four appended' => [static fn (): mixed => $plan()['hot_recovered_prefixes'][4], 'next142 clean autoload index from hot journal'],
    'retry page two prefix' => [static fn (): mixed => $plan()['retry_prefixes'][2], 'next142 retry active_plugins value inside savepoint'],
    'retry page four prefix' => [static fn (): mixed => $plan()['retry_prefixes'][4], 'next142 retry autoload index inside savepoint'],
    'retry page five prefix' => [static fn (): mixed => $plan()['retry_prefixes'][5], 'next142 retry newly allocated option overflow page'],
    'rollback page two restored hot image' => [static fn (): mixed => $plan()['rollback_prefixes'][2], 'next142 clean active_plugins from hot journal'],
    'rollback page four restored hot image' => [static fn (): mixed => $plan()['rollback_prefixes'][4], 'next142 clean autoload index from hot journal'],
    'rollback page five zero filled' => [static fn (): mixed => $plan()['rollback_prefixes'][5], ''],
    'captured page two source' => [static fn (): mixed => $plan()['captured_sources'][2], 'hot-journal-master-current-source'],
    'captured page four source' => [static fn (): mixed => $plan()['captured_sources'][4], 'hot-journal-master-current-source'],
    'captured page five source' => [static fn (): mixed => $plan()['captured_sources'][5], 'zero-fill'],
    'release read count' => [static fn (): mixed => count($plan()['release_reads']), 6],
    'release page one source' => [static fn (): mixed => $plan()['release_reads'][0]['source'], 'master-hot-current-source'],
    'release page one matches hot' => [static fn (): mixed => $plan()['release_reads'][0]['matches_hot_journal'], true],
    'release page two matches hot' => [static fn (): mixed => $plan()['release_reads'][1]['matches_hot_journal'], true],
    'release page two no dirty current' => [static fn (): mixed => $plan()['release_reads'][1]['matches_dirty_current'], false],
    'release page three dirty current remains' => [static fn (): mixed => $plan()['release_reads'][2]['matches_dirty_current'], true],
    'release page four hot recovered' => [static fn (): mixed => $plan()['release_reads'][3]['prefix'], 'next142 clean autoload index from hot journal'],
    'release page five zero fill' => [static fn (): mixed => $plan()['release_reads'][4]['zero_filled_short_read'], false],
    'release page five prefix empty' => [static fn (): mixed => $plan()['release_reads'][4]['prefix'], ''],
    'release page six zero fill' => [static fn (): mixed => $plan()['release_reads'][5]['zero_filled_short_read'], true],
    'release page six source' => [static fn (): mixed => $plan()['release_reads'][5]['source'], 'zero-fill'],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 19],
    'operation zero reads master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_master_journal'],
    'operation zero member' => [static fn (): mixed => $plan()['operations'][0]['member'], $journalPath],
    'operation one restores page one' => [static fn (): mixed => $plan()['operations'][1]['page_number'], 1],
    'operation three restores page four' => [static fn (): mixed => $plan()['operations'][3]['page_number'], 4],
    'operation four captures page two' => [static fn (): mixed => $plan()['operations'][4]['op'], 'capture_savepoint_before_image'],
    'operation five writes page two' => [static fn (): mixed => $plan()['operations'][5]['op'], 'write_retry_savepoint_page'],
    'operation eight captures page five zero fill' => [static fn (): mixed => $plan()['operations'][8]['source'], 'zero-fill'],
    'operation ten rollback page two' => [static fn (): mixed => $plan()['operations'][10]['op'], 'rollback_savepoint_before_image'],
    'operation twelve rollback page five' => [static fn (): mixed => $plan()['operations'][12]['page_number'], 5],
    'last operation reads page six' => [static fn (): mixed => $plan()['operations'][18]['page_number'], 6],
    'payload retry exists' => [static fn (): mixed => isset($plan()['payloads'][$databasePath . '#pager-savepoint-hot-journal-master-current-source-next142']), true],
    'payload rollback exists' => [static fn (): mixed => isset($plan()['payloads'][$databasePath . '#pager-savepoint-hot-journal-master-current-source-rollback-next142']), true],
    'payload retry contains retry value' => [static fn (): mixed => str_contains($plan()['payloads'][$databasePath . '#pager-savepoint-hot-journal-master-current-source-next142'], 'retry active_plugins'), true],
    'payload rollback excludes retry value' => [static fn (): mixed => str_contains($plan()['payloads'][$databasePath . '#pager-savepoint-hot-journal-master-current-source-rollback-next142'], 'retry active_plugins'), false],
    'payload rollback contains clean value' => [static fn (): mixed => str_contains($plan()['payloads'][$databasePath . '#pager-savepoint-hot-journal-master-current-source-rollback-next142'], 'clean active_plugins'), true],
    'source digest stable length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-savepoint-hot-journal-master-current-source-next142', $plan()['dependencies'], true), true],
    'dependency master validation' => [static fn (): mixed => in_array('sqlite-master-journal-current-source-member-validation', $plan()['dependencies'], true), true],
    'dependency hot journal' => [static fn (): mixed => in_array('sqlite-hot-journal-before-savepoint-retry', $plan()['dependencies'], true), true],
    'dependency before image' => [static fn (): mixed => in_array('sqlite-savepoint-before-image-after-hot-journal-recovery', $plan()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint hot journal master current source next142 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty database path' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, '', $journalPath, $masterPath, $journalPath, $dirty1, 'sp', [1 => $clean1], [1 => $retry2], [1]),
    'rejects empty journal path' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, '', $masterPath, $journalPath, $dirty1, 'sp', [1 => $clean1], [1 => $retry2], [1]),
    'rejects empty master path' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, '', $journalPath, $dirty1, 'sp', [1 => $clean1], [1 => $retry2], [1]),
    'rejects empty savepoint' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, $journalPath, $dirty1, '', [1 => $clean1], [1 => $retry2], [1]),
    'rejects read only' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, $journalPath, $dirty1, 'sp', [1 => $clean1], [1 => $retry2], [1], false, true),
    'rejects reserved lock' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, $journalPath, $dirty1, 'sp', [1 => $clean1], [1 => $retry2], [1], true),
    'rejects missing master bytes' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, null, $dirty1, 'sp', [1 => $clean1], [1 => $retry2], [1]),
    'rejects master without member' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, '/tmp/other-journal', $dirty1, 'sp', [1 => $clean1], [1 => $retry2], [1]),
    'rejects empty database bytes' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, $journalPath, '', 'sp', [1 => $clean1], [1 => $retry2], [1]),
    'rejects misaligned database bytes' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, $journalPath, 'short', 'sp', [1 => $clean1], [1 => $retry2], [1]),
    'rejects small page size' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan(128, $databasePath, $journalPath, $masterPath, $journalPath, $dirty1, 'sp', [1 => $clean1], [1 => $retry2], [1]),
    'rejects non power page size' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan(768, $databasePath, $journalPath, $masterPath, $journalPath, $dirty1 . str_repeat('.', 256), 'sp', [1 => str_pad('x', 768, '.')], [1 => str_pad('y', 768, '.')], [1]),
    'rejects empty hot pages' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, $journalPath, $dirty1, 'sp', [], [1 => $retry2], [1]),
    'rejects empty retry writes' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, $journalPath, $dirty1, 'sp', [1 => $clean1], [], [1]),
    'rejects empty read pages' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, $journalPath, $dirty1, 'sp', [1 => $clean1], [1 => $retry2], []),
    'rejects zero hot page' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, $journalPath, $dirty1, 'sp', [0 => $clean1], [1 => $retry2], [1]),
    'rejects short hot page' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, $journalPath, $dirty1, 'sp', [1 => 'short'], [1 => $retry2], [1]),
    'rejects zero retry page' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, $journalPath, $dirty1, 'sp', [1 => $clean1], [0 => $retry2], [1]),
    'rejects short retry page' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, $journalPath, $dirty1, 'sp', [1 => $clean1], [1 => 'short'], [1]),
    'rejects zero read page' => static fn () => SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan($pageSize, $databasePath, $journalPath, $masterPath, $journalPath, $dirty1, 'sp', [1 => $clean1], [1 => $retry2], [0]),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint hot journal master current source next142 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
