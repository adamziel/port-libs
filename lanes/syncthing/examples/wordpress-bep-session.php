<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepSession;
use PortLibs\Syncthing\BepSessionHandlers;
use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\ClusterConfig;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\Folder;
use PortLibs\Syncthing\Index;
use PortLibs\Syncthing\IndexUpdate;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\Response;
use PortLibs\Syncthing\VersionVector;

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

$nativeSession = new BepSession(nativeDirectorySeparator: '\\');
$nativeSession->receiveFrame(BepWire::encodeClusterConfigMessage(new ClusterConfig([
    new Folder(id: 'wordpress-media', label: 'WordPress Media'),
])));
$nativeBlock = new Block(0, 12, hash('sha256', 'native inbound bytes'));
$nativeWireFile = new FileInfo(
    name: 'wp-content/uploads/2026/native-inbound.jpg',
    modifiedS: 1700001600,
    version: VersionVector::fromCounters([404 => 8]),
    size: 12,
    blocksHash: $nativeBlock->hashHex,
    rawBlockSize: 12,
    sequence: 404,
    blocks: [$nativeBlock],
    modifiedBy: 404,
);
$badWireFile = $nativeWireFile->withName('wp-content\\uploads\\2026\\invalid-wire-name.jpg')->withSequence(405);
$nativeIndex = $nativeSession->receiveFrame(
    BepWire::encodeIndexMessage(new Index('wordpress-media', [$nativeWireFile, $badWireFile], lastSequence: 405)),
    new BepSessionHandlers(indexHandler: static fn (Index $index): array => [
        'count' => count($index->files),
        'converted' => count($index->files) === 1
            && str_contains($index->files[0]->name, '\\')
            && !str_contains($index->files[0]->name, '/'),
    ]),
);
$nativeUpdate = $nativeSession->receiveFrame(
    BepWire::encodeIndexUpdateMessage(new IndexUpdate('wordpress-media', [
        $nativeWireFile->withSequence(406),
        $badWireFile->withSequence(407),
    ], lastSequence: 407, prevSequence: 405)),
    new BepSessionHandlers(indexUpdateHandler: static fn (IndexUpdate $update): array => [
        'count' => count($update->files),
        'converted' => count($update->files) === 1
            && str_contains($update->files[0]->name, '\\')
            && !str_contains($update->files[0]->name, '/'),
    ]),
);
$nativeRequestConverted = false;
$nativeRequest = $nativeSession->receiveFrame(
    BepWire::encodeRequestMessage(new Request(
        id: 14,
        folder: 'wordpress-media',
        name: 'wp-content/uploads/2026/native-inbound.jpg',
        size: 12,
    )),
    static function (Request $request) use (&$nativeRequestConverted): string {
        $nativeRequestConverted = str_contains($request->name, '\\') && !str_contains($request->name, '/');

        return 'native bytes';
    },
);
$nativeInvalidHandlerCalled = false;
$nativeInvalidRequest = $nativeSession->receiveFrame(
    BepWire::encodeRequestMessage(new Request(
        id: 15,
        folder: 'wordpress-media',
        name: 'wp-content\\uploads\\2026\\invalid-request.jpg',
        size: 12,
    )),
    static function () use (&$nativeInvalidHandlerCalled): string {
        $nativeInvalidHandlerCalled = true;

        return '';
    },
);
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
    'nativeInboundPathConversion' => [
        'indexEvent' => $nativeIndex->type,
        'indexPathConverted' => (bool) ($nativeIndex->handlerResult['converted'] ?? false),
        'invalidIndexEntriesFiltered' => ($nativeIndex->handlerResult['count'] ?? 0) === 1,
        'indexUpdatePathConverted' => (bool) ($nativeUpdate->handlerResult['converted'] ?? false),
        'invalidIndexUpdateEntriesFiltered' => ($nativeUpdate->handlerResult['count'] ?? 0) === 1,
        'requestPathConvertedBeforeHandler' => $nativeRequestConverted,
        'requestResponseSucceeded' => BepWire::decodeResponseMessage($nativeRequest->outboundFrames[0])->successful(),
        'invalidRequestReturnedNoSuchFile' => $nativeInvalidRequest->response?->code === Response::CODE_NO_SUCH_FILE,
        'invalidRequestSkippedHandler' => !$nativeInvalidHandlerCalled,
        'sessionStayedOpen' => !$nativeSession->isClosed(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
