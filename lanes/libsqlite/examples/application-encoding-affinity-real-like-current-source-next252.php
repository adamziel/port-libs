<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteUtf16LikeGlobAffinityCurrentSourceCursor;

$rows = [
    ['key' => 10.0, 'rowid' => 1, 'textEncoding' => 'UTF-16LE', 'payload' => ['option_name' => 'posts_per_page']],
    ['key' => 100.0, 'rowid' => 2, 'textEncoding' => 'UTF-16BE', 'payload' => ['option_name' => 'thumbnail_size_w']],
    ['key' => 100.5, 'rowid' => 3, 'textEncoding' => 'UTF-16LE', 'payload' => ['option_name' => 'image_quality']],
    ['key' => 1000.0, 'rowid' => 4, 'textEncoding' => 'UTF-8', 'payload' => ['option_name' => 'large_size_w']],
    ['key' => null, 'rowid' => 5, 'textEncoding' => 'UTF-8', 'payload' => ['option_name' => 'unset_numeric_option']],
];

$cursor = new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor($rows, '100%', 'LIKE', 'NOCASE');
$matches = $cursor->matchedRows();

$summary = [
    'scenario' => 'application-encoding-affinity-real-like-current-source-next252',
    'applicationUse' => 'Copied wp_options numeric option-value scans preserve text-affinity real values ending in zero before applying UTF-8/UTF-16 LIKE range and residual matching.',
    'matchedRowids' => array_map(static fn (array $row): int => $row['rowid'], $matches),
    'matchedTexts' => array_map(static fn (array $row): string => $row['text'], $matches),
    'currentPlan' => $cursor->currentNextPlan(),
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['matchedRowids'] !== [2, 3, 4] || $summary['matchedTexts'] !== ['100', '100.5', '1000']) {
        throw new RuntimeException('Expected real text-affinity LIKE scan to keep numeric trailing zero digits');
    }
    if ($summary['currentPlan']['currentText'] !== '100' || $summary['currentPlan']['currentBytesHex'] !== '003100300030') {
        throw new RuntimeException('Expected UTF-16BE encoded current text for rowid 2');
    }

    echo "application-encoding-affinity-real-like-current-source-next252 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
