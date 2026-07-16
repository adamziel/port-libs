<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$settings = json_encode([
    'plugins' => [
        ['slug' => 'seo', 'enabled' => true, 'priority' => 8],
        ['slug' => 'cache', 'enabled' => false, 'priority' => 3],
        ['slug' => 'forms', 'enabled' => true, 'priority' => 5],
    ],
], JSON_THROW_ON_ERROR);

$pluginRows = SQLiteSelectSql::execute(
    "SELECT rowid AS json_rowid, key AS plugin_index, type
       FROM json_each AS j
      WHERE :settings = j.json
        AND '$.plugins' = j.root
        AND j.rowid = 2",
    [],
    [':settings' => $settings],
);

$leafRows = SQLiteSelectSql::execute(
    "SELECT rowid AS json_rowid, key, atom, fullkey
       FROM json_tree AS t
      WHERE :settings = t.json
        AND '$.plugins' = t.root
        AND t.oid = 6",
    [],
    [':settings' => $settings],
);

$summary = [
    'plugin_row' => $pluginRows[0] ?? null,
    'leaf_row' => $leafRows[0] ?? null,
    'dependency_closure' => 'no new support component needed; parser-level JSON table rowid hidden constraints reuse native JSON table planning and SELECT execution',
];

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['plugin_row']['plugin_index'] ?? null) !== 1 || ($summary['leaf_row']['atom'] ?? null) !== 'cache') {
        fwrite(STDERR, "application-json-table-rowid-hidden-constraint-current-source-next84 self-test failed\n");
        exit(1);
    }

    echo "application-json-table-rowid-hidden-constraint-current-source-next84 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
