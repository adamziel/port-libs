<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFts5SchemaImportPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sql = "CREATE VIRTUAL TABLE wp_posts_fts USING fts5(post_title, post_content, post_excerpt UNINDEXED, content='wp_posts', content_rowid='ID', tokenize='unicode61 remove_diacritics 2 tokenchars ''-_''', prefix='2 3 4', detail=column, tokendata=1)";
$plan = SQLiteFts5SchemaImportPlan::fromSql($sql, ['wp_posts', 'wp_options']);

echo json_encode([
    'applicationUse' => 'Preview imported Application FTS5 schema records, JSON metadata, external-content rebuild SQL, tokenizer settings, and shadow tables without requiring ext/sqlite.',
    'status' => $plan['status'],
    'table' => $plan['qualifiedName'],
    'jsonSchema' => $plan['jsonSchema'],
    'schemaRecords' => $plan['schemaRecords'],
    'externalContentSql' => $plan['externalContentSql'],
    'importActions' => $plan['importActions'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
