<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeCellDefragmentPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteRecord;

$cells = [
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_plugin_cache', 'stale', 'no'])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'blogname', 'Ported SQLite', 'yes'])),
];

$offsets = [470, 420, 360];
$page = str_repeat("\x55", 512);
$page[0] = "\x0d";
$page = substr_replace($page, pack('n', 392), 1, 2);
$page = substr_replace($page, pack('n', 3), 3, 2);
$page = substr_replace($page, pack('n', 360), 5, 2);
$page[7] = chr(2);
foreach ($offsets as $index => $offset) {
    $page = substr_replace($page, pack('n', $offset), 8 + ($index * 2), 2);
    $page = substr_replace($page, $cells[$index], $offset, strlen($cells[$index]));
}
$page = substr_replace($page, pack('n', 406) . pack('n', 12) . str_repeat('F', 8), 392, 12);
$page = substr_replace($page, pack('n', 0) . pack('n', 14) . str_repeat('G', 10), 406, 14);

$plan = SQLiteBTreeCellDefragmentPlan::fromPage(2, $page);
$afterHeader = SQLiteBTreePageHeader::parsePage($plan->pageImage, 512);

$summary = [
    'applicationScenario' => 'Compact a copied wp_options table leaf after transient cleanup leaves current/next freeblocks and fragmented bytes, preserving row order while rebuilding a contiguous cell content area without ext/sqlite.',
    'action' => $plan->toArray()['action'],
    'page' => $plan->pageNumber,
    'pageType' => $plan->pageType,
    'cellCount' => $plan->cellCount,
    'movedCellCount' => $plan->movedCellCount,
    'fragmentedBytesBefore' => $plan->fragmentedBytesBefore,
    'fragmentedBytesAfter' => $plan->fragmentedBytesAfter,
    'firstFreeblockBefore' => $plan->firstFreeblockBefore,
    'firstFreeblockAfter' => $plan->firstFreeblockAfter,
    'cellPointersAfter' => $afterHeader->cellPointers($plan->pageImage),
    'rowidsAfter' => array_map(
        static fn (SQLiteTableLeafCell $cell): int => $cell->rowId,
        SQLiteTableLeafCell::parsePageCells($plan->pageImage, $afterHeader),
    ),
    'currentNextFragmentBytesBefore' => $plan->currentNextFragmentReport['current_next_fragment_bytes'],
    'freeblockCountAfter' => count($afterHeader->freeblocks($plan->pageImage)),
];

if (($argv[1] ?? '') === '--self-test') {
    $ok = $summary['fragmentedBytesAfter'] === 0
        && $summary['firstFreeblockAfter'] === 0
        && $summary['rowidsAfter'] === [1, 2, 3]
        && $summary['freeblockCountAfter'] === 0;

    if (!$ok) {
        fwrite(STDERR, "application-btree-cell-defragment-current-next71 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-btree-cell-defragment-current-next71 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
