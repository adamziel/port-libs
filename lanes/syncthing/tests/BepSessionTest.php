<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepSession;
use PortLibs\Syncthing\BepSessionHandlers;
use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\ClusterConfig;
use PortLibs\Syncthing\DownloadProgress;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\Folder;
use PortLibs\Syncthing\Index;
use PortLibs\Syncthing\IndexUpdate;
use PortLibs\Syncthing\ProtocolValidation;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\Response;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream cluster-config-first writer boundary' => static function (TestRunner $t): void {
        $session = new BepSession();

        $t->same(null, $session->sendPing());
        $t->same(null, $session->sendRequest(new Request(folder: 'wordpress-media', name: 'hero.jpg', size: 1024)));
        $t->same([], $session->pendingRequestIds());

        $clusterFrame = $session->sendClusterConfig(new ClusterConfig([
            new Folder(id: 'wordpress-media', label: 'WordPress Media'),
        ]));
        $t->true(is_string($clusterFrame));
        $t->true($session->hasSentClusterConfig());
        $t->same(BepWire::MESSAGE_TYPE_CLUSTER_CONFIG, BepWire::decodeMessageFrame($clusterFrame)['type']);

        $ping = $session->sendPing();
        $t->true(is_string($ping));
        $t->same(BepWire::MESSAGE_TYPE_PING, BepWire::decodeMessageFrame($ping)['type']);

        $requestFrame = $session->sendRequest(new Request(
            folder: 'wordpress-media',
            name: 'wp-content/uploads/2026/hero.jpg',
            size: 2048,
        ));
        $t->true(is_string($requestFrame));
        $request = BepWire::decodeRequestMessage($requestFrame);
        $t->same(0, $request->id);
        $t->same('wp-content/uploads/2026/hero.jpg', $request->name);
        $t->same([0], $session->pendingRequestIds());
    },
    'maps upstream dispatcher cluster-config first close and unknown handling' => static function (TestRunner $t): void {
        $unknownFirst = new BepSession();
        $unknown = $unknownFirst->receiveFrame(syncthing_unknown_post_auth_frame());
        $t->same(BepSession::EVENT_IGNORED_UNKNOWN, $unknown->type);
        $t->same(99, $unknown->messageType);
        $t->true(!$unknownFirst->isClosed());

        $pingFirst = new BepSession();
        $protocolError = $pingFirst->receiveFrame(BepWire::encodePingMessage());
        $t->same(BepSession::EVENT_PROTOCOL_ERROR, $protocolError->type);
        $t->contains('invalid state 0 for ping', $protocolError->error ?? '');
        $t->true($pingFirst->isClosed());

        $closeFirst = new BepSession();
        $closed = $closeFirst->receiveFrame(BepWire::encodeCloseMessage(new PortLibs\Syncthing\Close('maintenance')));
        $t->same(BepSession::EVENT_CLOSE, $closed->type);
        $t->same('closed by remote: maintenance', $closed->error);
        $t->true($closeFirst->isClosed());

        $ready = new BepSession();
        $cluster = $ready->receiveFrame(BepWire::encodeClusterConfigMessage(new ClusterConfig()));
        $t->same(BepSession::EVENT_CLUSTER_CONFIG, $cluster->type);
        $t->true($ready->hasReceivedClusterConfig());
        $ping = $ready->receiveFrame(BepWire::encodePingMessage());
        $t->same(BepSession::EVENT_PING, $ping->type);
        $t->true(!$ready->isClosed());
    },
    'maps inbound response matching and close-time pending request drain' => static function (TestRunner $t): void {
        $session = new BepSession();
        $session->sendClusterConfig(new ClusterConfig());
        $session->receiveFrame(BepWire::encodeClusterConfigMessage(new ClusterConfig()));

        $first = $session->sendRequest(new Request(folder: 'wordpress-media', name: 'hero.jpg', size: 1024));
        $second = $session->sendRequest(new Request(folder: 'wordpress-media', name: 'poster.jpg', size: 1024));
        $t->true(is_string($first));
        $t->true(is_string($second));
        $t->same([0, 1], $session->pendingRequestIds());

        $hit = $session->receiveFrame(BepWire::encodeResponseMessage(new Response(0, 'image bytes')));
        $t->same(BepSession::EVENT_RESPONSE, $hit->type);
        $t->same(0, $hit->requestResult?->id);
        $t->same('image bytes', $hit->requestResult?->data);
        $t->same([1], $session->pendingRequestIds());

        $late = $session->receiveFrame(BepWire::encodeResponseMessage(new Response(77, 'late bytes')));
        $t->same(BepSession::EVENT_RESPONSE, $late->type);
        $t->same(null, $late->requestResult);
        $t->same([1], $session->pendingRequestIds());

        $closed = $session->receiveFrame(BepWire::encodeCloseMessage(new PortLibs\Syncthing\Close('peer restart')));
        $t->same(BepSession::EVENT_CLOSE, $closed->type);
        $t->same(1, $closed->closedResults[0]->id);
        $t->same(Response::ERROR_CLOSED, $closed->closedResults[0]->error);
        $t->same([], $session->pendingRequestIds());
    },
    'serves inbound wordpress requests after cluster config and closes on invalid requests' => static function (TestRunner $t): void {
        $session = new BepSession();
        $session->receiveFrame(BepWire::encodeClusterConfigMessage(new ClusterConfig()));

        $request = new Request(
            id: 42,
            folder: 'wordpress-media',
            name: 'wp-content/uploads/2026/hero.jpg',
            size: 11,
            hashHex: hash('sha256', 'media bytes'),
        );
        $event = $session->receiveFrame(
            BepWire::encodeRequestMessage($request),
            static fn (Request $inbound): string => $inbound->name === $request->name ? 'media bytes' : '',
        );

        $t->same(BepSession::EVENT_REQUEST, $event->type);
        $t->same(42, $event->message->id);
        $t->same(1, count($event->outboundFrames));
        $response = BepWire::decodeResponseMessage($event->outboundFrames[0]);
        $t->same(42, $response->id);
        $t->same('media bytes', $response->data);
        $t->same(Response::CODE_NO_ERROR, $response->code);

        $invalid = $session->receiveFrame(BepWire::encodeRequestMessage(new Request(
            id: 43,
            folder: 'wordpress-media',
            name: '../wp-config.php',
            size: ProtocolValidation::MAX_REQUEST_SIZE,
        )));
        $t->same(BepSession::EVENT_PROTOCOL_ERROR, $invalid->type);
        $t->contains('filename is invalid', $invalid->error ?? '');
        $t->true($session->isClosed());
    },
    'dispatches inbound model messages to registered wordpress callbacks' => static function (TestRunner $t): void {
        $seen = [];
        $session = (new BepSession())
            ->onIndex(static function (Index $index) use (&$seen): array {
                $seen[] = 'index:' . $index->folder . ':' . count($index->files);

                return ['lastSequence' => $index->lastSequence];
            })
            ->onIndexUpdate(static function (IndexUpdate $indexUpdate) use (&$seen): array {
                $seen[] = 'index-update:' . $indexUpdate->folder . ':' . $indexUpdate->prevSequence . '>' . $indexUpdate->lastSequence;

                return ['file' => $indexUpdate->files[0]->name];
            })
            ->onDownloadProgress(static function (DownloadProgress $progress) use (&$seen): array {
                $seen[] = 'download-progress:' . $progress->folder . ':' . implode(',', $progress->updates[0]->blockIndexes);

                return ['updates' => count($progress->updates)];
            });
        $session->receiveFrame(BepWire::encodeClusterConfigMessage(new ClusterConfig()));

        $file = syncthing_session_callback_file_info('wp-content/uploads/2026/hero.jpg', 91);
        $indexEvent = $session->receiveFrame(BepWire::encodeIndexMessage(new Index('wordpress-media', [$file], lastSequence: 91)));
        $updateEvent = $session->receiveFrame(BepWire::encodeIndexUpdateMessage(new IndexUpdate(
            'wordpress-media',
            [$file->withSequence(92)],
            lastSequence: 92,
            prevSequence: 91,
        )));
        $progressEvent = $session->receiveFrame(BepWire::encodeDownloadProgressMessage(new DownloadProgress('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: 'wp-content/uploads/2026/hero.jpg',
                version: $file->version,
                blockIndexes: [0, 1],
                blockSize: BlockList::MIN_BLOCK_SIZE,
            ),
        ])));

        $t->same(BepSession::EVENT_INDEX, $indexEvent->type);
        $t->same(['lastSequence' => 91], $indexEvent->handlerResult);
        $t->same(BepSession::EVENT_INDEX_UPDATE, $updateEvent->type);
        $t->same(['file' => 'wp-content/uploads/2026/hero.jpg'], $updateEvent->handlerResult);
        $t->same(BepSession::EVENT_DOWNLOAD_PROGRESS, $progressEvent->type);
        $t->same(['updates' => 1], $progressEvent->handlerResult);
        $t->same([
            'index:wordpress-media:1',
            'index-update:wordpress-media:91>92',
            'download-progress:wordpress-media:0,1',
        ], $seen);
        $t->true(!$session->isClosed());
    },
    'closes session when a provided model callback fails' => static function (TestRunner $t): void {
        $session = new BepSession();
        $session->receiveFrame(BepWire::encodeClusterConfigMessage(new ClusterConfig()));
        $file = syncthing_session_callback_file_info('wp-content/uploads/2026/failing.jpg', 93);

        $event = $session->receiveFrame(
            BepWire::encodeIndexMessage(new Index('wordpress-media', [$file], lastSequence: 93)),
            BepSessionHandlers::model(index: static function (Index $index): void {
                throw new RuntimeException('catalog write failed for ' . $index->folder);
            }),
        );

        $t->same(BepSession::EVENT_HANDLER_ERROR, $event->type);
        $t->contains('handling index for wordpress-media: catalog write failed for wordpress-media', $event->error ?? '');
        $t->true($event->closed());
        $t->true($session->isClosed());

        $closed = $session->receiveFrame(BepWire::encodePingMessage());
        $t->same(BepSession::EVENT_CLOSED, $closed->type);
    },
];

function syncthing_unknown_post_auth_frame(): string
{
    return hex2bin('0002086300000000');
}

function syncthing_session_callback_file_info(string $name, int $sequence): FileInfo
{
    $bytes = 'wordpress session callback bytes ' . $sequence;
    $blockList = new BlockList();
    $blocks = $blockList->fromBytes($bytes, 64);

    return new FileInfo(
        name: $name,
        modifiedS: 1700000900 + $sequence,
        version: VersionVector::fromCounters([101 => 1700000900 + $sequence]),
        size: strlen($bytes),
        blocksHash: $blockList->hashBlocks($blocks),
        rawBlockSize: 64,
        sequence: $sequence,
        blocks: [
            new Block($blocks[0]->offset, $blocks[0]->size, $blocks[0]->hashHex),
        ],
        modifiedBy: 101,
    );
}
