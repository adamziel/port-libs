<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$settings = '{"plugins":{"seo":{"rules":["title","meta"]},"cache":{"rules":[]},"forms":{"rules":["contact"]}}}';

$rows = SQLiteSelectSql::execute(
    "SELECT p.key AS plugin_key,
            p.rowid AS plugin_rowid,
            r.atom AS matched_rule,
            r.rowid AS matched_rule_rowid
       FROM json_each(:settings, '$.plugins') AS p
       LEFT JOIN json_each(p.value, '$.rules') AS r ON r.atom LIKE '%t%'
      ORDER BY plugin_key, matched_rule_rowid",
    [],
    [':settings' => $settings],
);

echo json_encode([
    'scenario' => 'application-json-table-left-join-rowid-current-source-next79',
    'plugin_keys' => array_column($rows, 'plugin_key'),
    'plugin_rowids' => array_column($rows, 'plugin_rowid'),
    'matched_rules' => array_column($rows, 'matched_rule'),
    'matched_rule_rowids' => array_column($rows, 'matched_rule_rowid'),
    'null_extended_plugins' => array_values(array_column(array_filter(
        $rows,
        static fn (array $row): bool => $row['matched_rule_rowid'] === null,
    ), 'plugin_key')),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
