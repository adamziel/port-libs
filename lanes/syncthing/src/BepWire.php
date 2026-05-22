<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class BepWire
{
    public const MESSAGE_TYPE_CLUSTER_CONFIG = 0;
    public const MESSAGE_TYPE_INDEX = 1;
    public const MESSAGE_TYPE_INDEX_UPDATE = 2;
    public const MESSAGE_TYPE_REQUEST = 3;
    public const MESSAGE_TYPE_RESPONSE = 4;
    public const MESSAGE_TYPE_DOWNLOAD_PROGRESS = 5;
    public const MESSAGE_TYPE_PING = 6;
    public const MESSAGE_TYPE_CLOSE = 7;

    public const MESSAGE_COMPRESSION_NONE = 0;
    public const MESSAGE_COMPRESSION_LZ4 = 1;
    public const COMPRESSION_THRESHOLD = 128;

    public static function encodeHelloFrame(Hello $hello): string
    {
        $payload = self::encodeHelloPayload($hello);
        if (strlen($payload) > 32767) {
            throw new \LengthException('hello message too big');
        }

        return pack('Nn', Hello::MESSAGE_MAGIC, strlen($payload)) . $payload;
    }

    public static function decodeHelloFrame(string $frame): Hello
    {
        if (strlen($frame) < 4) {
            throw new \UnexpectedValueException('reading hello magic failed');
        }

        $magic = unpack('N', substr($frame, 0, 4))[1];
        if (in_array($magic, [0x00010001, 0x00010000, Hello::VERSION_13_MAGIC], true)) {
            throw new \UnexpectedValueException('the remote device speaks an older version of the protocol');
        }
        if ($magic !== Hello::MESSAGE_MAGIC) {
            throw new \UnexpectedValueException('the remote device speaks an unknown version of the protocol');
        }
        if (strlen($frame) < 6) {
            throw new \UnexpectedValueException('reading hello length failed');
        }

        $size = unpack('n', substr($frame, 4, 2))[1];
        if ($size > 32767) {
            throw new \LengthException('hello message too big');
        }
        if (strlen($frame) < 6 + $size) {
            throw new \UnexpectedValueException('reading hello message failed');
        }

        return self::decodeHelloPayload(substr($frame, 6, $size));
    }

    public static function encodeRequestMessage(Request $request, int $compressionMode = Device::COMPRESSION_NEVER): string
    {
        return self::encodeMessageFrameWithCompressionMode(
            self::MESSAGE_TYPE_REQUEST,
            self::encodeRequestPayload($request),
            $compressionMode,
        );
    }

    public static function decodeRequestMessage(string $frame): Request
    {
        $message = self::decodeMessageFrame($frame);
        if ($message['type'] !== self::MESSAGE_TYPE_REQUEST) {
            throw new \UnexpectedValueException('expected request message');
        }

        return self::decodeRequestPayload($message['payload']);
    }

    public static function encodeResponseMessage(Response $response, int $compressionMode = Device::COMPRESSION_NEVER): string
    {
        return self::encodeMessageFrameWithCompressionMode(
            self::MESSAGE_TYPE_RESPONSE,
            self::encodeResponsePayload($response),
            $compressionMode,
        );
    }

    public static function decodeResponseMessage(string $frame): Response
    {
        $message = self::decodeMessageFrame($frame);
        if ($message['type'] !== self::MESSAGE_TYPE_RESPONSE) {
            throw new \UnexpectedValueException('expected response message');
        }

        return self::decodeResponsePayload($message['payload']);
    }

    public static function encodeClusterConfigMessage(ClusterConfig $config, int $compressionMode = Device::COMPRESSION_NEVER): string
    {
        return self::encodeMessageFrameWithCompressionMode(
            self::MESSAGE_TYPE_CLUSTER_CONFIG,
            self::encodeClusterConfigPayload($config),
            $compressionMode,
        );
    }

    public static function decodeClusterConfigMessage(string $frame): ClusterConfig
    {
        $message = self::decodeMessageFrame($frame);
        if ($message['type'] !== self::MESSAGE_TYPE_CLUSTER_CONFIG) {
            throw new \UnexpectedValueException('expected cluster config message');
        }

        return self::decodeClusterConfigPayload($message['payload']);
    }

    public static function encodeMessageFrame(int $messageType, string $payload, int $compression = self::MESSAGE_COMPRESSION_NONE): string
    {
        if ($messageType < self::MESSAGE_TYPE_CLUSTER_CONFIG || $messageType > self::MESSAGE_TYPE_CLOSE) {
            throw new \InvalidArgumentException('Unknown BEP message type');
        }
        if (!in_array($compression, [self::MESSAGE_COMPRESSION_NONE, self::MESSAGE_COMPRESSION_LZ4], true)) {
            throw new \InvalidArgumentException('Unknown BEP message compression');
        }
        if (strlen($payload) > ProtocolValidation::MAX_MESSAGE_LEN) {
            throw new \LengthException('message length exceeds maximum');
        }

        if ($compression === self::MESSAGE_COMPRESSION_LZ4) {
            $payload = self::compressLz4Block($payload);
            if (strlen($payload) > ProtocolValidation::MAX_MESSAGE_LEN) {
                throw new \LengthException('message length exceeds maximum');
            }
        }

        $header = self::encodeHeaderPayload($messageType, $compression);
        if (strlen($header) > 0xffff) {
            throw new \LengthException('header length exceeds maximum');
        }

        return pack('n', strlen($header)) . $header . pack('N', strlen($payload)) . $payload;
    }

    public static function encodeMessageFrameWithCompressionMode(int $messageType, string $payload, int $compressionMode): string
    {
        if (!in_array($compressionMode, [
            Device::COMPRESSION_METADATA,
            Device::COMPRESSION_NEVER,
            Device::COMPRESSION_ALWAYS,
        ], true)) {
            throw new \InvalidArgumentException('Unknown Syncthing compression mode');
        }

        if (!self::shouldCompressMessage($messageType, $payload, $compressionMode)) {
            return self::encodeMessageFrame($messageType, $payload);
        }

        $compressed = self::compressLz4Block($payload);
        if (strlen($compressed) > strlen($payload) - intdiv(strlen($payload), 32)) {
            return self::encodeMessageFrame($messageType, $payload);
        }

        return self::encodeMessageFrame($messageType, $payload, self::MESSAGE_COMPRESSION_LZ4);
    }

    /**
     * @return array{type:int, compression:int, payload:string}
     */
    public static function decodeMessageFrame(string $frame): array
    {
        if (strlen($frame) < 2) {
            throw new \UnexpectedValueException('reading header length failed');
        }

        $headerLength = unpack('n', substr($frame, 0, 2))[1];
        if (strlen($frame) < 2 + $headerLength + 4) {
            throw new \UnexpectedValueException('reading header failed');
        }

        [$messageType, $compression] = self::decodeHeaderPayload(substr($frame, 2, $headerLength));
        $lengthOffset = 2 + $headerLength;
        $messageLength = unpack('N', substr($frame, $lengthOffset, 4))[1];
        if ($messageLength > ProtocolValidation::MAX_MESSAGE_LEN) {
            throw new \LengthException('message length exceeds maximum');
        }
        if (strlen($frame) < $lengthOffset + 4 + $messageLength) {
            throw new \UnexpectedValueException('reading message failed');
        }
        $payload = substr($frame, $lengthOffset + 4, $messageLength);
        if ($compression === self::MESSAGE_COMPRESSION_LZ4) {
            $payload = self::decompressLz4Block($payload);
        } elseif ($compression !== self::MESSAGE_COMPRESSION_NONE) {
            throw new \UnexpectedValueException('unknown message compression');
        }

        return [
            'type' => $messageType,
            'compression' => $compression,
            'payload' => $payload,
        ];
    }

    public static function compressLz4Block(string $payload): string
    {
        if (strlen($payload) > ProtocolValidation::MAX_MESSAGE_LEN) {
            throw new \LengthException('message length exceeds maximum');
        }

        return pack('N', strlen($payload)) . self::encodeLz4RawBlock($payload);
    }

    public static function decompressLz4Block(string $payload): string
    {
        if (strlen($payload) < 4) {
            throw new \UnexpectedValueException(sprintf('compressed message len %d is too short', strlen($payload)));
        }

        $expectedLength = unpack('N', substr($payload, 0, 4))[1];
        if ($expectedLength > ProtocolValidation::MAX_MESSAGE_LEN) {
            throw new \LengthException('decompressed message length exceeds maximum');
        }

        $compressed = substr($payload, 4);
        if ($expectedLength === 0 && $compressed === '') {
            return '';
        }

        $out = '';
        $offset = 0;
        $length = strlen($compressed);
        while ($offset < $length) {
            $token = ord($compressed[$offset]);
            $offset++;

            $literalLength = $token >> 4;
            if ($literalLength === 15) {
                $literalLength += self::readLz4LengthExtension($compressed, $offset);
            }
            if ($offset + $literalLength > $length) {
                throw new \UnexpectedValueException('truncated LZ4 literals');
            }
            if ($literalLength > 0) {
                $out .= substr($compressed, $offset, $literalLength);
                $offset += $literalLength;
            }

            if ($offset === $length) {
                break;
            }
            if ($offset + 2 > $length) {
                throw new \UnexpectedValueException('truncated LZ4 match offset');
            }

            $matchOffset = ord($compressed[$offset]) | (ord($compressed[$offset + 1]) << 8);
            $offset += 2;
            if ($matchOffset <= 0 || $matchOffset > strlen($out)) {
                throw new \UnexpectedValueException('invalid LZ4 match offset');
            }

            $matchLength = ($token & 0x0f) + 4;
            if (($token & 0x0f) === 15) {
                $matchLength += self::readLz4LengthExtension($compressed, $offset);
            }

            for ($i = 0; $i < $matchLength; $i++) {
                $out .= $out[strlen($out) - $matchOffset];
            }
        }

        if (strlen($out) !== $expectedLength) {
            throw new \UnexpectedValueException('decompressed message length mismatch');
        }

        return $out;
    }

    private static function shouldCompressMessage(int $messageType, string $payload, int $compressionMode): bool
    {
        if (strlen($payload) < self::COMPRESSION_THRESHOLD) {
            return false;
        }

        return match ($compressionMode) {
            Device::COMPRESSION_NEVER => false,
            Device::COMPRESSION_ALWAYS => true,
            Device::COMPRESSION_METADATA => $messageType !== self::MESSAGE_TYPE_RESPONSE,
            default => false,
        };
    }

    private static function encodeLz4RawBlock(string $payload): string
    {
        $length = strlen($payload);
        if ($length === 0) {
            return '';
        }

        $out = '';
        $anchor = 0;
        $offset = 0;
        $table = [];
        $lastMatchStart = $length - 12;

        while ($offset <= $lastMatchStart) {
            $sequence = substr($payload, $offset, 4);
            $reference = $table[$sequence] ?? null;
            $table[$sequence] = $offset;

            if ($reference === null || $offset - $reference > 0xffff) {
                $offset++;
                continue;
            }

            $matchLength = 4;
            $maxMatchLength = $length - $offset - 5;
            while (
                $matchLength < $maxMatchLength
                && $payload[$reference + $matchLength] === $payload[$offset + $matchLength]
            ) {
                $matchLength++;
            }

            if ($matchLength < 4) {
                $offset++;
                continue;
            }

            $out .= self::encodeLz4Sequence(
                substr($payload, $anchor, $offset - $anchor),
                $offset - $reference,
                $matchLength,
            );

            $matchEnd = $offset + $matchLength;
            $primeEnd = min($matchEnd, $length - 3);
            for ($prime = $offset + 1; $prime < $primeEnd; $prime++) {
                $table[substr($payload, $prime, 4)] = $prime;
            }

            $offset = $matchEnd;
            $anchor = $offset;
        }

        if ($anchor < $length) {
            $out .= self::encodeLz4Literals(substr($payload, $anchor));
        }

        return $out;
    }

    private static function encodeLz4Sequence(string $literals, int $matchOffset, int $matchLength): string
    {
        if ($matchOffset <= 0 || $matchOffset > 0xffff || $matchLength < 4) {
            throw new \InvalidArgumentException('Invalid LZ4 sequence');
        }

        $literalLength = strlen($literals);
        $matchNibble = min($matchLength - 4, 15);
        $token = (min($literalLength, 15) << 4) | $matchNibble;

        return chr($token)
            . self::encodeLz4LengthExtension($literalLength)
            . $literals
            . chr($matchOffset & 0xff)
            . chr(($matchOffset >> 8) & 0xff)
            . self::encodeLz4LengthExtension($matchLength - 4);
    }

    private static function encodeLz4Literals(string $literals): string
    {
        $literalLength = strlen($literals);

        return chr(min($literalLength, 15) << 4)
            . self::encodeLz4LengthExtension($literalLength)
            . $literals;
    }

    private static function encodeLz4LengthExtension(int $length): string
    {
        if ($length < 15) {
            return '';
        }

        $remaining = $length - 15;
        $out = '';
        while ($remaining >= 255) {
            $out .= "\xff";
            $remaining -= 255;
        }

        return $out . chr($remaining);
    }

    private static function readLz4LengthExtension(string $payload, int &$offset): int
    {
        $length = strlen($payload);
        $extra = 0;

        do {
            if ($offset >= $length) {
                throw new \UnexpectedValueException('truncated LZ4 length extension');
            }
            $byte = ord($payload[$offset]);
            $offset++;
            $extra += $byte;
        } while ($byte === 255);

        return $extra;
    }

    public static function encodeRequestPayload(Request $request): string
    {
        $payload = '';
        $payload .= self::fieldVarint(1, $request->id);
        $payload .= self::fieldBytes(2, $request->folder);
        $payload .= self::fieldBytes(3, $request->name);
        $payload .= self::fieldVarint(4, $request->offset);
        $payload .= self::fieldVarint(5, $request->size);
        $payload .= self::fieldBytes(6, $request->hashHex === '' ? '' : hex2bin($request->hashHex));
        $payload .= self::fieldVarint(7, $request->fromTemporary ? 1 : 0);
        $payload .= self::fieldVarint(9, $request->blockNo);

        return $payload;
    }

    public static function decodeRequestPayload(string $payload): Request
    {
        $fields = self::decodeFields($payload);
        $hash = self::lastBytes($fields, 6);

        return new Request(
            id: self::lastInt($fields, 1),
            folder: self::lastBytes($fields, 2),
            name: self::lastBytes($fields, 3),
            offset: self::lastInt($fields, 4),
            size: self::lastInt($fields, 5),
            hashHex: $hash === '' ? '' : bin2hex($hash),
            fromTemporary: self::lastInt($fields, 7) !== 0,
            blockNo: self::lastInt($fields, 9),
        );
    }

    public static function encodeResponsePayload(Response $response): string
    {
        $payload = '';
        $payload .= self::fieldVarint(1, $response->id);
        $payload .= self::fieldBytes(2, $response->data);
        $payload .= self::fieldVarint(3, $response->code);

        return $payload;
    }

    public static function decodeResponsePayload(string $payload): Response
    {
        $fields = self::decodeFields($payload);

        return new Response(
            id: self::lastInt($fields, 1),
            data: self::lastBytes($fields, 2),
            code: self::lastInt($fields, 3),
        );
    }

    public static function encodeClusterConfigPayload(ClusterConfig $config): string
    {
        $payload = '';
        foreach ($config->folders as $folder) {
            $payload .= self::fieldBytes(1, self::encodeFolderPayload($folder));
        }
        $payload .= self::fieldVarint(2, $config->secondary ? 1 : 0);

        return $payload;
    }

    public static function decodeClusterConfigPayload(string $payload): ClusterConfig
    {
        $fields = self::decodeFields($payload);
        $folders = [];
        foreach (self::allBytes($fields, 1) as $folderPayload) {
            $folders[] = self::decodeFolderPayload($folderPayload);
        }

        return new ClusterConfig(
            folders: $folders,
            secondary: self::lastInt($fields, 2) !== 0,
        );
    }

    private static function encodeFolderPayload(Folder $folder): string
    {
        $payload = '';
        $payload .= self::fieldBytes(1, $folder->id);
        $payload .= self::fieldBytes(2, $folder->label);
        $payload .= self::fieldVarint(3, $folder->type);
        $payload .= self::fieldVarint(7, $folder->stopReason);
        foreach ($folder->devices as $device) {
            $payload .= self::fieldBytes(16, self::encodeDevicePayload($device));
        }

        return $payload;
    }

    private static function decodeFolderPayload(string $payload): Folder
    {
        $fields = self::decodeFields($payload);
        $devices = [];
        foreach (self::allBytes($fields, 16) as $devicePayload) {
            $devices[] = self::decodeDevicePayload($devicePayload);
        }

        return new Folder(
            id: self::lastBytes($fields, 1),
            label: self::lastBytes($fields, 2),
            type: self::lastInt($fields, 3),
            stopReason: self::lastInt($fields, 7),
            devices: $devices,
        );
    }

    private static function encodeDevicePayload(Device $device): string
    {
        $payload = '';
        $payload .= self::fieldBytes(1, $device->idHex === '' ? '' : hex2bin($device->idHex));
        $payload .= self::fieldBytes(2, $device->name);
        foreach ($device->addresses as $address) {
            $payload .= self::fieldBytes(3, $address);
        }
        $payload .= self::fieldVarint(4, $device->compression);
        $payload .= self::fieldBytes(5, $device->certName);
        $payload .= self::fieldVarint(6, $device->maxSequence);
        $payload .= self::fieldVarint(7, $device->introducer ? 1 : 0);
        $payload .= self::fieldVarint(8, $device->indexId);
        $payload .= self::fieldVarint(9, $device->skipIntroductionRemovals ? 1 : 0);
        $payload .= self::fieldBytes(10, $device->encryptionPasswordTokenHex === '' ? '' : hex2bin($device->encryptionPasswordTokenHex));

        return $payload;
    }

    private static function decodeDevicePayload(string $payload): Device
    {
        $fields = self::decodeFields($payload);

        return new Device(
            idHex: bin2hex(self::lastBytes($fields, 1)),
            name: self::lastBytes($fields, 2),
            addresses: self::allBytes($fields, 3),
            compression: self::lastInt($fields, 4),
            certName: self::lastBytes($fields, 5),
            maxSequence: self::lastInt($fields, 6),
            introducer: self::lastInt($fields, 7) !== 0,
            indexId: self::lastInt($fields, 8),
            skipIntroductionRemovals: self::lastInt($fields, 9) !== 0,
            encryptionPasswordTokenHex: bin2hex(self::lastBytes($fields, 10)),
        );
    }

    private static function encodeHelloPayload(Hello $hello): string
    {
        $payload = '';
        $payload .= self::fieldBytes(1, $hello->deviceName);
        $payload .= self::fieldBytes(2, $hello->clientName);
        $payload .= self::fieldBytes(3, $hello->clientVersion);
        $payload .= self::fieldVarint(4, $hello->numConnections);
        $payload .= self::fieldVarint(5, $hello->timestamp);

        return $payload;
    }

    private static function decodeHelloPayload(string $payload): Hello
    {
        $fields = self::decodeFields($payload);

        return new Hello(
            deviceName: self::lastBytes($fields, 1),
            clientName: self::lastBytes($fields, 2),
            clientVersion: self::lastBytes($fields, 3),
            numConnections: self::lastInt($fields, 4),
            timestamp: self::lastInt($fields, 5),
        );
    }

    private static function encodeHeaderPayload(int $messageType, int $compression): string
    {
        return self::fieldVarint(1, $messageType) . self::fieldVarint(2, $compression);
    }

    /**
     * @return array{int, int}
     */
    private static function decodeHeaderPayload(string $payload): array
    {
        $fields = self::decodeFields($payload);

        return [self::lastInt($fields, 1), self::lastInt($fields, 2)];
    }

    private static function fieldVarint(int $field, int $value): string
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Protobuf int fields must not be negative in this slice');
        }
        if ($value === 0) {
            return '';
        }

        return self::encodeVarint(($field << 3) | 0) . self::encodeVarint($value);
    }

    private static function fieldBytes(int $field, string|false $value): string
    {
        if ($value === false) {
            throw new \InvalidArgumentException('Expected hexadecimal byte field');
        }
        if ($value === '') {
            return '';
        }

        return self::encodeVarint(($field << 3) | 2) . self::encodeVarint(strlen($value)) . $value;
    }

    private static function encodeVarint(int $value): string
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Varint value must not be negative');
        }

        $out = '';
        while ($value > 0x7f) {
            $out .= chr(($value & 0x7f) | 0x80);
            $value = intdiv($value, 128);
        }

        return $out . chr($value);
    }

    /**
     * @return array<int, list<int|string>>
     */
    private static function decodeFields(string $payload): array
    {
        $offset = 0;
        $fields = [];
        $length = strlen($payload);

        while ($offset < $length) {
            $key = self::readVarint($payload, $offset);
            $field = $key >> 3;
            $wireType = $key & 7;
            if ($field <= 0) {
                throw new \UnexpectedValueException('invalid protobuf field number');
            }

            switch ($wireType) {
                case 0:
                    $fields[$field][] = self::readVarint($payload, $offset);
                    break;

                case 1:
                    if ($offset + 8 > $length) {
                        throw new \UnexpectedValueException('truncated fixed64 protobuf field');
                    }
                    $offset += 8;
                    break;

                case 2:
                    $size = self::readVarint($payload, $offset);
                    if ($offset + $size > $length) {
                        throw new \UnexpectedValueException('truncated length-delimited protobuf field');
                    }
                    $fields[$field][] = substr($payload, $offset, $size);
                    $offset += $size;
                    break;

                case 5:
                    if ($offset + 4 > $length) {
                        throw new \UnexpectedValueException('truncated fixed32 protobuf field');
                    }
                    $offset += 4;
                    break;

                default:
                    throw new \UnexpectedValueException('unsupported protobuf wire type');
            }
        }

        return $fields;
    }

    private static function readVarint(string $payload, int &$offset): int
    {
        $result = 0;
        $shift = 0;
        $length = strlen($payload);

        while ($offset < $length) {
            $byte = ord($payload[$offset]);
            $offset++;
            $result += ($byte & 0x7f) << $shift;
            if (($byte & 0x80) === 0) {
                return $result;
            }
            $shift += 7;
            if ($shift >= PHP_INT_SIZE * 8) {
                throw new \UnexpectedValueException('protobuf varint is too large');
            }
        }

        throw new \UnexpectedValueException('truncated protobuf varint');
    }

    /**
     * @param array<int, list<int|string>> $fields
     */
    private static function lastInt(array $fields, int $field): int
    {
        $values = $fields[$field] ?? null;
        if ($values === null) {
            return 0;
        }

        $value = $values[array_key_last($values)];
        if (!is_int($value)) {
            throw new \UnexpectedValueException('expected protobuf varint field');
        }

        return $value;
    }

    /**
     * @param array<int, list<int|string>> $fields
     */
    private static function lastBytes(array $fields, int $field): string
    {
        $values = $fields[$field] ?? null;
        if ($values === null) {
            return '';
        }

        $value = $values[array_key_last($values)];
        if (!is_string($value)) {
            throw new \UnexpectedValueException('expected protobuf bytes field');
        }

        return $value;
    }

    /**
     * @param array<int, list<int|string>> $fields
     *
     * @return list<string>
     */
    private static function allBytes(array $fields, int $field): array
    {
        $values = $fields[$field] ?? [];
        $out = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new \UnexpectedValueException('expected protobuf bytes field');
            }
            $out[] = $value;
        }

        return $out;
    }
}
