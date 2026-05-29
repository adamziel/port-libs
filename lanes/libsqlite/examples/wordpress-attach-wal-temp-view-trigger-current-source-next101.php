<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempViewTriggerResolution;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$current = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, option_name text, source text)', 2),
        $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW active_options(option_id, option_name, option_value, autoload) AS SELECT option_id, option_name, option_value, autoload FROM wp_options WHERE autoload = "yes"', 3),
        $record('trigger', 'active_options_insert', 'active_options', 0, 'CREATE TRIGGER active_options_insert INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, "main"); END', 4),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text, autoload text)', 5),
        $record('view', 'active_options', 'active_options', null, 'CREATE TEMP VIEW active_options(option_id, temp_name, option_value, autoload) AS SELECT option_id, temp_name, option_value, autoload FROM temp.wp_options', 6),
        $record('trigger', 'temp_active_options_insert', 'active_options', null, 'CREATE TEMP TRIGGER temp_active_options_insert INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, temp_name, option_value, autoload) VALUES(new.option_id, new.temp_name, new.option_value, new.autoload); END', 7),
    ],
);
$current->attach('archive', '/srv/wp-content/database/archive.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE archive.wp_options(blog_id integer, option_name text, option_value text, autoload text)', 8),
    $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW archive.active_options(blog_id, option_name, option_value, autoload) AS SELECT blog_id, option_name, option_value, autoload FROM wp_options', 9),
    $record('trigger', 'archive_active_options_insert', 'active_options', 0, 'CREATE TRIGGER archive_active_options_insert INSTEAD OF INSERT ON active_options BEGIN INSERT INTO archive.wp_options(blog_id, option_name, option_value, autoload) VALUES(new.blog_id, new.option_name, new.option_value, new.autoload); END', 10),
]);

$next = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, option_name text, source text)', 2),
        $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW active_options(option_id, option_name, option_value, autoload) AS SELECT option_id, option_name, option_value, autoload FROM wp_options WHERE autoload = "yes"', 3),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text, autoload text)', 5),
        $record('view', 'active_options', 'active_options', null, 'CREATE TEMP VIEW active_options(option_id, temp_name, option_value, autoload) AS SELECT option_id, temp_name, option_value, autoload FROM temp.wp_options', 6),
    ],
);

$main = SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan($current, $next, 'main.active_options_insert');
$temp = SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan($current, $next, 'temp.temp_active_options_insert');
$archive = SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan($current, $next, 'archive.archive_active_options_insert');

$summary = [
    'wordpressUse' => 'Copied wp_options imports can keep an active INSTEAD OF view trigger on its current source while a plugin migration drops main/temp/archive trigger sources in the next schema, then force SQLITE_SCHEMA reprepare before the next step.',
    'mainStatus' => $main['status'],
    'tempStatus' => $temp['status'],
    'archiveStatus' => $archive['status'],
    'currentStepResults' => [$main['sqliteResultOnCurrentStep'], $temp['sqliteResultOnCurrentStep'], $archive['sqliteResultOnCurrentStep']],
    'nextStepActions' => [$main['nextStepAction'], $temp['nextStepAction'], $archive['nextStepAction']],
    'invalidatedSources' => [
        'main' => $main['invalidatedSources'],
        'temp' => $temp['invalidatedSources'],
        'archive' => $archive['invalidatedSources'],
    ],
    'missingReasons' => [
        'main' => $main['next']['missingReason'],
        'temp' => $temp['next']['missingReason'],
        'archive' => $archive['next']['missingReason'],
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['mainStatus'] === 'reprepare-required');
    assert($summary['tempStatus'] === 'reprepare-required');
    assert($summary['archiveStatus'] === 'reprepare-required');
    assert($summary['currentStepResults'] === ['SQLITE_OK', 'SQLITE_OK', 'SQLITE_OK']);
    assert($summary['nextStepActions'] === ['abort-reset-and-reprepare', 'abort-reset-and-reprepare', 'abort-reset-and-reprepare']);
    assert($summary['invalidatedSources']['main'] === ['main']);
    assert($summary['invalidatedSources']['temp'] === ['temp']);
    assert($summary['invalidatedSources']['archive'] === ['archive']);
    fwrite(STDOUT, "wordpress-attach-wal-temp-view-trigger-current-source-next101 self-test passed\n");
    return;
}

fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
