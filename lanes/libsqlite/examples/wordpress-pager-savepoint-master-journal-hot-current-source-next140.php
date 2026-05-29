<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerSavepointMasterJournalHotCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerSavepointMasterJournalHotCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next140.sqlite';
$masterPath = '/srv/wp-content/database/wp-next140.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = SQLitePagerSavepointMasterJournalHotCurrentSourceNextPlan::plan(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n/srv/wp-content/database/site-next140.sqlite-journal\n",
    implode('', [
        $page('wp next140 crashed schema before recovery'),
        $page('wp next140 crashed options root before recovery'),
        $page('wp next140 crashed active_plugins before recovery'),
        $page('wp next140 crashed autoload before recovery'),
        $page('wp next140 crashed transient before recovery'),
    ]),
    $pageSize,
    'wp_import_next140',
    'retry_plugin_options_next140',
    [
        1 => $page('wp next140 hot recovered schema current source'),
        2 => $page('wp next140 hot recovered options current source'),
        3 => $page('wp next140 hot recovered active_plugins current source'),
        4 => $page('wp next140 hot recovered autoload current source'),
        5 => $page('wp next140 hot recovered transient current source'),
    ],
    [
        2 => $page('wp next140 failed savepoint options update'),
        3 => $page('wp next140 failed savepoint active_plugins update'),
    ],
    [
        2 => $page('wp next140 retry options update'),
        5 => $page('wp next140 retry transient update'),
    ],
    [1, 2, 3, 4, 5],
    true,
    false,
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-savepoint-master-journal-hot-current-source-next140');
    assert($plan['savepoint']['release_merged_page_numbers'] === [2, 3, 5]);
    assert($plan['master_journal_action'] === 'preserve_master_journal_until_outer_commit');
    assert($plan['final_prefixes'][2] === 'wp next140 retry options update');
    assert($plan['final_prefixes'][3] === 'wp next140 hot recovered active_plugins current source');
    assert($plan['dirty_page_numbers'] === [2, 5]);

    echo "wordpress pager savepoint master-journal hot current-source next140 smoke passed\n";
    return;
}

return [
    'wordpressUse' => 'A copied WordPress options import recovers hot rollback-journal pages through the current master journal, rolls back a failed savepoint write to those recovered current-source images, retries option/transient writes, and preserves the master journal until the outer attached transaction commits.',
    'status' => $plan['status'],
    'finalPrefixes' => $plan['final_prefixes'],
    'dirtyPages' => $plan['dirty_page_numbers'],
    'masterJournalAction' => $plan['master_journal_action'],
    'operations' => array_column($plan['operations'], 'op'),
];
