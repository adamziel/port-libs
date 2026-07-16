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

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(blog_id integer primary key, domain text)', 1),
        $record('table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(option_id integer primary key, blog_id integer REFERENCES wp_sites(blog_id) ON DELETE RESTRICT, option_name text)', 2),
        $record('table', 'wp_option_audit', 'wp_option_audit', 4, 'CREATE TABLE wp_option_audit(option_id integer, blog_id integer REFERENCES wp_sites(blog_id) ON UPDATE CASCADE)', 3),
        $record('trigger', 'main_option_insert_audit', 'wp_options', 0, 'CREATE TRIGGER main_option_insert_audit AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, blog_id) VALUES(new.option_id, new.blog_id); END', 4),
    ],
    [
        $record('table', 'wp_sites', 'wp_sites', 10, 'CREATE TEMP TABLE wp_sites(blog_id integer primary key)', 5),
        $record('table', 'wp_options', 'wp_options', 11, 'CREATE TEMP TABLE wp_options(option_id integer primary key, blog_id integer REFERENCES wp_sites(blog_id) ON DELETE CASCADE)', 6),
        $record('table', 'wp_option_audit', 'wp_option_audit', 12, 'CREATE TEMP TABLE wp_option_audit(option_id integer, blog_id integer REFERENCES wp_sites(blog_id) ON UPDATE SET DEFAULT)', 7),
        $record('trigger', 'temp_main_option_insert_audit', 'wp_options', null, 'CREATE TEMP TRIGGER temp_main_option_insert_audit AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, blog_id) VALUES(new.option_id, new.blog_id); END', 8),
    ],
);

$catalog->attach('site', '/srv/www/site.sqlite', [
    $record('table', 'wp_sites', 'wp_sites', 20, 'CREATE TABLE site.wp_sites(blog_id integer primary key)', 9),
    $record('table', 'wp_options', 'wp_options', 21, 'CREATE TABLE site.wp_options(option_id integer primary key, blog_id integer, FOREIGN KEY(blog_id) REFERENCES wp_sites(blog_id) ON DELETE SET NULL)', 10),
    $record('trigger', 'site_option_insert', 'wp_options', 0, 'CREATE TRIGGER site_option_insert AFTER INSERT ON wp_options BEGIN SELECT new.option_id, new.blog_id; END', 11),
]);

$main = SQLiteAttachTempViewTriggerResolution::foreignKeyContext($catalog, 'main_option_insert_audit');
$temp = SQLiteAttachTempViewTriggerResolution::foreignKeyContext($catalog, 'temp_main_option_insert_audit');
$site = SQLiteAttachTempViewTriggerResolution::foreignKeyContext($catalog, 'site.site_option_insert');

$payload = [
    'scenario' => 'application attach temp trigger foreign key current-source resolution',
    'mainTargetFk' => $main['targetForeignKeys'][0],
    'mainBodyFk' => $main['bodyForeignKeys'][0],
    'tempPinnedSchemas' => $temp['foreignKeySchemas'],
    'tempPinnedBodyFk' => $temp['bodyForeignKeys'][0],
    'siteTargetFk' => $site['targetForeignKeys'][0],
    'statuses' => [
        'main' => $main['status'],
        'tempPinned' => $temp['status'],
        'site' => $site['status'],
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($payload['mainTargetFk']['parentSchema'] === 'main');
    assert($payload['mainBodyFk']['parentSchema'] === 'main');
    assert($payload['tempPinnedSchemas'] === ['main', 'temp']);
    assert($payload['tempPinnedBodyFk']['parentSchema'] === 'temp');
    assert($payload['siteTargetFk']['parentSchema'] === 'site');
    assert($payload['statuses'] === ['main' => 'resolved', 'tempPinned' => 'resolved', 'site' => 'resolved']);
    echo "application-attach-temp-trigger-fk-current-next21 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT) . PHP_EOL;
