<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'prepare(4,wp-options-lease)',
    'prepare(9,wp-postmeta-lease)',
    'publish(import-checkpoint-202,4,9)',
    'ack(import-checkpoint-202)',
    'reader(plugin-import-reader,4,wp-options-lease)',
    'reader(stale-cache-reader,9,old-postmeta-lease)',
], [
    'current' => [
        'current_source' => 'main',
        'owner_generations' => ['/srv/www/wp-content/database/wp.sqlite' => 61],
        'sources' => [
            'main' => [
                'handle' => 'vfs194197-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'lock' => 'reserved',
                'data_version' => 12,
                'write_receipts' => [['page' => 4, 'bytes' => 4096, 'digest' => 'wp-options']],
                'durable_receipts' => [['page' => 4, 'bytes' => 4096, 'digest' => 'wp-options']],
            ],
        ],
    ],
    'prerequisite_next198_201' => ['sync(normal)'],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][2]['status'] === 'published');
    assert($plan['events'][3]['status'] === 'acknowledged');
    assert($plan['events'][4]['status'] === 'reader-retained');
    assert($plan['events'][5]['status'] === 'reader-reopen-required');
    assert(in_array('vfs-current-source-publish-reader-fence-next202-205', $plan['dependencies'], true));
    echo "application-vfs-current-source-next202-205 self-test passed\n";
    return;
}

print_r($plan);
