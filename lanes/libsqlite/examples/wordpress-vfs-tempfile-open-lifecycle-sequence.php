<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsTempFileOpenLifecycle;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$plan = SQLiteVfsTempFileOpenLifecycle::tempFileOpenLifecycleSequence(
    ['open(journal)', 'commit', 'close(temp-wp-import-7-1)', 'open(sorter)'],
    [
        'temp_dir' => '/tmp/wp-sqlite-import',
        'connection_id' => 'wp import 7',
    ],
);

$summary = [
    'first_status' => $plan['events'][0]['status'],
    'commit_status' => $plan['events'][1]['status'],
    'commit_pending_delete' => $plan['events'][1]['delete_on_close_pending'],
    'close_deleted' => $plan['events'][2]['deleted'],
    'next_open_count' => $plan['next']['open_count'],
    'next_pending_delete_count' => $plan['next']['pending_delete_count'],
    'next_paths' => $plan['next']['paths'],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['first_status'] === 'temp-open');
    assert($summary['commit_status'] === 'deferred-close');
    assert($summary['commit_pending_delete'] === 1);
    assert($summary['close_deleted'] === true);
    assert($summary['next_open_count'] === 1);
    assert($summary['next_pending_delete_count'] === 1);
    assert($summary['next_paths'] === ['/tmp/wp-sqlite-import/sqlite-wp-import-7-000002.sorter']);
    assert(in_array('vfs-tempfile-open-lifecycle', $summary['dependencies'], true));
    echo "wordpress-vfs-tempfile-open-lifecycle-temp-file-open-lifecycle-sequence self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
