<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\ClusterConfig;
use PortLibs\Syncthing\Device;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\Folder;
use PortLibs\Syncthing\Hello;
use PortLibs\Syncthing\Index;
use PortLibs\Syncthing\IndexUpdate;
use PortLibs\Syncthing\ProtocolValidation;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\Response;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream v14 hello protobuf frame fixture' => static function (TestRunner $t): void {
        $hello = new Hello(
            deviceName: 'test device',
            clientName: 'syncthing',
            clientVersion: 'v0.14.5',
        );
        $expectedHex = '2ea7d90b00210a0b7465737420646576696365120973796e637468696e671a0776302e31342e35';

        $frame = BepWire::encodeHelloFrame($hello);
        $t->same($expectedHex, bin2hex($frame));

        $decoded = BepWire::decodeHelloFrame($frame);
        $t->same('test device', $decoded->deviceName);
        $t->same('syncthing', $decoded->clientName);
        $t->same('v0.14.5', $decoded->clientVersion);
        $t->same(0, $decoded->timestamp);
    },
    'maps upstream old and unknown hello magic handling' => static function (TestRunner $t): void {
        foreach (['00010001', '00010000', '9f79bc40'] as $oldMagic) {
            $t->throws(UnexpectedValueException::class, static fn () => BepWire::decodeHelloFrame(hex2bin($oldMagic)));
        }

        $t->throws(UnexpectedValueException::class, static fn () => BepWire::decodeHelloFrame(hex2bin('12345678')));
        $t->throws(LengthException::class, static fn () => BepWire::decodeHelloFrame(hex2bin('2ea7d90b8000')));
    },
    'maps request protobuf fields and uncompressed post-auth frame' => static function (TestRunner $t): void {
        $hash = hash('sha256', 'block');
        $request = new Request(
            id: 7,
            folder: 'wordpress',
            name: 'wp-content/uploads/hero.jpg',
            offset: 131072,
            size: 65536,
            hashHex: $hash,
            fromTemporary: true,
            blockNo: 3,
        );
        $expectedPayloadHex = '08071209776f726470726573731a1b77702d636f6e74656e742f75706c6f6164732f6865726f2e6a706720808008288080043220'
            . $hash
            . '38014803';

        $payload = BepWire::encodeRequestPayload($request);
        $t->same($expectedPayloadHex, bin2hex($payload));

        $frame = BepWire::encodeRequestMessage($request);
        $t->same('00020803', bin2hex(substr($frame, 0, 4)));
        $t->same(strlen($payload), unpack('N', substr($frame, 4, 4))[1]);

        $decoded = BepWire::decodeRequestMessage($frame);
        $t->same($request->folder, $decoded->folder);
        $t->same($request->name, $decoded->name);
        $t->same($request->offset, $decoded->offset);
        $t->same($request->size, $decoded->size);
        $t->same($request->hashHex, $decoded->hashHex);
        $t->true($decoded->fromTemporary);
        $t->same(3, $decoded->blockNo);
    },
    'maps response protobuf fields and error codes' => static function (TestRunner $t): void {
        $response = new Response(id: 7, data: 'bytes', code: Response::CODE_NO_SUCH_FILE);
        $payload = BepWire::encodeResponsePayload($response);

        $t->same('0807120562797465731802', bin2hex($payload));

        $frame = BepWire::encodeResponseMessage($response);
        $t->same('00020804', bin2hex(substr($frame, 0, 4)));

        $decoded = BepWire::decodeResponseMessage($frame);
        $t->same(7, $decoded->id);
        $t->same('bytes', $decoded->data);
        $t->same(Response::CODE_NO_SUCH_FILE, $decoded->code);
    },
    'maps upstream lz4 block compatibility fixture' => static function (TestRunner $t): void {
        $uncompressed = 'this is some arbitrary yet fairly compressible data';
        $oldCompressedHex = '00000033f0247468697320697320736f6d65206172626974726172792079657420666169726c7920636f6d707265737369626c652064617461';

        $t->same($uncompressed, BepWire::decompressLz4Block(hex2bin($oldCompressedHex)));
        $t->same($oldCompressedHex, bin2hex(BepWire::compressLz4Block($uncompressed)));
    },
    'maps compressed response post-auth frames' => static function (TestRunner $t): void {
        $data = str_repeat('wordpress-media-block:', 48);
        $response = new Response(id: 9, data: $data, code: Response::CODE_NO_ERROR);
        $payload = BepWire::encodeResponsePayload($response);

        $frame = BepWire::encodeResponseMessage($response, Device::COMPRESSION_ALWAYS);
        $t->same('000408041001', bin2hex(substr($frame, 0, 6)));

        $message = BepWire::decodeMessageFrame($frame);
        $t->same(BepWire::MESSAGE_TYPE_RESPONSE, $message['type']);
        $t->same(BepWire::MESSAGE_COMPRESSION_LZ4, $message['compression']);
        $t->same($payload, $message['payload']);

        $decoded = BepWire::decodeResponseMessage($frame);
        $t->same(9, $decoded->id);
        $t->same($data, $decoded->data);
        $t->same(Response::CODE_NO_ERROR, $decoded->code);
    },
    'maps upstream compression mode threshold and fallback rules' => static function (TestRunner $t): void {
        $small = BepWire::encodeMessageFrameWithCompressionMode(
            BepWire::MESSAGE_TYPE_REQUEST,
            str_repeat('x', BepWire::COMPRESSION_THRESHOLD - 1),
            Device::COMPRESSION_ALWAYS,
        );
        $t->same(BepWire::MESSAGE_COMPRESSION_NONE, BepWire::decodeMessageFrame($small)['compression']);

        $incompressible = '';
        for ($i = 0; $i < 160; $i++) {
            $incompressible .= chr($i);
        }
        $fallback = BepWire::encodeMessageFrameWithCompressionMode(
            BepWire::MESSAGE_TYPE_REQUEST,
            $incompressible,
            Device::COMPRESSION_ALWAYS,
        );
        $fallbackMessage = BepWire::decodeMessageFrame($fallback);
        $t->same(BepWire::MESSAGE_COMPRESSION_NONE, $fallbackMessage['compression']);
        $t->same($incompressible, $fallbackMessage['payload']);

        $metadataResponse = BepWire::encodeMessageFrameWithCompressionMode(
            BepWire::MESSAGE_TYPE_RESPONSE,
            str_repeat('response-bytes', 32),
            Device::COMPRESSION_METADATA,
        );
        $t->same(BepWire::MESSAGE_COMPRESSION_NONE, BepWire::decodeMessageFrame($metadataResponse)['compression']);
    },
    'maps metadata compression for wordpress request payloads' => static function (TestRunner $t): void {
        $request = new Request(
            id: 11,
            folder: 'wordpress-media',
            name: 'wp-content/uploads/2026/' . str_repeat('hero-', 48) . '.jpg',
            size: 131072,
        );
        $payload = BepWire::encodeRequestPayload($request);
        $frame = BepWire::encodeRequestMessage($request, Device::COMPRESSION_METADATA);
        $message = BepWire::decodeMessageFrame($frame);

        $t->same(BepWire::MESSAGE_TYPE_REQUEST, $message['type']);
        $t->same(BepWire::MESSAGE_COMPRESSION_LZ4, $message['compression']);
        $t->same($payload, $message['payload']);
        $t->same($request->name, BepWire::decodeRequestMessage($frame)->name);
    },
    'maps cluster config folder and device protobuf fields' => static function (TestRunner $t): void {
        $config = new ClusterConfig([
            new Folder(
                id: 'default',
                label: 'Default',
                devices: [
                    new Device(
                        idHex: '01',
                        name: 'peer',
                        addresses: ['dynamic'],
                        compression: Device::COMPRESSION_NEVER,
                    ),
                ],
            ),
        ], secondary: true);
        $expectedPayloadHex = '0a290a0764656661756c74120744656661756c748201140a01011204706565721a0764796e616d696320011001';

        $payload = BepWire::encodeClusterConfigPayload($config);
        $t->same($expectedPayloadHex, bin2hex($payload));

        $decoded = BepWire::decodeClusterConfigPayload($payload);
        $t->true($decoded->secondary);
        $t->same('default', $decoded->folders[0]->id);
        $t->same('Default', $decoded->folders[0]->label);
        $t->true($decoded->folders[0]->isRunning());
        $t->same('01', $decoded->folders[0]->devices[0]->idHex);
        $t->same(['dynamic'], $decoded->folders[0]->devices[0]->addresses);
        $t->same(Device::COMPRESSION_NEVER, $decoded->folders[0]->devices[0]->compression);
    },
    'maps cluster config device options and post-auth frame type' => static function (TestRunner $t): void {
        $config = new ClusterConfig([
            new Folder(
                id: 'wordpress-media',
                label: 'WordPress Media',
                type: Folder::TYPE_RECEIVE_ONLY,
                stopReason: Folder::STOP_REASON_PAUSED,
                devices: [
                    new Device(
                        idHex: '01020304',
                        name: 'playground',
                        addresses: ['tcp://127.0.0.1:22000', 'dynamic'],
                        compression: Device::COMPRESSION_ALWAYS,
                        certName: 'wp.local',
                        maxSequence: 22,
                        introducer: true,
                        indexId: 42,
                        skipIntroductionRemovals: true,
                        encryptionPasswordTokenHex: '746f6b656e',
                    ),
                ],
            ),
        ]);

        $payload = BepWire::encodeClusterConfigPayload($config);
        $frame = BepWire::encodeClusterConfigMessage($config);
        $t->same('0000', bin2hex(substr($frame, 0, 2)));
        $t->same(strlen($payload), unpack('N', substr($frame, 2, 4))[1]);

        $decoded = BepWire::decodeClusterConfigMessage($frame);
        $folder = $decoded->folders[0];
        $device = $folder->devices[0];
        $t->same('WordPress Media', $folder->label);
        $t->same(Folder::TYPE_RECEIVE_ONLY, $folder->type);
        $t->true(!$folder->isRunning());
        $t->same('"WordPress Media" (wordpress-media)', $folder->description());
        $t->same(['tcp://127.0.0.1:22000', 'dynamic'], $device->addresses);
        $t->same(Device::COMPRESSION_ALWAYS, $device->compression);
        $t->same('wp.local', $device->certName);
        $t->same(22, $device->maxSequence);
        $t->true($device->introducer);
        $t->same(42, $device->indexId);
        $t->true($device->skipIntroductionRemovals);
        $t->same('746f6b656e', $device->encryptionPasswordTokenHex);
    },
    'maps file info protobuf blocks version flags hashes and platform fields' => static function (TestRunner $t): void {
        $blockList = new BlockList();
        $blocks = $blockList->fromBytes(str_repeat('wordpress-block-', 4), 16);
        $blocksHash = $blockList->hashBlocks($blocks);
        $previousBlocksHash = hash('sha256', 'previous wordpress media bytes');
        $file = new FileInfo(
            name: 'wp-content/uploads/2026/hero.jpg',
            modifiedS: 1700000400,
            modifiedNs: 123456789,
            version: VersionVector::fromCounters([11 => 5, 7 => 9]),
            localFlags: FileInfo::FLAG_LOCAL_IGNORED,
            size: 64,
            blocksHash: $blocksHash,
            previousBlocksHash: $previousBlocksHash,
            permissions: 0644,
            noPermissions: true,
            rawBlockSize: 16,
            sequence: 33,
            blocks: $blocks,
            unixOwnerName: 'www-data',
            unixGroupName: 'www-data',
            unixUid: 33,
            unixGid: 33,
            modifiedBy: 11,
        );

        $payload = BepWire::encodeFileInfoPayload($file);
        $decoded = BepWire::decodeFileInfoPayload($payload);

        $t->same($file->name, $decoded->name);
        $t->same($file->size, $decoded->size);
        $t->same($file->modifiedS, $decoded->modifiedS);
        $t->same($file->modifiedNs, $decoded->modifiedNs);
        $t->same([7 => 9, 11 => 5], $decoded->version->toArray());
        $t->same(33, $decoded->sequence);
        $t->same(11, $decoded->modifiedBy);
        $t->same(FileInfo::FLAG_LOCAL_REMOTE_INVALID, $decoded->localFlags);
        $t->true($decoded->isInvalid());
        $t->true($decoded->noPermissions);
        $t->same(0644, $decoded->permissions);
        $t->same(16, $decoded->rawBlockSize);
        $t->same($blocksHash, $decoded->blocksHash);
        $t->same($previousBlocksHash, $decoded->previousBlocksHash);
        $t->same(count($blocks), count($decoded->blocks));
        $t->same($blocks[1]->offset, $decoded->blocks[1]->offset);
        $t->same($blocks[1]->size, $decoded->blocks[1]->size);
        $t->same($blocks[1]->hashHex, $decoded->blocks[1]->hashHex);
        $t->same('www-data', $decoded->unixOwnerName);
        $t->same('www-data', $decoded->unixGroupName);
        $t->same(33, $decoded->unixUid);
        $t->same(33, $decoded->unixGid);
    },
    'maps index and index update protobuf payloads and frame types' => static function (TestRunner $t): void {
        $blockList = new BlockList();
        $blocks = $blockList->fromBytes('wordpress media bytes', 64);
        $file = new FileInfo(
            name: 'wp-content\\uploads\\2026\\hero.jpg',
            modifiedS: 1700000500,
            version: VersionVector::fromCounters([101 => 1700000500]),
            size: strlen('wordpress media bytes'),
            blocksHash: $blockList->hashBlocks($blocks),
            permissions: 0644,
            rawBlockSize: 64,
            sequence: 44,
            blocks: [new Block($blocks[0]->offset, $blocks[0]->size, $blocks[0]->hashHex)],
            modifiedBy: 101,
        );

        $index = (new Index('wordpress-media', [$file], lastSequence: 44))->normalizedForWire('\\');
        $indexFrame = BepWire::encodeIndexMessage($index);
        $t->same('00020801', bin2hex(substr($indexFrame, 0, 4)));
        $decodedIndex = BepWire::decodeIndexMessage($indexFrame);
        $t->same('wordpress-media', $decodedIndex->folder);
        $t->same(44, $decodedIndex->lastSequence);
        $t->same('wp-content/uploads/2026/hero.jpg', $decodedIndex->files[0]->name);
        $t->same($file->blocks[0]->hashHex, $decodedIndex->files[0]->blocks[0]->hashHex);

        $update = new IndexUpdate($index->folder, $index->files, lastSequence: 47, prevSequence: 44);
        $updateFrame = BepWire::encodeIndexUpdateMessage($update);
        $t->same('00020802', bin2hex(substr($updateFrame, 0, 4)));
        $decodedUpdate = BepWire::decodeIndexUpdateMessage($updateFrame);
        $t->same('wordpress-media', $decodedUpdate->folder);
        $t->same(47, $decodedUpdate->lastSequence);
        $t->same(44, $decodedUpdate->prevSequence);
        $t->same([101 => 1700000500], $decodedUpdate->files[0]->version->toArray());
        $t->same($file->blocksHash, $decodedUpdate->files[0]->blocksHash);
    },
    'maps deleted and symlink file info wire fields' => static function (TestRunner $t): void {
        $deleted = (new FileInfo(
            name: 'wp-content/uploads/2025/old-hero.jpg',
            modifiedS: 1700000000,
            version: VersionVector::fromCounters([101 => 3]),
            size: 12,
            blocksHash: hash('sha256', 'old hero'),
            blocks: [new Block(0, 12, hash('sha256', 'old hero'))],
        ))->withDeleted(101, 1700000600);
        $decodedDeleted = BepWire::decodeFileInfoPayload(BepWire::encodeFileInfoPayload($deleted));

        $t->true($decodedDeleted->deleted);
        $t->same(0, $decodedDeleted->size);
        $t->same([], $decodedDeleted->blocks);
        $t->same('', $decodedDeleted->blocksHash);
        $t->same(101, $decodedDeleted->modifiedBy);

        $symlink = new FileInfo(
            name: 'wp-content/uploads/current',
            modifiedS: 1700000610,
            version: VersionVector::fromCounters([101 => 4]),
            type: FileInfo::TYPE_SYMLINK,
            symlinkTarget: '2026',
            sequence: 51,
        );
        $decodedSymlink = BepWire::decodeFileInfoPayload(BepWire::encodeFileInfoPayload($symlink));
        $t->same(FileInfo::TYPE_SYMLINK, $decodedSymlink->type);
        $t->same('2026', $decodedSymlink->symlinkTarget);
        $t->same(51, $decodedSymlink->sequence);
    },
    'rejects malformed cluster config values' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => new Device(idHex: '0'));
        $t->throws(InvalidArgumentException::class, static fn () => new Device(idHex: 'GG'));
        $t->throws(InvalidArgumentException::class, static fn () => new Device(idHex: '01', compression: 99));
        $t->throws(InvalidArgumentException::class, static fn () => new Folder(id: 'default', type: 99));
        $t->throws(UnexpectedValueException::class, static fn () => BepWire::decodeRequestMessage(BepWire::encodeClusterConfigMessage(new ClusterConfig())));
        $t->throws(UnexpectedValueException::class, static fn () => BepWire::decodeIndexUpdateMessage(BepWire::encodeIndexMessage(new Index('default'))));
        $t->throws(InvalidArgumentException::class, static fn () => new Index('default', lastSequence: -1));
        $t->throws(InvalidArgumentException::class, static fn () => new IndexUpdate('default', prevSequence: -1));
    },
    'rejects malformed compressed and post-auth frames' => static function (TestRunner $t): void {
        $unknownCompressionHeader = "\x00\x04\x08\x03\x10\x02" . pack('N', 0);
        $truncatedCompressedFrame = "\x00\x04\x08\x03\x10\x01" . pack('N', 3) . 'bad';
        $invalidCompressedOffset = "\x00\x04\x08\x03\x10\x01" . pack('N', 7) . "\x00\x00\x00\x04\x00\x01\x00";

        $t->throws(UnexpectedValueException::class, static fn () => BepWire::decodeMessageFrame($unknownCompressionHeader));
        $t->throws(UnexpectedValueException::class, static fn () => BepWire::decodeMessageFrame($truncatedCompressedFrame));
        $t->throws(UnexpectedValueException::class, static fn () => BepWire::decodeMessageFrame($invalidCompressedOffset));
        $t->throws(UnexpectedValueException::class, static fn () => BepWire::decodeMessageFrame("\x00"));
        $t->throws(LengthException::class, static fn () => BepWire::decodeMessageFrame("\x00\x00" . pack('N', ProtocolValidation::MAX_MESSAGE_LEN + 1)));
        $t->throws(InvalidArgumentException::class, static fn () => BepWire::encodeMessageFrame(99, ''));
        $t->throws(InvalidArgumentException::class, static fn () => BepWire::encodeMessageFrameWithCompressionMode(BepWire::MESSAGE_TYPE_REQUEST, '', 99));
    },
];
