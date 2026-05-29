<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePragmaIntegrityPartialIndexCurrentSourceNext;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'blog_id' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'blog_id' => 1],
    ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'blog_id' => 1],
    ['option_id' => 4, 'option_name' => 'plugin_cache', 'autoload' => null, 'blog_id' => 1],
];
$indexEntries = [
    ['rowid' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl'],
    ['rowid' => 3, 'autoload' => 'no', 'option_name' => '_transient_feed'],
    ['rowid' => 99, 'autoload' => 'yes', 'option_name' => 'deleted_option'],
];
$predicate = new SQLiteIndexPredicate('autoload', SQLiteIndexPredicate::EQUALS, 'yes');

$integrity = SQLitePragmaIntegrityPartialIndexCurrentSourceNext::page(
    $rows,
    $indexEntries,
    $predicate,
    ['autoload', 'option_name'],
    0,
    126,
    'wp_options',
    'wp_options_autoload_yes',
    'PRAGMA integrity_check',
);
$quick = SQLitePragmaIntegrityPartialIndexCurrentSourceNext::page(
    $rows,
    $indexEntries,
    $predicate,
    ['autoload', 'option_name'],
    0,
    126,
    'wp_options',
    'wp_options_autoload_yes',
    'PRAGMA quick_check',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $integrity['status'] !== 'blocked'
        || $integrity['current']['missing'] !== 1
        || $integrity['current']['stale'] !== 1
        || $integrity['current']['orphan'] !== 1
        || $quick['current']['stale'] !== 0
    ) {
        fwrite(STDERR, "wordpress-pragma-integrity-partial-index-current-source-next self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-integrity-partial-index-current-source-next self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-integrity-partial-index-current-source-next',
    'wordpressUse' => 'Validate copied wp_options partial indexes before import cleanup so autoload=yes lookups do not miss current rows or retain stale/deleted option entries.',
    'integrity_status' => $integrity['status'],
    'integrity_current' => $integrity['current'],
    'quick_status' => $quick['status'],
    'quick_current' => $quick['current'],
    'source_id' => $integrity['source_id'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
