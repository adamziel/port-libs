<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'source(wal)',
    ['op' => 'filecontrol', 'control' => 'data_version'],
    'source(main)',
    ['op' => 'filecontrol', 'control' => 'checkpoint_fullfsync', 'value' => true],
    'sync(full)',
    'source(wal)',
    ['op' => 'filecontrol', 'control' => 'data_version'],
    'close(wal)',
    ['op' => 'open', 'source' => 'shm', 'path' => '/srv/www/wp-content/database/wp.sqlite-shm'],
    'lock(shared, wp-reader)',
    ['op' => 'open', 'source' => 'archive', 'path' => '/srv/www/wp-content/database/archive.sqlite', 'readonly' => true],
    'lock(exclusive, wp-import)',
    'source(main)',
], [
    'current' => [
        'current_source' => 'main',
        'owner_generations' => [
            '/srv/www/wp-content/database/wp.sqlite' => 7,
        ],
        'sources' => [
            'main' => [
                'handle' => 'vfs146149-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'generation' => 7,
                'file_controls' => ['persist_wal' => true],
                'syncs' => ['normal'],
            ],
            'wal' => [
                'handle' => 'vfs146149-2',
                'path' => '/srv/www/wp-content/database/wp.sqlite-wal',
                'generation' => 6,
                'locks' => ['shared' => 'wp-reader'],
            ],
        ],
    ],
]);

$summary = [
    'scenario' => 'wordpress-vfs-current-source-next150-153',
    'wordpressUse' => 'Continue hydrated WordPress SQLite VFS current-source state through WAL sidecar staleness, full sync, close/reopen, and readonly archive lock blocking.',
    'dependency' => 'vfs-current-source-close-reopen-next150-153',
    'initialWalStale' => $plan['events'][1]['stale_current_source'],
    'mainOwnerGeneration' => $plan['events'][3]['owner_generation'],
    'syncs' => $plan['next']['sources']['main']['syncs'],
    'closedWal' => $plan['next']['sources']['wal']['closed'],
    'shmHandle' => $plan['next']['sources']['shm']['handle'],
    'archiveWriterLock' => $plan['events'][11]['status'],
    'finalSource' => $plan['next']['current_source'],
    'openSourceCount' => $plan['next']['open_source_count'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['initialWalStale'] === true);
    assert($summary['mainOwnerGeneration'] === 8);
    assert($summary['syncs'] === ['normal', 'full']);
    assert($summary['closedWal'] === true);
    assert($summary['shmHandle'] === 'vfs150153-3');
    assert($summary['archiveWriterLock'] === 'blocked');
    assert($summary['finalSource'] === 'main');
    assert($summary['openSourceCount'] === 3);
    echo "wordpress-vfs-current-source-next150-153 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
