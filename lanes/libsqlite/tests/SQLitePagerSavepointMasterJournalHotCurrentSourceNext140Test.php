<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointMasterJournalHotCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next140.sqlite';
$masterPath = '/srv/wp-content/database/wp-next140.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/site-next140.sqlite-journal\n";
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$crashed = [
    1 => $page('next140 crashed schema page before hot master recovery'),
    2 => $page('next140 crashed wp_options root page'),
    3 => $page('next140 crashed active_plugins row page'),
    4 => $page('next140 crashed autoload index page'),
    5 => $page('next140 crashed plugin transient page'),
    6 => $page('next140 crashed untouched comments page'),
];
$hotRecovered = [
    1 => $page('next140 hot recovered schema current source'),
    2 => $page('next140 hot recovered wp_options current source'),
    3 => $page('next140 hot recovered active_plugins current source'),
    4 => $page('next140 hot recovered autoload index current source'),
    5 => $page('next140 hot recovered transient current source'),
];
$savepointWrites = [
    2 => $page('next140 failed savepoint wp_options write'),
    3 => $page('next140 failed savepoint active_plugins write'),
];
$retryWrites = [
    2 => $page('next140 retry wp_options after rollback'),
    5 => $page('next140 retry transient after rollback'),
];
$databaseBytes = implode('', $crashed);

