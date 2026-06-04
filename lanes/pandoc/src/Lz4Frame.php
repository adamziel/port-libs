<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class Lz4Frame
{
    private const FRAME_MAGIC = 0x184d2204;
    private const SKIPPABLE_MAGIC_MIN = 0x184d2a50;
    private const SKIPPABLE_MAGIC_MAX = 0x184d2a5f;
    private const VERSION_MASK = 0xc0;
    private const VERSION_SUPPORTED = 0x40;
    private const FLAG_BLOCK_INDEPENDENCE = 0x20;
    private const FLAG_BLOCK_CHECKSUM = 0x10;
    private const FLAG_CONTENT_SIZE = 0x08;
    private const FLAG_CONTENT_CHECKSUM = 0x04;
    private const FLAG_RESERVED = 0x02;
    private const FLAG_DICTIONARY_ID = 0x01;
    private const BLOCK_UNCOMPRESSED_FLAG = 0x80000000;
    private const BLOCK_SIZE_MASK = 0x7fffffff;

    private const BLOCK_MAX_SIZES = [
        4 => 65536,
        5 => 262144,
        6 => 1048576,
        7 => 4194304,
    ];

    /**
     * @param array{blockMaxSize?:int, blockChecksum?:bool, contentChecksum?:bool, contentSize?:bool} $options
     */
    public static function build(string $data, array $options = []): string
    {
        $blockMaxSize = $options['blockMaxSize'] ?? 65536;
        $blockMaxCode = self::blockMaxCode($blockMaxSize);
        $blockChecksum = (bool) ($options['blockChecksum'] ?? false);
        $contentChecksum = (bool) ($options['contentChecksum'] ?? true);
        $includeContentSize = (bool) ($options['contentSize'] ?? true);

        $flags = self::VERSION_SUPPORTED | self::FLAG_BLOCK_INDEPENDENCE;
        if ($blockChecksum) {
            $flags |= self::FLAG_BLOCK_CHECKSUM;
        }
        if ($includeContentSize) {
            $flags |= self::FLAG_CONTENT_SIZE;
        }
        if ($contentChecksum) {
            $flags |= self::FLAG_CONTENT_CHECKSUM;
        }

        $descriptor = chr($flags) . chr($blockMaxCode << 4);
        if ($includeContentSize) {
            $descriptor .= self::packUInt64(strlen($data));
        }

        $bytes = self::packUInt32(self::FRAME_MAGIC)
            . $descriptor
            . chr((self::xxh32($descriptor) >> 8) & 0xff);

        for ($offset = 0, $length = strlen($data); $offset < $length; $offset += $blockMaxSize) {
            $block = substr($data, $offset, $blockMaxSize);
            $encoded = self::encodeRawBlock($block);
            if ($encoded !== '' && strlen($encoded) < strlen($block)) {
                $blockPayload = $encoded;
                $blockSizeField = strlen($encoded);
            } else {
                $blockPayload = $block;
                $blockSizeField = self::BLOCK_UNCOMPRESSED_FLAG | strlen($block);
            }

            $bytes .= self::packUInt32($blockSizeField) . $blockPayload;
            if ($blockChecksum) {
                $bytes .= self::packUInt32(self::xxh32($blockPayload));
            }
        }

        $bytes .= self::packUInt32(0);
        if ($contentChecksum) {
            $bytes .= self::packUInt32(self::xxh32($data));
        }

        return $bytes;
    }

    public static function skippableFrame(string $data, int $id = 0): string
    {
        if ($id < 0 || $id > 15) {
            throw new \RuntimeException('LZ4 skippable frame id must be between 0 and 15');
        }

        self::assertUInt32Value(strlen($data), 'LZ4 skippable frame length');

        return self::packUInt32(self::SKIPPABLE_MAGIC_MIN + $id)
            . self::packUInt32(strlen($data))
            . $data;
    }

    public static function decode(string $bytes, ?int $maxUncompressedBytes = null): string
    {
        $data = '';
        foreach (self::frames($bytes, $maxUncompressedBytes) as $frame) {
            if ($frame['type'] === 'frame') {
                $data .= $frame['data'];
            }
        }

        return $data;
    }

    /**
     * @return list<array{
     *     type:string,
     *     data:string,
     *     frameSize:int,
     *     id?:int,
     *     contentSize?:?int,
     *     blockMaxSize?:int,
     *     blockChecksum?:bool,
     *     contentChecksum?:bool,
     *     blockCount?:int,
     *     blockTypes?:list<string>,
     *     compressedSize?:int
     * }>
     */
    public static function frames(string $bytes, ?int $maxUncompressedBytes = null): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('LZ4 frame stream is empty');
        }

        if ($maxUncompressedBytes !== null && $maxUncompressedBytes < 0) {
            throw new \RuntimeException('LZ4 max uncompressed byte limit must not be negative');
        }

        $frames = [];
        $cursor = 0;
        $length = strlen($bytes);
        $totalUncompressedBytes = 0;

        while ($cursor < $length) {
            $frameStart = $cursor;
            self::assertRange($bytes, $cursor, 4, 'frame magic');
            $magic = self::readUInt32($bytes, $cursor);
            $cursor += 4;

            if ($magic >= self::SKIPPABLE_MAGIC_MIN && $magic <= self::SKIPPABLE_MAGIC_MAX) {
                self::assertRange($bytes, $cursor, 4, 'skippable frame size');
                $skippableSize = self::readUInt32($bytes, $cursor);
                $cursor += 4;
                self::assertRange($bytes, $cursor, $skippableSize, 'skippable frame payload');
                $frames[] = [
                    'type' => 'skippable',
                    'id' => $magic - self::SKIPPABLE_MAGIC_MIN,
                    'data' => substr($bytes, $cursor, $skippableSize),
                    'frameSize' => 8 + $skippableSize,
                ];
                $cursor += $skippableSize;
                continue;
            }

            if ($magic !== self::FRAME_MAGIC) {
                throw new \RuntimeException('Invalid LZ4 frame magic');
            }

            self::assertRange($bytes, $cursor, 3, 'frame descriptor');
            $descriptorStart = $cursor;
            $flags = ord($bytes[$cursor]);
            $blockDescriptor = ord($bytes[$cursor + 1]);
            $cursor += 2;

            if (($flags & self::VERSION_MASK) !== self::VERSION_SUPPORTED) {
                throw new \RuntimeException('Unsupported LZ4 frame descriptor version');
            }

            if (($flags & self::FLAG_RESERVED) !== 0) {
                throw new \RuntimeException('LZ4 frame descriptor uses reserved flag bits');
            }

            if (($flags & self::FLAG_BLOCK_INDEPENDENCE) === 0) {
                throw new \RuntimeException('Dependent LZ4 frame blocks are not supported by the pandoc archive reader');
            }

            if (($blockDescriptor & 0x8f) !== 0) {
                throw new \RuntimeException('LZ4 block descriptor uses reserved bits');
            }

            $blockMaxCode = ($blockDescriptor >> 4) & 0x07;
            if (!isset(self::BLOCK_MAX_SIZES[$blockMaxCode])) {
                throw new \RuntimeException('Unsupported LZ4 maximum block size code');
            }
            $blockMaxSize = self::BLOCK_MAX_SIZES[$blockMaxCode];

            $contentSize = null;
            if (($flags & self::FLAG_CONTENT_SIZE) !== 0) {
                self::assertRange($bytes, $cursor, 8, 'content size');
                $contentSize = self::readUInt64($bytes, $cursor);
                $cursor += 8;
            }

            if (($flags & self::FLAG_DICTIONARY_ID) !== 0) {
                self::assertRange($bytes, $cursor, 4, 'dictionary id');
                $cursor += 4;
            }

            $descriptor = substr($bytes, $descriptorStart, $cursor - $descriptorStart);
            $headerChecksum = ord($bytes[$cursor]);
            $expectedHeaderChecksum = (self::xxh32($descriptor) >> 8) & 0xff;
            if ($headerChecksum !== $expectedHeaderChecksum) {
                throw new \RuntimeException('LZ4 frame header checksum does not match descriptor bytes');
            }
            $cursor++;

            if (($flags & self::FLAG_DICTIONARY_ID) !== 0) {
                throw new \RuntimeException('Dictionary-backed LZ4 frames are not supported by the pandoc archive reader');
            }

            $data = '';
            $blockTypes = [];
            $blockCount = 0;
            $compressedSize = 0;
            $blockChecksum = ($flags & self::FLAG_BLOCK_CHECKSUM) !== 0;

            while (true) {
                self::assertRange($bytes, $cursor, 4, 'block size');
                $blockSizeField = self::readUInt32($bytes, $cursor);
                $cursor += 4;
                if ($blockSizeField === 0) {
                    break;
                }

                $uncompressedBlock = ($blockSizeField & self::BLOCK_UNCOMPRESSED_FLAG) !== 0;
                $blockSize = $blockSizeField & self::BLOCK_SIZE_MASK;
                if ($blockSize <= 0 || $blockSize > $blockMaxSize) {
                    throw new \RuntimeException('LZ4 block size exceeds the configured frame maximum');
                }

                self::assertRange($bytes, $cursor, $blockSize, 'block payload');
                $blockPayload = substr($bytes, $cursor, $blockSize);
                $cursor += $blockSize;
                $compressedSize += $blockSize;

                if ($blockChecksum) {
                    self::assertRange($bytes, $cursor, 4, 'block checksum');
                    $storedBlockChecksum = self::readUInt32($bytes, $cursor);
                    $expectedBlockChecksum = self::xxh32($blockPayload);
                    if ($storedBlockChecksum !== $expectedBlockChecksum) {
                        throw new \RuntimeException('LZ4 block checksum does not match block payload');
                    }
                    $cursor += 4;
                }

                $decodedBlock = $uncompressedBlock
                    ? $blockPayload
                    : self::decodeRawBlock($blockPayload, $blockMaxSize);
                if (strlen($decodedBlock) > $blockMaxSize) {
                    throw new \RuntimeException('LZ4 decoded block exceeds the configured frame maximum');
                }

                $data .= $decodedBlock;
                $blockTypes[] = $uncompressedBlock ? 'uncompressed' : 'compressed';
                $blockCount++;
                $totalUncompressedBytes += strlen($decodedBlock);
                if ($maxUncompressedBytes !== null && $totalUncompressedBytes > $maxUncompressedBytes) {
                    throw new \RuntimeException('LZ4 frame stream exceeds the configured uncompressed byte limit');
                }
            }

            if ($contentSize !== null && strlen($data) !== $contentSize) {
                throw new \RuntimeException('LZ4 content size does not match decoded payload length');
            }

            $contentChecksum = ($flags & self::FLAG_CONTENT_CHECKSUM) !== 0;
            if ($contentChecksum) {
                self::assertRange($bytes, $cursor, 4, 'content checksum');
                $storedContentChecksum = self::readUInt32($bytes, $cursor);
                $expectedContentChecksum = self::xxh32($data);
                if ($storedContentChecksum !== $expectedContentChecksum) {
                    throw new \RuntimeException('LZ4 content checksum does not match decoded payload');
                }
                $cursor += 4;
            }

            $frames[] = [
                'type' => 'frame',
                'data' => $data,
                'frameSize' => $cursor - $frameStart,
                'contentSize' => $contentSize,
                'blockMaxSize' => $blockMaxSize,
                'blockChecksum' => $blockChecksum,
                'contentChecksum' => $contentChecksum,
                'blockCount' => $blockCount,
                'blockTypes' => $blockTypes,
                'compressedSize' => $compressedSize,
            ];
        }

        return $frames;
    }

    private static function encodeRawBlock(string $payload): string
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

            $out .= self::encodeRawSequence(
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
            $out .= self::encodeRawLiterals(substr($payload, $anchor));
        }

        return $out;
    }

    private static function decodeRawBlock(string $payload, int $maxOutputBytes): string
    {
        $out = '';
        $offset = 0;
        $length = strlen($payload);

        while ($offset < $length) {
            $token = ord($payload[$offset]);
            $offset++;

            $literalLength = $token >> 4;
            if ($literalLength === 15) {
                $literalLength += self::readRawLengthExtension($payload, $offset);
            }

            if ($offset + $literalLength > $length) {
                throw new \RuntimeException('LZ4 raw block has truncated literals');
            }

            if (strlen($out) + $literalLength > $maxOutputBytes) {
                throw new \RuntimeException('LZ4 raw block exceeds the configured output limit');
            }

            if ($literalLength > 0) {
                $out .= substr($payload, $offset, $literalLength);
                $offset += $literalLength;
            }

            if ($offset === $length) {
                break;
            }

            if ($offset + 2 > $length) {
                throw new \RuntimeException('LZ4 raw block has a truncated match offset');
            }

            $matchOffset = ord($payload[$offset]) | (ord($payload[$offset + 1]) << 8);
            $offset += 2;
            if ($matchOffset <= 0 || $matchOffset > strlen($out)) {
                throw new \RuntimeException('LZ4 raw block has an invalid match offset');
            }

            $matchLength = ($token & 0x0f) + 4;
            if (($token & 0x0f) === 15) {
                $matchLength += self::readRawLengthExtension($payload, $offset);
            }

            if (strlen($out) + $matchLength > $maxOutputBytes) {
                throw new \RuntimeException('LZ4 raw block exceeds the configured output limit');
            }

            for ($index = 0; $index < $matchLength; $index++) {
                $out .= $out[strlen($out) - $matchOffset];
            }
        }

        return $out;
    }

    private static function encodeRawSequence(string $literals, int $matchOffset, int $matchLength): string
    {
        if ($matchOffset <= 0 || $matchOffset > 0xffff || $matchLength < 4) {
            throw new \RuntimeException('Invalid LZ4 raw sequence');
        }

        $literalLength = strlen($literals);
        $matchNibble = min($matchLength - 4, 15);
        $token = (min($literalLength, 15) << 4) | $matchNibble;

        return chr($token)
            . self::encodeRawLengthExtension($literalLength)
            . $literals
            . chr($matchOffset & 0xff)
            . chr(($matchOffset >> 8) & 0xff)
            . self::encodeRawLengthExtension($matchLength - 4);
    }

    private static function encodeRawLiterals(string $literals): string
    {
        $literalLength = strlen($literals);

        return chr(min($literalLength, 15) << 4)
            . self::encodeRawLengthExtension($literalLength)
            . $literals;
    }

    private static function encodeRawLengthExtension(int $length): string
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

    private static function readRawLengthExtension(string $payload, int &$offset): int
    {
        $length = strlen($payload);
        $extra = 0;

        do {
            if ($offset >= $length) {
                throw new \RuntimeException('LZ4 raw block has a truncated length extension');
            }
            $byte = ord($payload[$offset]);
            $offset++;
            $extra += $byte;
        } while ($byte === 255);

        return $extra;
    }

    private static function blockMaxCode(mixed $blockMaxSize): int
    {
        if (!is_int($blockMaxSize)) {
            throw new \RuntimeException('LZ4 blockMaxSize must be an integer');
        }

        foreach (self::BLOCK_MAX_SIZES as $code => $size) {
            if ($blockMaxSize === $size) {
                return $code;
            }
        }

        throw new \RuntimeException('LZ4 blockMaxSize must be one of 65536, 262144, 1048576, or 4194304');
    }

    private static function xxh32(string $bytes): int
    {
        if (!in_array('xxh32', hash_algos(), true)) {
            throw new \RuntimeException('LZ4 frame checksums require PHP hash algorithm xxh32');
        }

        return intval(hash('xxh32', $bytes), 16);
    }

    private static function packUInt32(int $value): string
    {
        self::assertUInt32Value($value, 'LZ4 uint32 field');

        return pack('V', $value);
    }

    private static function packUInt64(int $value): string
    {
        if ($value < 0) {
            throw new \RuntimeException('LZ4 uint64 field must not be negative');
        }

        $low = $value & 0xffffffff;
        $high = intdiv($value, 0x100000000);

        return pack('V2', $low, $high);
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        self::assertRange($bytes, $offset, 4, 'uint32');
        $values = unpack('Vvalue', substr($bytes, $offset, 4));
        if (!is_array($values)) {
            throw new \RuntimeException('Unable to read LZ4 uint32 value');
        }

        return (int) $values['value'];
    }

    private static function readUInt64(string $bytes, int $offset): int
    {
        self::assertRange($bytes, $offset, 8, 'uint64');
        $values = unpack('Vlow/Vhigh', substr($bytes, $offset, 8));
        if (!is_array($values)) {
            throw new \RuntimeException('Unable to read LZ4 uint64 value');
        }

        $low = (int) $values['low'];
        $high = (int) $values['high'];
        if ($high > intdiv(PHP_INT_MAX - $low, 0x100000000)) {
            throw new \RuntimeException('LZ4 content size is too large for this PHP runtime');
        }

        return $high * 0x100000000 + $low;
    }

    private static function assertUInt32Value(mixed $value, string $label): void
    {
        if (!is_int($value) || $value < 0 || $value > 0xffffffff) {
            throw new \RuntimeException("{$label} must fit in an unsigned 32-bit field");
        }
    }

    private static function assertRange(string $bytes, int $offset, int $length, string $label): void
    {
        if ($offset < 0 || $length < 0 || $offset > strlen($bytes) || $offset + $length > strlen($bytes)) {
            throw new \RuntimeException("LZ4 {$label} extends beyond available bytes");
        }
    }
}
