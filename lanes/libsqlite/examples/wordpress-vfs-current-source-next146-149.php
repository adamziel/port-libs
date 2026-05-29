<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNext146149Plan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext146149Plan;

$plan = SQLiteVfsCurrentSourceNext146149Plan::run([
    'source(temp)',
    ['op' => 'filecontrol', 'control' => 'checkpoint_fullfsync', 'value' => true],
    'open(main, /srv/www/wp-content/database/wp.sqlite)',
    'lock(reserved, wp-import)',
    ['op' => 'open', 'source' => 'archive', 'path' => '/srv/www/wp-content/database/archive.sqlite', 'readonly' => true],
    'lock(shared, wp-reader)',
    'lock(reserved, wp-reader)',
    'source(main)',
], [
    'current' => [
        'current_source' => 'main',
        'sources' => [
            'main' => [
                'handle' => 'vfs137-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'file_controls' => ['persist_wal' => true],
            ],
            'temp' => [
                'handle' => 'vfs137-2',
                'path' => '/srv/www/wp-content/uploads/sqlite-tmp/sqlite-temp-2.db',
                'locks' => ['shared' => 'wp-import'],
            ],
        ],
    ],
]);

$summary = [
    'scenario' => 'wordpress-vfs-current-source-next146-149',
    'wordpressUse' => 'Carry hydrated WordPress SQLite VFS main/temp handles through next146-149 current-source routing while opening a readonly archive database.',
    'dependency' => 'vfs-current-source-next146-149',
    'reusedMainStatus' => $plan['events'][2]['status'],
    'tempFullFsync' => $plan['next']['sources']['temp']['file_controls']['checkpoint_fullfsync'],
    'archiveSharedLock' => $plan['events'][5]['status'],
    'archiveWriterLock' => $plan['events'][6]['status'],
    'finalSource' => $plan['next']['current_source'],
    'sourceCount' => $plan['next']['source_count'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['reusedMainStatus'] === 'reused-current-source');
    assert($summary['tempFullFsync'] === true);
    assert($summary['archiveSharedLock'] === 'ok');
    assert($summary['archiveWriterLock'] === 'blocked');
    assert($summary['finalSource'] === 'main');
    assert($summary['sourceCount'] === 3);
    echo "wordpress-vfs-current-source-next146-149 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
