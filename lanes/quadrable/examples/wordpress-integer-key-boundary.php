<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tree = new SparseTree();
$largestSupportedRecordId = Key::MAX_INTEGER;
$largestKey = Key::fromInteger($largestSupportedRecordId);

$tree->putKey($largestKey, 'wp_posts:' . $largestSupportedRecordId . '=Largest native PHP snapshot id');

$overflowAccepted = true;
$overflowError = null;

try {
    Key::fromInteger(Key::MAX_INTEGER + 1);
} catch (InvalidArgumentException $exception) {
    $overflowAccepted = false;
    $overflowError = $exception->getMessage();
}

echo json_encode([
    'largestSupportedRecordId' => $largestSupportedRecordId,
    'largestRecordKey' => $largestKey->hex(),
    'largestRecordValue' => $tree->getKey($largestKey),
    'overflowAccepted' => $overflowAccepted,
    'overflowError' => $overflowError,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
