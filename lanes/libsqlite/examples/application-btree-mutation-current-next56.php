<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$cell = SQLiteTableLeafCell::encode(9, SQLiteRecord::encode([null, '_transient_timeout_plugin', 'stale', 'no']));
$cellOffset = 512 - strlen($cell);
$freeblockOffset = $cellOffset - 22;
$page = str_repeat("\xff", 512);
$page[0] = "\x0d";
$page = substr_replace($page, pack('n', $freeblockOffset), 1, 2);
$page = substr_replace($page, pack('n', 1), 3, 2);
$page = substr_replace($page, pack('n', $freeblockOffset), 5, 2);
$page[7] = chr(2);
$page = substr_replace($page, pack('n', $cellOffset), 8, 2);
$page = substr_replace($page, pack('n', 0) . pack('n', 20) . str_repeat('F', 16), $freeblockOffset, 20);
$page = substr_replace($page, $cell, $cellOffset, strlen($cell));

$before = SQLiteBTreePageHeader::parsePage($page, 512);
$mutated = SQLiteTableLeafPage::deleteCellByRowId($page, 9, secureDelete: true);
$after = SQLiteBTreePageHeader::parsePage($mutated, 512);
$freeblock = $after->freeblocks($mutated)[0];

echo json_encode([
    'scenario' => 'application-btree-mutation-current-next56',
    'summary' => 'Copied wp_options transient delete applies the B-tree mutation by absorbing a current/next two-byte fragment into the coalesced table-leaf freeblock.',
    'before' => [
        'cell_count' => $before->cellCount,
        'fragmented_free_bytes' => $before->fragmentedFreeBytes,
        'first_freeblock_offset' => $before->firstFreeblockOffset,
    ],
    'after' => [
        'cell_count' => $after->cellCount,
        'fragmented_free_bytes' => $after->fragmentedFreeBytes,
        'first_freeblock_offset' => $after->firstFreeblockOffset,
        'freeblock' => $freeblock->toArray(),
        'integrity' => $after->freeblockIntegrityReport($mutated)['status'],
    ],
], JSON_PRETTY_PRINT) . PHP_EOL;
