<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
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
    'rejects unsupported compressed and malformed post-auth frames' => static function (TestRunner $t): void {
        $compressedHeaderFrame = BepWire::encodeMessageFrame(
            BepWire::MESSAGE_TYPE_REQUEST,
            BepWire::encodeRequestPayload(new Request(name: 'valid', size: 1)),
            BepWire::MESSAGE_COMPRESSION_LZ4,
        );

        $t->throws(UnexpectedValueException::class, static fn () => BepWire::decodeMessageFrame($compressedHeaderFrame));
        $t->throws(UnexpectedValueException::class, static fn () => BepWire::decodeMessageFrame("\x00"));
        $t->throws(LengthException::class, static fn () => BepWire::decodeMessageFrame("\x00\x00" . pack('N', ProtocolValidation::MAX_MESSAGE_LEN + 1)));
        $t->throws(InvalidArgumentException::class, static fn () => BepWire::encodeMessageFrame(99, ''));
    },
];