$plan = static fn (
    array $recovered = null,
    array $savepoint = null,
    array $retry = null,
    array $reads = null,
    bool $release = true,
    bool $commit = false,
    ?string $path = null,
    ?string $masterJournalPath = null,
    ?string $master = null,
    ?string $bytes = null,
    ?int $size = null,
    string $savepointName = 'wp_import_next140',
    string $retryName = 'retry_options_next140',
): array => SQLitePagerSavepointMasterJournalHotCurrentSourceNextPlan::plan(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    func_num_args() >= 9 ? $master : $masterBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $savepointName,
    $retryName,
    $recovered ?? $hotRecovered,
    $savepoint ?? $savepointWrites,
    $retry ?? $retryWrites,
    $reads ?? [1, 2, 3, 4, 5, 6],
    $release,
    $commit,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-savepoint-master-journal-hot-current-source-next140'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'hot_master_journal_recovery_seeds_savepoint_and_retry_current_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'savepoint name' => [static fn (): mixed => $plan()['savepoint']['name'], 'wp_import_next140'],
    'retry name' => [static fn (): mixed => $plan()['retry_statement']['name'], 'retry_options_next140'],
    'hot pages' => [static fn (): mixed => $plan()['hot_recovered_page_numbers'], [1, 2, 3, 4, 5]],
    'savepoint before pages' => [static fn (): mixed => $plan()['savepoint']['before_page_numbers'], [2, 3]],
    'savepoint rollback pages' => [static fn (): mixed => $plan()['savepoint']['rollback_restored_page_numbers'], [2, 3]],
    'savepoint released' => [static fn (): mixed => $plan()['savepoint']['released_after_retry'], true],
    'outer commit false' => [static fn (): mixed => $plan()['savepoint']['outer_transaction_committed'], false],
    'release merged pages' => [static fn (): mixed => $plan()['savepoint']['release_merged_page_numbers'], [2, 3, 5]],
    'retry before pages' => [static fn (): mixed => $plan()['retry_statement']['before_page_numbers'], [2, 5]],
    'retry write pages' => [static fn (): mixed => $plan()['retry_statement']['write_page_numbers'], [2, 5]],
    'hot prefix page two' => [static fn (): mixed => $plan()['hot_recovered_prefixes'][2], 'next140 hot recovered wp_options current source'],
    'hot prefix page five' => [static fn (): mixed => $plan()['hot_recovered_prefixes'][5], 'next140 hot recovered transient current source'],
    'savepoint before page two' => [static fn (): mixed => $plan()['savepoint_before_prefixes'][2], 'next140 hot recovered wp_options current source'],
    'savepoint before page three' => [static fn (): mixed => $plan()['savepoint_before_prefixes'][3], 'next140 hot recovered active_plugins current source'],
    'savepoint write page two' => [static fn (): mixed => $plan()['savepoint_write_prefixes'][2], 'next140 failed savepoint wp_options write'],
    'retry before page two restored' => [static fn (): mixed => $plan()['retry_before_prefixes'][2], 'next140 hot recovered wp_options current source'],
    'retry before page five hot' => [static fn (): mixed => $plan()['retry_before_prefixes'][5], 'next140 hot recovered transient current source'],
    'retry write page five' => [static fn (): mixed => $plan()['retry_write_prefixes'][5], 'next140 retry transient after rollback'],
    'final page one hot' => [static fn (): mixed => $plan()['final_prefixes'][1], 'next140 hot recovered schema current source'],
    'final page two retry' => [static fn (): mixed => $plan()['final_prefixes'][2], 'next140 retry wp_options after rollback'],
    'final page three restored' => [static fn (): mixed => $plan()['final_prefixes'][3], 'next140 hot recovered active_plugins current source'],
    'final page four hot' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next140 hot recovered autoload index current source'],
    'final page five retry' => [static fn (): mixed => $plan()['final_prefixes'][5], 'next140 retry transient after rollback'],
    'final page six crashed untouched' => [static fn (): mixed => $plan()['final_prefixes'][6], 'next140 crashed untouched comments page'],
    'source page one hot' => [static fn (): mixed => $plan()['final_sources'][1], 'hot-master-journal-current-source'],
    'source page two retry' => [static fn (): mixed => $plan()['final_sources'][2], 'retry-write-after-savepoint-hot-current-source'],
    'source page three rollback' => [static fn (): mixed => $plan()['final_sources'][3], 'rollback-to-savepoint-hot-current-source-before-image'],
    'source page five retry' => [static fn (): mixed => $plan()['final_sources'][5], 'retry-write-after-savepoint-hot-current-source'],
    'source page six crashed' => [static fn (): mixed => $plan()['final_sources'][6], 'crashed-database-before-hot-master-current-source'],
    'dirty pages' => [static fn (): mixed => $plan()['dirty_page_numbers'], [2, 5]],
    'journal preserved before outer commit' => [static fn (): mixed => $plan()['journal_action'], 'preserve_rollback_journal_until_outer_commit'],
    'master preserved before outer commit' => [static fn (): mixed => $plan()['master_journal_action'], 'preserve_master_journal_until_outer_commit'],
    'hot bytes include recovered active_plugins' => [static fn (): mixed => str_contains($plan()['hot_recovered_database_bytes'], 'next140 hot recovered active_plugins current source'), true],
    'hot bytes exclude crashed active_plugins' => [static fn (): mixed => str_contains($plan()['hot_recovered_database_bytes'], 'next140 crashed active_plugins row page'), false],
    'dirty bytes include failed savepoint' => [static fn (): mixed => str_contains($plan()['savepoint_dirty_database_bytes'], 'next140 failed savepoint active_plugins write'), true],
    'after rollback excludes failed savepoint' => [static fn (): mixed => str_contains($plan()['after_rollback_database_bytes'], 'next140 failed savepoint active_plugins write'), false],
    'after rollback restores hot options' => [static fn (): mixed => str_contains($plan()['after_rollback_database_bytes'], 'next140 hot recovered wp_options current source'), true],
    'final bytes include retry options' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next140 retry wp_options after rollback'), true],
    'final bytes include retry transient' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next140 retry transient after rollback'), true],
    'final bytes excludes crashed root' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next140 crashed wp_options root page'), false],
    'final bytes excludes failed savepoint root' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next140 failed savepoint wp_options write'), false],
    'read count' => [static fn (): mixed => count($plan()['reads']), 6],
    'read one hot clean' => [static fn (): mixed => $plan()['reads'][0]['dirty'], false],
    'read two dirty' => [static fn (): mixed => $plan()['reads'][1]['dirty'], true],
    'read two no longer retry before' => [static fn (): mixed => $plan()['reads'][1]['matches_retry_before_image'], false],
    'read three matches savepoint before' => [static fn (): mixed => $plan()['reads'][2]['matches_savepoint_before_image'], true],
    'read five source retry' => [static fn (): mixed => $plan()['reads'][4]['source'], 'retry-write-after-savepoint-hot-current-source'],
    'read six source crashed untouched' => [static fn (): mixed => $plan()['reads'][5]['source'], 'crashed-database-before-hot-master-current-source'],
    'operation count preserved' => [static fn (): mixed => count($plan()['operations']), 25],
    'operation reads current master first' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_before_hot_recovery'],
    'operation restores hot page one' => [static fn (): mixed => $plan()['operations'][1]['reason'], 'recover_hot_journal_from_current_master_before_savepoint'],
    'operation captures savepoint' => [static fn (): mixed => in_array('capture_savepoint_before_image_from_hot_current_source', array_column($plan()['operations'], 'op'), true), true],
    'operation rolls back savepoint' => [static fn (): mixed => in_array('rollback_to_savepoint_hot_current_source_before_image', array_column($plan()['operations'], 'op'), true), true],
    'operation captures retry' => [static fn (): mixed => in_array('capture_retry_before_image_after_savepoint_rollback', array_column($plan()['operations'], 'op'), true), true],
    'operation preserves rollback journal' => [static fn (): mixed => $plan()['operations'][23]['op'], 'preserve_database_rollback_journal'],
    'operation preserves master journal' => [static fn (): mixed => $plan()['operations'][24]['op'], 'preserve_master_journal'],
    'digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-pager-savepoint-master-journal-hot-current-source-next140', $plan()['dependencies'], true), true],
    'dependency current master' => [static fn (): mixed => in_array('sqlite-current-master-journal-before-savepoint-hot-recovery', $plan()['dependencies'], true), true],
    'dependency deferred delete' => [static fn (): mixed => in_array('sqlite-master-journal-delete-deferred-until-outer-commit', $plan()['dependencies'], true), true],
    'no release merged empty' => [static fn (): mixed => $plan(release: false)['savepoint']['release_merged_page_numbers'], []],
    'no release operation count' => [static fn (): mixed => count($plan(release: false)['operations']), 24],
    'outer commit journal deleted' => [static fn (): mixed => $plan(commit: true)['journal_action'], 'delete_rollback_journal_after_outer_commit'],
    'outer commit master deleted' => [static fn (): mixed => $plan(commit: true)['master_journal_action'], 'delete_master_journal_after_all_named_journals_commit'],
    'outer commit delete rollback op' => [static fn (): mixed => $plan(commit: true)['operations'][23]['op'], 'delete_database_rollback_journal'],
    'outer commit delete master op' => [static fn (): mixed => $plan(commit: true)['operations'][24]['op'], 'delete_master_journal'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint master journal hot current source next140 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(path: ''),
    'empty master path rejected' => static fn () => $plan(masterJournalPath: ''),
    'missing master bytes rejected' => static fn () => $plan(master: null),
    'master without journal rejected' => static fn () => $plan(master: '/tmp/other.sqlite-journal' . "\n"),
    'empty database bytes rejected' => static fn () => $plan(bytes: ''),
    'bad page size rejected' => static fn () => $plan(size: 500),
    'unaligned database bytes rejected' => static fn () => $plan(bytes: $databaseBytes . 'x'),
    'empty savepoint name rejected' => static fn () => $plan(savepointName: ''),
    'empty retry name rejected' => static fn () => $plan(retryName: ''),
    'empty recovered rejected' => static fn () => $plan(recovered: []),
    'empty savepoint writes rejected' => static fn () => $plan(savepoint: []),
    'empty retry writes rejected' => static fn () => $plan(retry: []),
    'empty reads rejected' => static fn () => $plan(reads: []),
    'zero recovered page rejected' => static fn () => $plan(recovered: [0 => $hotRecovered[1]]),
    'short recovered page rejected' => static fn () => $plan(recovered: [1 => 'short']),
    'recovered outside database rejected' => static fn () => $plan(recovered: [7 => $page('outside')]),
    'zero savepoint page rejected' => static fn () => $plan(savepoint: [0 => $savepointWrites[2]]),
    'short savepoint page rejected' => static fn () => $plan(savepoint: [2 => 'short']),
    'savepoint outside recovered rejected' => static fn () => $plan(savepoint: [7 => $page('outside')]),
    'zero retry page rejected' => static fn () => $plan(retry: [0 => $retryWrites[2]]),
    'short retry page rejected' => static fn () => $plan(retry: [2 => 'short']),
    'retry outside recovered rejected' => static fn () => $plan(retry: [7 => $page('outside')]),
    'zero read rejected' => static fn () => $plan(reads: [0]),
    'read outside database rejected' => static fn () => $plan(reads: [7]),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint master journal hot current source next140 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
