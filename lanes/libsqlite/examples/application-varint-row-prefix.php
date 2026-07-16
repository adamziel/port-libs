<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVarint;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rowId = isset($argv[1]) ? (int) $argv[1] : 1;
$payloadLength = isset($argv[2]) ? (int) $argv[2] : 128;

if ($rowId < 0 || $payloadLength < 0) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-varint-row-prefix.php [rowid] [payload-bytes]\n");
    fwrite(STDERR, "Both values must be non-negative integers for this Application wp_options cell-prefix preflight.\n");
    exit(1);
}

$payloadLengthVarint = SQLiteVarint::encode($payloadLength);
$rowIdVarint = SQLiteVarint::encode($rowId);

echo json_encode([
    'applicationUse' => 'Preflight the varint prefixes for a generated wp_options table-leaf cell before writing or repairing a SQLite database image without the SQLite extension.',
    'rowId' => $rowId,
    'payloadLength' => $payloadLength,
    'payloadLengthVarintHex' => bin2hex($payloadLengthVarint),
    'rowIdVarintHex' => bin2hex($rowIdVarint),
    'cellPrefixHex' => bin2hex($payloadLengthVarint . $rowIdVarint),
    'cellPrefixBytes' => strlen($payloadLengthVarint) + strlen($rowIdVarint),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
