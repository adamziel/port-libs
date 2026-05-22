<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class GzipReader
{
    public const ERR_ZLIB_MISSING = 'gzip reader requires the zlib extension';
    public const ERR_INVALID_GZIP = 'gzip: invalid header';
    public const ERR_CLOSED = 'gzip reader already closed';

    private object $reader;
    private string $decoded;
    private int $offset = 0;
    private bool $closed = false;

    public function __construct(mixed $reader)
    {
        if (!function_exists('gzdecode')) {
            throw new \RuntimeException(self::ERR_ZLIB_MISSING);
        }

        $this->reader = self::reader($reader);
        $decoded = @gzdecode($this->readCompressedBytes());
        if ($decoded === false) {
            throw new \RuntimeException(self::ERR_INVALID_GZIP);
        }

        $this->decoded = $decoded;
    }

    public function read(int $length): string
    {
        if ($this->closed) {
            throw new \RuntimeException(self::ERR_CLOSED);
        }
        if ($length <= 0 || $this->offset >= strlen($this->decoded)) {
            return '';
        }

        $chunk = substr($this->decoded, $this->offset, $length);
        $this->offset += strlen($chunk);

        return $chunk;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        if (!method_exists($this->reader, 'close')) {
            return;
        }

        $result = $this->reader->close();
        if ($result instanceof \Throwable) {
            throw $result;
        }
    }

    private function readCompressedBytes(): string
    {
        $bytes = '';
        while (true) {
            $chunk = $this->reader->read(8192);
            if (!is_string($chunk)) {
                throw new \UnexpectedValueException('Reader read() must return a string');
            }
            if ($chunk === '') {
                return $bytes;
            }

            $bytes .= $chunk;
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
                    if ($length <= 0 || $this->offset >= strlen($this->bytes)) {
                        return '';
                    }

                    $chunk = substr($this->bytes, $this->offset, $length);
                    $this->offset += strlen($chunk);

                    return $chunk;
                }

                public function close(): void
                {
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

                public function close(): void
                {
                    if (is_resource($this->stream)) {
                        fclose($this->stream);
                    }
                }
            };
        }

        if (is_object($reader) && method_exists($reader, 'read')) {
            return $reader;
        }

        throw new \InvalidArgumentException('Reader must be a gzip byte string, stream resource, or object with a read method');
    }
}
