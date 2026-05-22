<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\ClusterConfig;
use PortLibs\Syncthing\Device;
use PortLibs\Syncthing\Folder;
use PortLibs\Syncthing\Hello;
use PortLibs\Syncthing\ProtocolValidation;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\Response;

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
    'rejects malformed cluster config values' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => new Device(idHex: '0'));
        $t->throws(InvalidArgumentException::class, static fn () => new Device(idHex: 'GG'));
        $t->throws(InvalidArgumentException::class, static fn () => new Device(idHex: '01', compression: 99));
        $t->throws(InvalidArgumentException::class, static fn () => new Folder(id: 'default', type: 99));
        $t->throws(UnexpectedValueException::class, static fn () => BepWire::decodeRequestMessage(BepWire::encodeClusterConfigMessage(new ClusterConfig())));
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
