<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerSavepointHotJournalCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerSavepointHotJournalCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/wp-content/database/wp.sqlite';

$dirty = [
    1 => $page('wp header dirty after interrupted plugin import'),
    2 => $page('wp_options root dirty after interrupted plugin import'),
    3 => $page('active_plugins dirty after interrupted plugin import'),
    4 => $page('transient dirty after interrupted plugin import'),
];
$clean = [
    2 => $page('wp_options root clean hot-journal image'),
    4 => $page('transient clean hot-journal image'),
];

$plan = SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan(
    $databasePath,
    implode('', $dirty),
    $pageSize,
    'plugin-import-retry',
    $clean,
    [2 => $dirty[2], 3 => $dirty[3], 4 => $dirty[4]],
    [
        2 => $page('wp_options current savepoint write'),
        4 => $page('transient current savepoint write'),
    ],
    [
        3 => $page('active_plugins retry savepoint write'),
        5 => $page('new option retry savepoint append'),
    ],
    4,
    false,
    true,
    true,
);

echo json_encode([
    'scenario' => 'wordpress-pager-savepoint-hot-journal-current-source-next88',
    'status' => $plan['status'],
    'hotRecovered' => $plan['hot_recovered'],
    'currentSourceVerified' => $plan['current_source_verified'],
    'hotJournalPages' => $plan['hot_journal_page_numbers'],
    'savepointCapturedPages' => $plan['savepoint_captured_page_numbers'],
    'rollbackRestoredPages' => $plan['rollback_restored_page_numbers'],
    'nextWrittenPages' => $plan['next_written_page_numbers'],
    'finalSources' => $plan['final_sources'],
    'operationReasons' => array_values(array_filter(array_map(
        static fn (array $operation): ?string => isset($operation['reason']) ? (string) $operation['reason'] : null,
        $plan['operations']
    ))),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
