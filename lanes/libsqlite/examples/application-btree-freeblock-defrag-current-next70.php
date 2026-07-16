<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeFreeblockDefragPlan;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$cells = [
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'autoload', 'yes'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_timeout_feed', 'stale'])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_site_transient_update_plugins', 'fresh'])),
];

$page = SQLiteTableLeafPage::assemble($cells);
$deleted = SQLiteTableLeafPage::deleteCellByRowId($page, 2, secureDelete: true);
$deleted[7] = chr(47);

$plan = SQLiteBTreeFreeblockDefragPlan::fromPage(5, $deleted);

echo json_encode([
    'scenario' => 'application-btree-freeblock-defrag-current-next70',
    'description' => 'Copied wp_options transient cleanup compacts a fragmented table leaf page after delete, clearing freeblocks and fragmented bytes without ext/sqlite.',
    'plan' => $plan->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
