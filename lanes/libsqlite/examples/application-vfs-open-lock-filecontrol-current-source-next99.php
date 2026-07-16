<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteVfsOpenLockFileControlCurrentSource.php';

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

$plan = SQLiteVfsOpenLockFileControlCurrentSource::planGeneratedSourceFileControls([
    'open(file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix)',
    'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private&vfs=unix)',
    ['op' => 'lock', 'handle' => 'db-1', 'value' => 'reserved'],
    ['op' => 'filecontrol', 'handle' => 'db-1', 'control' => 'persist_wal', 'value' => true],
    ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'data_version'],
    ['op' => 'close', 'handle' => 'db-2'],
    'open(file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw)',
    ['op' => 'filecontrol', 'handle' => 'db-3', 'control' => 'data_version'],
]);

$payload = [
    'scenario' => 'application-vfs-open-lock-filecontrol-current-source-next99',
    'applicationUse' => 'Detect copied wp_options database handles whose current-source snapshot is stale after a sibling writer changes persistent VFS file-control state, without requiring ext/sqlite.',
    'writerPath' => $plan['events'][0]['source_key'],
    'readerPath' => $plan['events'][1]['source_key'],
    'writerGenerationAfterPersistWal' => $plan['events'][3]['source_generation'],
    'staleReaderHandles' => $plan['events'][3]['stale_handles'],
    'readerDataVersion' => $plan['events'][4]['value'],
    'readerOpenedGeneration' => $plan['events'][4]['opened_generation'],
    'readerIsStale' => $plan['events'][4]['stale_current_source'],
    'reopenedReaderGeneration' => $plan['events'][6]['next']['handles']['db-3']['source_generation'],
    'reopenedReaderIsStale' => $plan['events'][7]['stale_current_source'],
    'dependencies' => $plan['dependencies'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($payload['writerPath'] === '/srv/www/wp-content/database/wp copy.sqlite');
    assert($payload['readerPath'] === '/srv/www/wp-content/database/wp copy.sqlite');
    assert($payload['writerGenerationAfterPersistWal'] === 2);
    assert($payload['staleReaderHandles'] === ['db-2']);
    assert($payload['readerDataVersion'] === 2);
    assert($payload['readerOpenedGeneration'] === 1);
    assert($payload['readerIsStale'] === true);
    assert($payload['reopenedReaderGeneration'] === 2);
    assert($payload['reopenedReaderIsStale'] === false);
    echo "application-vfs-open-lock-filecontrol-current-source-next99 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
