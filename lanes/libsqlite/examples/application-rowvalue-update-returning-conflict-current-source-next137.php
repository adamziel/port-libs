<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateReturningConflictCurrentSourceNextPlan;

$options = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'network_siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
];

$plan = SQLiteRowValueUpdateReturningConflictCurrentSourceNextPlan::execute(
    ['wp_options' => $options],
    "UPDATE OR REPLACE wp_options SET (option_name, status, option_value) = ('_transient_feed', option_name || ':replace', option_value || ':next') WHERE option_id IN (2, 3, 4) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id",
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'application-rowvalue-update-returning-conflict-current-source-next137',
    'applicationUse' => 'Model copied wp_options cleanup where an UPDATE OR REPLACE row-value assignment deletes a later selected transient row before that row can emit RETURNING, while independent selected rows still yield current-source RETURNING images.',
    'status' => $plan['status'],
    'selectedOptionIds' => $plan['selected_ids'],
    'returnedOptionIds' => $plan['returning_ids'],
    'suppressedSelectedOptionIds' => $plan['suppressed_selected_ids'],
    'deletedConflictOptionIds' => $plan['deleted_conflict_ids'],
    'currentOptionIds' => $plan['current_source_row_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses native PHP UPDATE RETURNING row-value assignment and conflict handling',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (
        $summary['status'] !== 'rowvalue-update-returning-conflict-current-source-next137-ready'
        || $summary['selectedOptionIds'] !== [2, 3, 4]
        || $summary['returnedOptionIds'] !== [2, 4]
        || $summary['suppressedSelectedOptionIds'] !== [3]
        || $summary['deletedConflictOptionIds'] !== [3]
    ) {
        fwrite(STDERR, "unexpected row-value UPDATE RETURNING current-source conflict summary\n");
        exit(1);
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
