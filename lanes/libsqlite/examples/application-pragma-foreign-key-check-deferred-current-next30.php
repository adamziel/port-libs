<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaForeignKeyCheckDeferredPlan;

$tables = [
    'wp_posts' => [
        ['rowid' => 1, 'ID' => 1, 'post_name' => 'hello-world'],
    ],
    'wp_postmeta' => [
        ['rowid' => 10, 'meta_id' => 10, 'post_id' => 1, 'meta_key' => '_edit_lock'],
    ],
];

$foreignKeys = [
    ['id' => 0, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => ['post_id' => 'ID']],
];

$plan = SQLitePragmaForeignKeyCheckDeferredPlan::plan($tables, $foreignKeys, [
    ['op' => 'insert', 'table' => 'wp_postmeta', 'row' => ['rowid' => 11, 'meta_id' => 11, 'post_id' => 404, 'meta_key' => '_thumbnail_id']],
    ['op' => 'check', 'label' => 'before_repair'],
    ['op' => 'insert', 'table' => 'wp_posts', 'row' => ['rowid' => 404, 'ID' => 404, 'post_name' => 'imported-media']],
    ['op' => 'check', 'label' => 'after_repair'],
    ['op' => 'commit'],
]);

$summary = [
    'pragma' => 'foreign_key_check',
    'transaction' => 'deferred-current',
    'before_repair' => $plan['snapshots']['before_repair']['rows'],
    'after_repair_violations' => $plan['snapshots']['after_repair']['deferred_violations'],
    'committed' => $plan['committed'],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['before_repair'][0]['rowid'] !== 11 || $summary['after_repair_violations'] !== 0 || $summary['committed'] !== true) {
        fwrite(STDERR, "application-pragma-foreign-key-check-deferred-current-next30 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-foreign-key-check-deferred-current-next30 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
