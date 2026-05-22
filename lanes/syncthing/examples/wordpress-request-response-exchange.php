<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\RequestExchange;
use PortLibs\Syncthing\Response;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$exchange = new RequestExchange();

$stale = $exchange->queue(new Request(
    folder: 'wordpress-media',
    name: 'wp-content/uploads/2026/missing-original.jpg',
    offset: 0,
    size: 1024,
    hashHex: hash('sha256', 'old media block'),
    blockNo: 0,
));
$staleWire = BepWire::decodeRequestMessage(BepWire::encodeRequestMessage($stale));
$staleResult = $exchange->complete(new Response(id: $staleWire->id, code: Response::CODE_NO_SUCH_FILE));

$restoredBytes = 'restored media bytes';
$retry = $exchange->queue(new Request(
    folder: 'wordpress-media',
    name: 'wp-content/uploads/2026/restored-original.jpg',
    offset: 0,
    size: strlen($restoredBytes),
    hashHex: hash('sha256', $restoredBytes),
    blockNo: 0,
));
$retryResult = $exchange->complete(new Response(id: $retry->id, data: $restoredBytes));

$pending = $exchange->queue(new Request(
    folder: 'wordpress-media',
    name: 'wp-content/uploads/2026/editorial-pending.jpg',
    size: 1024,
));
$closed = $exchange->close();

echo json_encode([
    'staleRequest' => [
        'id' => $staleWire->id,
        'name' => $staleWire->name,
        'error' => $staleResult?->error,
        'code' => $staleResult?->code,
    ],
    'retryRequest' => [
        'id' => $retry->id,
        'successful' => $retryResult?->successful(),
        'bytes' => strlen($retryResult?->data ?? ''),
    ],
    'closedPending' => [
        'id' => $pending->id,
        'result' => $closed[0]->toArray(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
