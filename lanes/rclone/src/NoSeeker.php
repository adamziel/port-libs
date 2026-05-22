<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class NoSeeker
{
    public const ERR_CANT_SEEK = "can't Seek";

    private object $reader;

    public function __construct(mixed $reader)
    {
        $this->reader = self::reader($reader);
    }

    public function read(int $length): string
    {
        return $this->reader->read($length);
    }

    public function seek(int $offset, int $whence = SEEK_SET): int
    {
        throw new \RuntimeException(self::ERR_CANT_SEEK);
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
            };
        }

        if (is_object($reader) && method_exists($reader, 'read')) {
            return $reader;
        }

        throw new \InvalidArgumentException('Reader must be a string, stream resource, or object with a read method');
    }
}
