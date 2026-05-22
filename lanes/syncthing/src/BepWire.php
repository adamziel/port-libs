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

    public static function encodeRequestMessage(Request $request): string
    {
        return self::encodeMessageFrame(self::MESSAGE_TYPE_REQUEST, self::encodeRequestPayload($request));
    }

    public static function decodeRequestMessage(string $frame): Request
    {
        $message = self::decodeMessageFrame($frame);
        if ($message['type'] !== self::MESSAGE_TYPE_REQUEST) {
            throw new \UnexpectedValueException('expected request message');
        }

        return self::decodeRequestPayload($message['payload']);
    }

    public static function encodeResponseMessage(Response $response): string
    {
        return self::encodeMessageFrame(self::MESSAGE_TYPE_RESPONSE, self::encodeResponsePayload($response));
    }

    public static function decodeResponseMessage(string $frame): Response
    {
        $message = self::decodeMessageFrame($frame);
        if ($message['type'] !== self::MESSAGE_TYPE_RESPONSE) {
            throw new \UnexpectedValueException('expected response message');
        }

        return self::decodeResponsePayload($message['payload']);
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

        $header = self::encodeHeaderPayload($messageType, $compression);
        if (strlen($header) > 0xffff) {
            throw new \LengthException('header length exceeds maximum');
        }

        return pack('n', strlen($header)) . $header . pack('N', strlen($payload)) . $payload;
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
        if ($compression !== self::MESSAGE_COMPRESSION_NONE) {
            throw new \UnexpectedValueException('compressed BEP messages are not supported by this native slice');
        }

        return [
            'type' => $messageType,
            'compression' => $compression,
            'payload' => substr($frame, $lengthOffset + 4, $messageLength),
        ];
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
}
