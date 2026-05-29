<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerStatementJournalSavepointMasterCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp/content/database/wp-next123.sqlite';
$masterPath = '/srv/wp/content/database/wp-next123.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp/content/database/site-next123.sqlite-journal\n";
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$stale = [
    1 => $page('next123 stale header before master rollback'),
    2 => $page('next123 stale wp_options root after crash'),
    3 => $page('next123 stale active_plugins after crash'),
    4 => $page('next123 stale autoload index after crash'),
    5 => $page('next123 stale plugin transient after crash'),
    6 => $page('next123 stale comments page before retry'),
];
$databaseBytes = implode('', $stale);
$masterRecovered = [
    1 => $page('next123 recovered sqlite header from master'),
    2 => $page('next123 recovered wp_options root current source'),
    4 => $page('next123 recovered autoload index current source'),
    5 => $page('next123 recovered plugin transient current source'),
];
$savepointBefore = [
    2 => $masterRecovered[2],
    4 => $masterRecovered[4],
];
$statementBefore = [
    3 => $page('next123 statement before active_plugins row'),
    5 => $masterRecovered[5],
];
$statementWrites = [
    3 => $page('next123 failed statement active_plugins row'),
    5 => $page('next123 failed statement plugin transient row'),
];
$nextBefore = [
    2 => $masterRecovered[2],
    3 => $statementBefore[3],
    5 => $statementBefore[5],
    7 => str_repeat("\0", $pageSize),
];
$nextWrites = [
    2 => $page('next123 retry wp_options root'),
    3 => $page('next123 retry active_plugins row'),
    5 => $page('next123 retry plugin transient row'),
    7 => $page('next123 retry overflow append page'),
];

