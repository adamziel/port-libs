<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepFrameStream;
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
    'maps upstream stream-backed post-auth frame boundaries' => static function (TestRunner $t): void {
        $stream = fopen('php://temp', 'w+b');
        $io = new BepFrameStream($stream);

        $config = new ClusterConfig([
            new Folder(id: 'wordpress-media', label: 'WordPress Media'),
        ]);
        $progress = new DownloadProgress('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: 'wp-content\\uploads\\2026\\hero.jpg',
                version: VersionVector::fromCounters([101 => 7]),
                blockIndexes: [0, 2],
                blockSize: BlockList::MIN_BLOCK_SIZE,
            ),
        ]);

        $clusterBytes = $io->writeClusterConfig($config);
        $progressBytes = $io->writeDownloadProgress($progress, directorySeparator: '\\');
        $t->true($clusterBytes > 0);
        $t->true($progressBytes > 0);

        rewind($stream);
        $first = $io->readFrame();
        $second = $io->readFrame();
        $t->same(BepWire::MESSAGE_TYPE_CLUSTER_CONFIG, BepWire::decodeMessageFrame($first)['type']);
        $t->same(BepWire::MESSAGE_TYPE_DOWNLOAD_PROGRESS, BepWire::decodeMessageFrame($second)['type']);
        $t->same('wp-content/uploads/2026/hero.jpg', BepWire::decodeDownloadProgressMessage($second)->updates[0]->name);

        $combined = $first . $second;
        $offset = 0;
        $t->same(BepWire::MESSAGE_TYPE_CLUSTER_CONFIG, BepFrameStream::decodeOne($combined, $offset)['type']);
        $t->same(strlen($first), $offset);
        $t->same(BepWire::MESSAGE_TYPE_DOWNLOAD_PROGRESS, BepFrameStream::decodeOne($combined, $offset)['type']);
        $t->same(strlen($combined), $offset);
    },
    'dispatches stream frames through bounded wordpress session handling' => static function (TestRunner $t): void {
        $stream = fopen('php://temp', 'w+b');
        $io = BepFrameStream::from($stream);
        $mediaBytes = 'streamed wordpress media bytes';
        $request = new Request(
            id: 51,
            folder: 'wordpress-media',
            name: 'wp-content/uploads/2026/streamed.jpg',
            size: strlen($mediaBytes),
            hashHex: hash('sha256', $mediaBytes),
        );

        $io->writeClusterConfig(new ClusterConfig([
            new Folder(id: 'wordpress-media', label: 'WordPress Media'),
        ]));
        $io->writeFrame(BepWire::encodeRequestMessage($request));
        rewind($stream);

        $session = new BepSession();
        $cluster = $io->receiveNext($session);
        $served = $io->receiveNext(
            $session,
            static fn (Request $inbound): string => $inbound->name === $request->name ? $mediaBytes : '',
        );

        $t->same(BepSession::EVENT_CLUSTER_CONFIG, $cluster->type);
        $t->true($session->hasReceivedClusterConfig());
        $t->same(BepSession::EVENT_REQUEST, $served->type);
        $t->same(1, count($served->outboundFrames));
        $response = BepWire::decodeResponseMessage($served->outboundFrames[0]);
        $t->same(51, $response->id);
        $t->same(Response::CODE_NO_ERROR, $response->code);
        $t->same($mediaBytes, $response->data);
    },
    'dispatches stream-backed index and progress frames to model callbacks' => static function (TestRunner $t): void {
        $stream = fopen('php://temp', 'w+b');
        $io = BepFrameStream::from($stream);
        $file = syncthing_stream_callback_file_info('wp-content\\uploads\\2026\\streamed-hero.jpg', 111);
        $version = $file->version;

        $io->writeClusterConfig(new ClusterConfig([
            new Folder(id: 'wordpress-media', label: 'WordPress Media'),
        ]));
        $io->writeIndex(new Index('wordpress-media', [$file], lastSequence: 111), directorySeparator: '\\');
        $io->writeIndexUpdate(new IndexUpdate('wordpress-media', [$file->withSequence(112)], lastSequence: 112, prevSequence: 111), directorySeparator: '\\');
        $io->writeDownloadProgress(new DownloadProgress('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: 'wp-content\\uploads\\2026\\streamed-hero.jpg',
                version: $version,
                blockIndexes: [0, 2],
                blockSize: BlockList::MIN_BLOCK_SIZE,
            ),
        ]), directorySeparator: '\\');
        rewind($stream);

        $seen = [];
        $handlers = BepSessionHandlers::model(
            index: static function (Index $index) use (&$seen): string {
                $seen[] = 'index:' . $index->files[0]->name;

                return $index->folder . '#' . $index->lastSequence;
            },
            indexUpdate: static function (IndexUpdate $indexUpdate) use (&$seen): string {
                $seen[] = 'index-update:' . $indexUpdate->files[0]->sequence;

                return $indexUpdate->prevSequence . '>' . $indexUpdate->lastSequence;
            },
            downloadProgress: static function (DownloadProgress $progress) use (&$seen): string {
                $seen[] = 'download-progress:' . implode(',', $progress->updates[0]->blockIndexes);

                return $progress->updates[0]->name;
            },
        );

        $session = new BepSession();
        $cluster = $io->receiveNext($session, $handlers);
        $index = $io->receiveNext($session, $handlers);
        $update = $io->receiveNext($session, $handlers);
        $progress = $io->receiveNext($session, $handlers);

        $t->same(BepSession::EVENT_CLUSTER_CONFIG, $cluster->type);
        $t->same(BepSession::EVENT_INDEX, $index->type);
        $t->same('wordpress-media#111', $index->handlerResult);
        $t->same(BepSession::EVENT_INDEX_UPDATE, $update->type);
        $t->same('111>112', $update->handlerResult);
        $t->same(BepSession::EVENT_DOWNLOAD_PROGRESS, $progress->type);
        $t->same('wp-content/uploads/2026/streamed-hero.jpg', $progress->handlerResult);
        $t->same([
            'index:wp-content/uploads/2026/streamed-hero.jpg',
            'index-update:112',
            'download-progress:0,2',
        ], $seen);
    },
    'rejects truncated and oversized stream frames like upstream readMessage' => static function (TestRunner $t): void {
        $truncatedHeader = fopen('php://temp', 'w+b');
        fwrite($truncatedHeader, "\x00\x04\x08");
        rewind($truncatedHeader);
        $t->throws(UnexpectedValueException::class, static fn () => BepFrameStream::from($truncatedHeader)->readFrame());

        $truncatedMessage = fopen('php://temp', 'w+b');
        fwrite($truncatedMessage, BepWire::encodeResponseMessage(new Response(5, 'short bytes')));
        rewind($truncatedMessage);
        $bytes = stream_get_contents($truncatedMessage);
        $truncatedMessage = fopen('php://temp', 'w+b');
        fwrite($truncatedMessage, substr($bytes, 0, -2));
        rewind($truncatedMessage);
        $t->throws(UnexpectedValueException::class, static fn () => BepFrameStream::from($truncatedMessage)->readFrame());

        $oversized = fopen('php://temp', 'w+b');
        fwrite($oversized, "\x00\x00" . pack('N', ProtocolValidation::MAX_MESSAGE_LEN + 1));
        rewind($oversized);
        $t->throws(LengthException::class, static fn () => BepFrameStream::from($oversized)->readFrame());

        $t->throws(UnexpectedValueException::class, static fn () => BepFrameStream::sliceOneFrame("\x00"));
    },
];

function syncthing_stream_callback_file_info(string $name, int $sequence): FileInfo
{
    $bytes = 'wordpress stream callback bytes ' . $sequence;
    $blockList = new BlockList();
    $blocks = $blockList->fromBytes($bytes, 64);

    return new FileInfo(
        name: $name,
        modifiedS: 1700001000 + $sequence,
        version: VersionVector::fromCounters([101 => 1700001000 + $sequence]),
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
