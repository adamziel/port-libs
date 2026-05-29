<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNext222225Plan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext222225Plan;

$plan = SQLiteVfsCurrentSourceNext222225Plan::run([
    'prepare(wp-options-after-ready)',
    'reuse(wp-options-after-ready)',
    'publish(wp-options-after-ready-publication)',
], [
    'current' => [
        'current_source' => 'main',
        'sources' => [
            'main' => [
                'handle' => 'vfs222225-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 17,
                'publish_receipts' => [
                    ['token' => 'wp-options-publication', 'reuse_count' => 1, 'data_version' => 17],
                ],
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'ready');
    assert($plan['events'][1]['status'] === 'reused');
    assert($plan['events'][2]['status'] === 'published');
    assert(in_array('vfs-current-source-after-ready-reuse-publish-next222-225', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next222-225 self-test passed\n";
    return;
}

print_r($plan);
