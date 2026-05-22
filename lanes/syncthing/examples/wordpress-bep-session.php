<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepSession;
use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\ClusterConfig;
use PortLibs\Syncthing\Folder;
use PortLibs\Syncthing\Request;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$session = new BepSession();

$blockedPing = $session->sendPing();
$clusterFrame = $session->sendClusterConfig(new ClusterConfig([
    new Folder(id: 'wordpress-media', label: 'WordPress Media'),
]));
$session->receiveFrame($clusterFrame);

$mediaBytes = 'featured image bytes';
$request = new Request(
    id: 12,
    folder: 'wordpress-media',
    name: 'wp-content/uploads/2026/featured.jpg',
    size: strlen($mediaBytes),
    hashHex: hash('sha256', $mediaBytes),
);
$served = $session->receiveFrame(
    BepWire::encodeRequestMessage($request),
    static fn (Request $inbound): string => $inbound->name === $request->name ? $mediaBytes : '',
);

$pendingFrame = $session->sendRequest(new Request(
    folder: 'wordpress-media',
    name: 'wp-content/uploads/2026/editorial-pending.jpg',
    size: 1024,
));
$closed = $session->receiveFrame(BepWire::encodeCloseMessage(new PortLibs\Syncthing\Close('peer restarted')));

echo json_encode([
    'blockedBeforeClusterConfig' => $blockedPing === null,
    'clusterConfigType' => BepWire::decodeMessageFrame($clusterFrame)['type'],
    'servedRequest' => [
        'event' => $served->type,
        'response' => BepWire::decodeResponseMessage($served->outboundFrames[0])->successful(),
        'bytes' => strlen(BepWire::decodeResponseMessage($served->outboundFrames[0])->data),
    ],
    'pendingOutboundRequest' => BepWire::decodeRequestMessage($pendingFrame)->id,
    'closedPending' => $closed->closedResults[0]->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
