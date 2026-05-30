<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachTempVfsOpenPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$temp = SQLiteAttachTempVfsOpenPlan::forAttachSql("ATTACH '' AS import_scratch", true, true);
$site = SQLiteAttachTempVfsOpenPlan::forAttachSql(
    "ATTACH 'file:/srv/application/wp-content/database/site.sqlite?mode=rw&cache=shared' AS site",
    true,
    true,
);

$summary = [
    'temp_schema' => $temp['schema'],
    'temp_database_list_file' => $temp['database_list'][2]['file'],
    'temp_journal_writable' => $temp['sidecar']['journal_writable'],
    'temp_wal_path' => $temp['sidecar']['wal_path'],
    'site_schema' => $site['schema'],
    'site_path' => $site['open']['path'],
    'site_wal_path' => $site['sidecar']['wal_path'],
    'site_shared_memory' => $site['sidecar']['uses_shared_memory'],
    'dependencies' => array_values(array_unique(array_merge($temp['dependencies'], $site['dependencies']))),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['temp_schema'] === 'import_scratch');
    assert($summary['temp_database_list_file'] === '');
    assert($summary['temp_journal_writable'] === true);
    assert($summary['temp_wal_path'] === '');
    assert($summary['site_schema'] === 'site');
    assert($summary['site_path'] === '/srv/application/wp-content/database/site.sqlite');
    assert($summary['site_wal_path'] === '/srv/application/wp-content/database/site.sqlite-wal');
    assert($summary['site_shared_memory'] === true);
    assert(in_array('temp-vfs-open', $summary['dependencies'], true));
    assert(in_array('shared-cache-coordination', $summary['dependencies'], true));
    echo "application-attach-temp-vfs-open-current-next23 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
