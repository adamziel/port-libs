<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepFrameStream;
use PortLibs\Syncthing\BepSession;
use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\ClusterConfig;
use PortLibs\Syncthing\Folder;
use PortLibs\Syncthing\Request;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wire = fopen('php://temp', 'w+b');
$stream = BepFrameStream::from($wire);
$mediaBytes = 'streamed featured image bytes';

$stream->writeClusterConfig(new ClusterConfig([
    new Folder(id: 'wordpress-media', label: 'WordPress Media'),
]));
$stream->writeRequest(new Request(
    id: 24,
    folder: 'wordpress-media',
    name: 'wp-content/uploads/2026/streamed-featured.jpg',
    size: strlen($mediaBytes),
    hashHex: hash('sha256', $mediaBytes),
));

rewind($wire);
$session = new BepSession();
$cluster = $stream->receiveNext($session);
$served = $stream->receiveNext(
    $session,
    static fn (Request $request): string => $request->name === 'wp-content/uploads/2026/streamed-featured.jpg'
        ? $mediaBytes
        : '',
);

$response = BepWire::decodeResponseMessage($served->outboundFrames[0]);

echo json_encode([
    'clusterEvent' => $cluster->type,
    'requestEvent' => $served->type,
    'requestPath' => $served->message->name,
    'responseType' => BepWire::decodeMessageFrame($served->outboundFrames[0])['type'],
    'responseBytes' => strlen($response->data),
    'successful' => $response->successful(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
