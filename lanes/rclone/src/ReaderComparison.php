<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class ReaderComparison
{
    public const BUFFER_SIZE = 64 * 1024;

    public static function checkEqualReaders(mixed $left, mixed $right, int $bufferSize = self::BUFFER_SIZE): ReaderComparisonResult
    {
        if ($bufferSize <= 0) {
            throw new \InvalidArgumentException('Reader comparison buffer size must be positive');
        }

        $leftReader = self::reader($left);
        $rightReader = self::reader($right);

        while (true) {
            $leftChunk = self::readFill($leftReader, $bufferSize);
            $rightChunk = self::readFill($rightReader, $bufferSize);

            if ($leftChunk['error'] !== null) {
                return new ReaderComparisonResult(false, $leftChunk['error']);
            }
            if ($rightChunk['error'] !== null) {
                return new ReaderComparisonResult(false, $rightChunk['error']);
            }

            if ($leftChunk['bytes'] !== $rightChunk['bytes']) {
                return new ReaderComparisonResult(false);
            }

            if ($leftChunk['eof'] && $rightChunk['eof']) {
                return new ReaderComparisonResult(true);
            }
        }
    }

    private static function reader(mixed $reader): object
    {
        if (is_string($reader)) {
            return new class($reader) {
                private int $offset = 0;

                public function __construct(private readonly string $bytes)
                {
                }

                public function read(int $length): string
                {
                    if ($length <= 0 || $this->eof()) {
                        return '';
                    }

                    $chunk = substr($this->bytes, $this->offset, $length);
                    $this->offset += strlen($chunk);

                    return $chunk;
                }

                public function eof(): bool
                {
                    return $this->offset >= strlen($this->bytes);
                }
            };
        }

        if (is_resource($reader)) {
            return new class($reader) {
                /** @param resource $stream */
                public function __construct(private $stream)
                {
                }

                public function read(int $length): string
                {
                    if ($length <= 0 || feof($this->stream)) {
                        return '';
                    }

                    $chunk = fread($this->stream, $length);
                    if ($chunk === false) {
                        throw new \RuntimeException('Failed to read stream');
                    }

                    return $chunk;
                }

                public function eof(): bool
                {
                    return feof($this->stream);
                }
            };
        }

        if (is_object($reader) && method_exists($reader, 'read') && method_exists($reader, 'eof')) {
            return $reader;
        }

        throw new \InvalidArgumentException('Reader must be a string, stream resource, or object with read/eof methods');
    }

    /**
     * @return array{bytes: string, eof: bool, error: ?\Throwable}
     */
    private static function readFill(object $reader, int $limit): array
    {
        $bytes = '';
        $eof = false;

        while (strlen($bytes) < $limit) {
            try {
                $chunk = $reader->read($limit - strlen($bytes));
            } catch (\Throwable $throwable) {
                return ['bytes' => $bytes, 'eof' => false, 'error' => $throwable];
            }

            if (!is_string($chunk)) {
                return [
                    'bytes' => $bytes,
                    'eof' => false,
                    'error' => new \UnexpectedValueException('Reader read() must return a string'),
                ];
            }

            if ($chunk === '') {
                try {
                    $eof = (bool) $reader->eof();
                } catch (\Throwable $throwable) {
                    return ['bytes' => $bytes, 'eof' => false, 'error' => $throwable];
                }

                if ($eof) {
                    break;
                }

                return [
                    'bytes' => $bytes,
                    'eof' => false,
                    'error' => new \RuntimeException('Reader returned no bytes before EOF'),
                ];
            }

            $bytes .= $chunk;

            try {
                $eof = (bool) $reader->eof();
            } catch (\Throwable $throwable) {
                return ['bytes' => $bytes, 'eof' => false, 'error' => $throwable];
            }

            if ($eof) {
                break;
            }
        }

        return ['bytes' => $bytes, 'eof' => $eof, 'error' => null];
    }
}