$plan = static fn (
    array $recovered = null,
    array $savepoint = null,
    array $statementBeforeInput = null,
    array $statementWritesInput = null,
    array $nextBeforeInput = null,
    array $nextWritesInput = null,
    bool $release = true,
    ?string $path = null,
    ?string $masterJournalPath = null,
    ?string $master = null,
    ?string $bytes = null,
    ?int $size = null,
    string $savepointName = 'plugin-batch-next123',
    string $statementName = 'insert-plugin-next123',
    string $nextName = 'retry-plugin-next123',
): array => SQLitePagerStatementJournalSavepointMasterCurrentSourceNextPlan::plan(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    func_num_args() >= 10 ? $master : $masterBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $savepointName,
    $statementName,
    $nextName,
    $recovered ?? $masterRecovered,
    $savepoint ?? $savepointBefore,
    $statementBeforeInput ?? $statementBefore,
    $statementWritesInput ?? $statementWrites,
    $nextBeforeInput ?? $nextBefore,
    $nextWritesInput ?? $nextWrites,
    $release,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_statement_journal_savepoint_master_current_source_next123'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_recovery_precedes_statement_rollback_inside_active_savepoint'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'plugin-batch-next123'],
    'failed statement name' => [static fn (): mixed => $plan()['failed_statement'], 'insert-plugin-next123'],
    'next statement name' => [static fn (): mixed => $plan()['next_statement'], 'retry-plugin-next123'],
    'release flag true' => [static fn (): mixed => $plan()['release_savepoint_after_retry'], true],
    'current source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'master recovered pages sorted' => [static fn (): mixed => $plan()['master_recovered_page_numbers'], [1, 2, 4, 5]],
    'savepoint before pages' => [static fn (): mixed => $plan()['savepoint_before_page_numbers'], [2, 4]],
    'statement write pages' => [static fn (): mixed => $plan()['statement_write_page_numbers'], [3, 5]],
    'statement restored pages' => [static fn (): mixed => $plan()['statement_restored_page_numbers'], [3, 5]],
    'next statement pages' => [static fn (): mixed => $plan()['next_statement_page_numbers'], [2, 3, 5, 7]],
    'release merged pages' => [static fn (): mixed => $plan()['release_merged_page_numbers'], [1, 2, 3, 4, 5, 7]],
    'master prefix page one' => [static fn (): mixed => $plan()['master_recovered_prefixes'][1], 'next123 recovered sqlite header from master'],
    'master prefix page two' => [static fn (): mixed => $plan()['master_recovered_prefixes'][2], 'next123 recovered wp_options root current source'],
    'savepoint prefix root' => [static fn (): mixed => $plan()['savepoint_before_prefixes'][2], 'next123 recovered wp_options root current source'],
    'statement write prefix active plugin' => [static fn (): mixed => $plan()['statement_write_prefixes'][3], 'next123 failed statement active_plugins row'],
    'statement rollback prefix active plugin' => [static fn (): mixed => $plan()['statement_rollback_prefixes'][3], 'next123 statement before active_plugins row'],
    'statement rollback prefix transient' => [static fn (): mixed => $plan()['statement_rollback_prefixes'][5], 'next123 recovered plugin transient current source'],
    'next before page two recovered' => [static fn (): mixed => $plan()['next_statement_before_prefixes'][2], 'next123 recovered wp_options root current source'],
    'next before page seven empty' => [static fn (): mixed => $plan()['next_statement_before_prefixes'][7], ''],
    'master recovered bytes include header' => [static fn (): mixed => str_contains($plan()['master_recovered_database_bytes'], 'next123 recovered sqlite header from master'), true],
    'master recovered bytes exclude stale root' => [static fn (): mixed => str_contains($plan()['master_recovered_database_bytes'], 'next123 stale wp_options root after crash'), false],
    'master recovered bytes keep untouched page six' => [static fn (): mixed => str_contains($plan()['master_recovered_database_bytes'], 'next123 stale comments page before retry'), true],
    'failed bytes include failed active plugin' => [static fn (): mixed => str_contains($plan()['failed_statement_database_bytes'], 'next123 failed statement active_plugins row'), true],
    'failed bytes include failed transient' => [static fn (): mixed => str_contains($plan()['failed_statement_database_bytes'], 'next123 failed statement plugin transient row'), true],
    'statement rollback bytes restore active plugin' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next123 statement before active_plugins row'), true],
    'statement rollback bytes restore master transient' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next123 recovered plugin transient current source'), true],
    'statement rollback bytes exclude failed active plugin' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next123 failed statement active_plugins row'), false],
    'statement rollback bytes keep recovered root' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next123 recovered wp_options root current source'), true],
    'final bytes include retry root' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next123 retry wp_options root'), true],
    'final bytes include retry active plugin' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next123 retry active_plugins row'), true],
    'final bytes include retry transient' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next123 retry plugin transient row'), true],
    'final bytes include retry append' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next123 retry overflow append page'), true],
    'final bytes exclude stale root' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next123 stale wp_options root after crash'), false],
    'final bytes exclude failed transient' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next123 failed statement plugin transient row'), false],
    'final prefix page one recovered' => [static fn (): mixed => $plan()['final_prefixes'][1], 'next123 recovered sqlite header from master'],
    'final prefix page two retry' => [static fn (): mixed => $plan()['final_prefixes'][2], 'next123 retry wp_options root'],
    'final prefix page three retry' => [static fn (): mixed => $plan()['final_prefixes'][3], 'next123 retry active_plugins row'],
    'final prefix page four recovered' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next123 recovered autoload index current source'],
    'final prefix page five retry' => [static fn (): mixed => $plan()['final_prefixes'][5], 'next123 retry plugin transient row'],
    'final prefix page six stale untouched' => [static fn (): mixed => $plan()['final_prefixes'][6], 'next123 stale comments page before retry'],
    'final prefix page seven append' => [static fn (): mixed => $plan()['final_prefixes'][7], 'next123 retry overflow append page'],
    'final source page one master' => [static fn (): mixed => $plan()['final_sources'][1], 'master-journal-recovered-current-source'],
    'final source page two next' => [static fn (): mixed => $plan()['final_sources'][2], 'next-statement-write'],
    'final source page three next' => [static fn (): mixed => $plan()['final_sources'][3], 'next-statement-write'],
    'final source page four master' => [static fn (): mixed => $plan()['final_sources'][4], 'master-journal-recovered-current-source'],
    'final source page five next' => [static fn (): mixed => $plan()['final_sources'][5], 'next-statement-write'],
    'final source page six stale' => [static fn (): mixed => $plan()['final_sources'][6], 'stale-database-before-master-recovery'],
    'final source page seven next' => [static fn (): mixed => $plan()['final_sources'][7], 'next-statement-write'],
    'operation count with release' => [static fn (): mixed => count($plan()['operations']), 20],
    'operation first reads master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_master_journal'],
    'operation first reason' => [static fn (): mixed => $plan()['operations'][0]['reason'], 'master_journal_names_database_rollback_journal_before_statement_savepoint'],
    'operation restores master page one' => [static fn (): mixed => $plan()['operations'][1]['reason'], 'recover_current_source_before_statement_subjournal'],
    'operation records savepoint root' => [static fn (): mixed => $plan()['operations'][5]['op'], 'record_savepoint_before_image'],
    'operation writes failed active plugin' => [static fn (): mixed => $plan()['operations'][7]['reason'], 'failed_statement_writes_after_master_recovery'],
    'operation restores statement page three' => [static fn (): mixed => $plan()['operations'][9]['reason'], 'rollback_failed_statement_without_discarding_savepoint'],
    'operation captures next append' => [static fn (): mixed => $plan()['operations'][14]['page_number'], 7],
    'operation writes next append' => [static fn (): mixed => $plan()['operations'][18]['page_number'], 7],
    'operation release savepoint' => [static fn (): mixed => $plan()['operations'][19]['op'], 'release_savepoint'],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-pager-statement-journal-savepoint-master-current-source-next123', $plan()['dependencies'], true), true],
    'dependency master source' => [static fn (): mixed => in_array('sqlite-master-journal-current-source-before-statement-subjournal', $plan()['dependencies'], true), true],
    'dependency statement rollback' => [static fn (): mixed => in_array('sqlite-statement-journal-rollback-keeps-active-savepoint', $plan()['dependencies'], true), true],
    'no release count' => [static fn (): mixed => count($plan(null, null, null, null, null, null, false)['operations']), 19],
    'no release merged empty' => [static fn (): mixed => $plan(null, null, null, null, null, null, false)['release_merged_page_numbers'], []],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager statement journal savepoint master current source next123 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, true, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, ''),
    'missing master bytes rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null),
    'master without database journal rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, '/other.sqlite-journal'),
    'empty database bytes rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, ''),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, null, 500),
    'unaligned bytes rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, $databaseBytes . 'x'),
    'empty savepoint name rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, null, null, ''),
    'empty statement name rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, null, null, 'sp', ''),
    'empty next name rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, null, null, 'sp', 'stmt', ''),
    'empty recovered pages rejected' => static fn () => $plan([]),
    'empty savepoint pages rejected' => static fn () => $plan(null, []),
    'empty statement before rejected' => static fn () => $plan(null, null, []),
    'empty statement writes rejected' => static fn () => $plan(null, null, null, []),
    'empty next before rejected' => static fn () => $plan(null, null, null, null, []),
    'empty next writes rejected' => static fn () => $plan(null, null, null, null, null, []),
    'zero recovered page rejected' => static fn () => $plan([0 => $masterRecovered[1]]),
    'short recovered page rejected' => static fn () => $plan([1 => 'short']),
    'recovered outside database rejected' => static fn () => $plan([8 => $page('outside')]),
    'savepoint outside source rejected' => static fn () => $plan(null, [8 => $page('outside')]),
    'statement write missing before rejected' => static fn () => $plan(null, null, [3 => $statementBefore[3]], [5 => $statementWrites[5]]),
    'next before stale rejected' => static fn () => $plan(null, null, null, null, [2 => $stale[2]]),
    'next write missing before rejected' => static fn () => $plan(null, null, null, null, [2 => $masterRecovered[2]], [3 => $nextWrites[3]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager statement journal savepoint master current source next123 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
