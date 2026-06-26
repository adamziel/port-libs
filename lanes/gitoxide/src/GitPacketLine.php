<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitPacketLine
{
    public const KIND_DATA = 'data';
    public const KIND_FLUSH = 'flush';
    public const KIND_DELIMITER = 'delimiter';
    public const KIND_RESPONSE_END = 'response-end';

    public const CHANNEL_DATA = 'data';
    public const CHANNEL_PROGRESS = 'progress';
    public const CHANNEL_ERROR = 'error';

    public const MAX_DATA_LENGTH = 65516;
    public const MAX_LINE_LENGTH = self::MAX_DATA_LENGTH + 4;

    private const ERR_PREFIX = 'ERR ';

    private function __construct()
    {
    }

    /**
     * @return array{status:'complete',line:array{kind:string,payload?:string},bytesConsumed:int}|array{status:'incomplete',bytesNeeded:int}
     */
    public static function streaming(string $bytes): array
    {
        $available = strlen($bytes);
        if ($available < 4) {
            return [
                'status' => 'incomplete',
                'bytesNeeded' => 4 - $available,
            ];
        }

        $prefix = substr($bytes, 0, 4);
        $special = self::specialLine($prefix);
        if ($special !== null) {
            return [
                'status' => 'complete',
                'line' => $special,
                'bytesConsumed' => 4,
            ];
        }

        $wanted = self::decodePrefix($prefix);
        if ($wanted > self::MAX_LINE_LENGTH) {
            throw new \InvalidArgumentException(
                'The data received claims to be larger than the maximum allowed size: '
                . "got {$wanted}, exceeds " . self::MAX_DATA_LENGTH
            );
        }
        if ($available < $wanted) {
            return [
                'status' => 'incomplete',
                'bytesNeeded' => $wanted - $available,
            ];
        }

        return [
            'status' => 'complete',
            'line' => self::dataLine(substr($bytes, 4, $wanted - 4)),
            'bytesConsumed' => $wanted,
        ];
    }

    /**
     * @return array{kind:string,payload?:string}
     */
    public static function decode(string $bytes): array
    {
        $result = self::streaming($bytes);
        if ($result['status'] === 'incomplete') {
            throw new \InvalidArgumentException(
                "Needing {$result['bytesNeeded']} additional bytes to decode the line successfully"
            );
        }

        return $result['line'];
    }

    /**
     * @return list<array{kind:string,payload?:string}>
     */
    public static function decodeAll(string $bytes): array
    {
        $lines = [];
        $offset = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $result = self::streaming(substr($bytes, $offset));
            if ($result['status'] === 'incomplete') {
                throw new \InvalidArgumentException(
                    "Needing {$result['bytesNeeded']} additional bytes to decode the line successfully"
                );
            }

            $lines[] = $result['line'];
            $offset += $result['bytesConsumed'];
        }

        return $lines;
    }

    /**
     * @return array{kind:'data',payload:string}
     */
    public static function dataLine(string $payload): array
    {
        return [
            'kind' => self::KIND_DATA,
            'payload' => $payload,
        ];
    }

    /**
     * @return array{kind:'flush'}
     */
    public static function flushLine(): array
    {
        return ['kind' => self::KIND_FLUSH];
    }

    /**
     * @return array{kind:'delimiter'}
     */
    public static function delimiterLine(): array
    {
        return ['kind' => self::KIND_DELIMITER];
    }

    /**
     * @return array{kind:'response-end'}
     */
    public static function responseEndLine(): array
    {
        return ['kind' => self::KIND_RESPONSE_END];
    }

    /**
     * @param array{kind:string,payload?:string} $line
     */
    public static function encodeLine(array $line): string
    {
        return match ($line['kind'] ?? null) {
            self::KIND_DATA => self::encodeData((string) ($line['payload'] ?? '')),
            self::KIND_FLUSH => self::encodeFlush(),
            self::KIND_DELIMITER => self::encodeDelimiter(),
            self::KIND_RESPONSE_END => self::encodeResponseEnd(),
            default => throw new \InvalidArgumentException('Unknown packet-line kind'),
        };
    }

    public static function encodeData(string $data): string
    {
        return self::encodePayload('', $data, '');
    }

    public static function encodeText(string $text): string
    {
        return self::encodePayload('', $text, "\n");
    }

    public static function encodeError(string $message): string
    {
        return self::encodePayload(self::ERR_PREFIX, $message, '');
    }

    public static function encodeFlush(): string
    {
        return '0000';
    }

    public static function encodeDelimiter(): string
    {
        return '0001';
    }

    public static function encodeResponseEnd(): string
    {
        return '0002';
    }

    public static function encodeBand(string $channel, string $data): string
    {
        return self::encodeBandLine(self::band($channel, $data));
    }

    /**
     * @param array{channel:string,payload:string} $band
     */
    public static function encodeBandLine(array $band): string
    {
        return self::encodePayload(chr(self::bandId($band['channel'] ?? '')), (string) ($band['payload'] ?? ''), '');
    }

    /**
     * @return array{channel:string,payload:string}
     */
    public static function band(string $channel, string $payload): array
    {
        self::bandId($channel);

        return [
            'channel' => $channel,
            'payload' => $payload,
        ];
    }

    /**
     * @param array{kind:string,payload?:string} $line
     * @return array{channel:string,payload:string}
     */
    public static function decodeBand(array $line): array
    {
        if (($line['kind'] ?? null) !== self::KIND_DATA || !array_key_exists('payload', $line)) {
            throw new \InvalidArgumentException('attempt to decode a non-data line into a side-channel band');
        }

        $payload = $line['payload'];
        if ($payload === '') {
            throw new \InvalidArgumentException('attempt to decode a non-side channel line or input was malformed: 0');
        }

        $band = ord($payload[0]);

        return match ($band) {
            1 => self::band(self::CHANNEL_DATA, substr($payload, 1)),
            2 => self::band(self::CHANNEL_PROGRESS, substr($payload, 1)),
            3 => self::band(self::CHANNEL_ERROR, substr($payload, 1)),
            default => throw new \InvalidArgumentException(
                "attempt to decode a non-side channel line or input was malformed: {$band}"
            ),
        };
    }

    /**
     * @param array{kind:string,payload?:string} $line
     * @return array{channel:string,payload:string}|null
     */
    public static function asBand(array $line, string $channel): ?array
    {
        if (($line['kind'] ?? null) !== self::KIND_DATA || !array_key_exists('payload', $line)) {
            return null;
        }

        return self::band($channel, $line['payload']);
    }

    /**
     * @param array{kind:string,payload?:string} $line
     */
    public static function asText(array $line): ?string
    {
        if (($line['kind'] ?? null) !== self::KIND_DATA || !array_key_exists('payload', $line)) {
            return null;
        }

        $payload = $line['payload'];
        if (str_ends_with($payload, "\n")) {
            return substr($payload, 0, -1);
        }

        return $payload;
    }

    /**
     * @param array{kind:string,payload?:string} $line
     */
    public static function asError(array $line): ?string
    {
        if (($line['kind'] ?? null) !== self::KIND_DATA || !array_key_exists('payload', $line)) {
            return null;
        }

        return $line['payload'];
    }

    /**
     * @param array{kind:string,payload?:string} $line
     */
    public static function checkError(array $line): ?string
    {
        if (($line['kind'] ?? null) !== self::KIND_DATA || !array_key_exists('payload', $line)) {
            return null;
        }

        $payload = $line['payload'];
        if (!str_starts_with($payload, self::ERR_PREFIX)) {
            return null;
        }

        return substr($payload, strlen(self::ERR_PREFIX));
    }

    /**
     * @return array{kind:string}|null
     */
    private static function specialLine(string $prefix): ?array
    {
        return match ($prefix) {
            '0000' => self::flushLine(),
            '0001' => self::delimiterLine(),
            '0002' => self::responseEndLine(),
            default => null,
        };
    }

    private static function decodePrefix(string $prefix): int
    {
        if (preg_match('/\A[0-9a-fA-F]{4}\z/', $prefix) !== 1) {
            throw new \InvalidArgumentException(
                'Failed to decode the first four hex bytes indicating the line length: Invalid character'
            );
        }

        $wanted = hexdec($prefix);
        if ($wanted === 3) {
            throw new \InvalidArgumentException('Received an invalid line of length 3');
        }
        if ($wanted === 4) {
            throw new \InvalidArgumentException('Received an invalid empty line');
        }
        if ($wanted < 4) {
            throw new \InvalidArgumentException('Received an invalid packet-line length');
        }

        return $wanted;
    }

    private static function encodePayload(string $prefix, string $data, string $suffix): string
    {
        $payloadLength = strlen($prefix) + strlen($data) + strlen($suffix);
        if ($payloadLength > self::MAX_DATA_LENGTH) {
            throw new \InvalidArgumentException(
                'Cannot encode more than ' . self::MAX_DATA_LENGTH . " bytes, got {$payloadLength}"
            );
        }
        if ($data === '') {
            throw new \InvalidArgumentException('Empty lines are invalid');
        }

        return sprintf('%04x', $payloadLength + 4) . $prefix . $data . $suffix;
    }

    private static function bandId(string $channel): int
    {
        return match ($channel) {
            self::CHANNEL_DATA => 1,
            self::CHANNEL_PROGRESS => 2,
            self::CHANNEL_ERROR => 3,
            default => throw new \InvalidArgumentException("Unknown side-band channel {$channel}"),
        };
    }
}
