<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteVfsLockUriTempFileControlCurrentSourceNext137Plan.php';

use PortLibs\LibSqlite\SQLiteVfsLockUriTempFileControlCurrentSourceNext137Plan;

$plan = SQLiteVfsLockUriTempFileControlCurrentSourceNext137Plan::run([
    'open(main, file://localhost/srv/www/wp-content/database/wp.sqlite?mode=rw&cache=shared&psow=1)',
    ['op' => 'filecontrol', 'control' => 'temp_directory', 'value' => '/srv/www/wp-content/uploads/import-tmp'],
    'open(temp, file:/wp-import-stage?mode=memory&tempdir=/srv/www/wp-content/uploads/request-tmp&scratch=17)',
    ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'scratch', 'default' => 0]],
    'lock(shared, wp-import)',
    'lock(reserved, wp-cron)',
    'close(temp)',
    'open(temp)',
], [
    'temp_directory' => '/srv/www/wp-content/uploads/sqlite-tmp',
]);

$summary = [
    'scenario' => 'wordpress-vfs-lock-uri-temp-filecontrol-current-source-next137',
    'wordpressUse' => 'Model a WordPress import that opens a URI main database and memory/temp staging database, changes the connection temp directory through file-control, reads URI file-control values, and proves temp locks/delete-on-close are current-source scoped.',
    'dependency' => 'vfs-lock-uri-temp-filecontrol-current-source-next137',
    'mainPath' => $plan['events'][0]['path'],
    'requestTempPath' => $plan['events'][2]['path'],
    'scratchFileControl' => $plan['events'][3]['value'],
    'cronReservedStatus' => $plan['events'][5]['status'],
    'deletedTempOwner' => $plan['events'][6]['owner'],
    'reopenedTempPath' => $plan['events'][7]['path'],
    'finalTempDirectory' => $plan['next']['temp_directory'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['mainPath'] === '/srv/www/wp-content/database/wp.sqlite');
    assert($summary['requestTempPath'] === '/srv/www/wp-content/uploads/request-tmp/sqlite-temp-2.db');
    assert($summary['scratchFileControl'] === 17);
    assert($summary['cronReservedStatus'] === 'busy');
    assert($summary['deletedTempOwner'] === 'temp:temp:2');
    assert($summary['reopenedTempPath'] === '/srv/www/wp-content/uploads/import-tmp/sqlite-temp-3.db');
    assert($summary['finalTempDirectory'] === '/srv/www/wp-content/uploads/import-tmp');
    echo "wordpress-vfs-lock-uri-temp-filecontrol-current-source-next137 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
