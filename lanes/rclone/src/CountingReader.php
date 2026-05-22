<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class CountingReader
{
    private object $reader;
    private int $bytesRead = 0;

    public function __construct(mixed $reader)
    {
        $this->reader = self::reader($reader);
    }

    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $chunk = $this->reader->read($length);
        if (!is_string($chunk)) {
            throw new \UnexpectedValueException('Reader read() must return a string');
        }

        $this->bytesRead += strlen($chunk);

        return $chunk;
    }

    public function bytesRead(): int
    {
        return $this->bytesRead;
    }

    public function eof(): bool
    {
        if (!method_exists($this->reader, 'eof')) {
            return false;
        }

        return (bool) $this->reader->eof();
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

        if (is_object($reader) && method_exists($reader, 'read')) {
            return $reader;
        }

        throw new \InvalidArgumentException('Reader must be a string, stream resource, or object with a read method');
    }
}
