<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class NoCloseReader
{
    private function __construct(private readonly object $reader)
    {
    }

    public static function wrap(mixed $reader): mixed
    {
        if ($reader === null) {
            return null;
        }

        $reader = self::reader($reader);
        if (!method_exists($reader, 'close')) {
            return $reader;
        }

        return new self($reader);
    }

    public function read(int $length): string
    {
        $chunk = $this->reader->read($length);
        if (!is_string($chunk)) {
            throw new \UnexpectedValueException('Reader read() must return a string');
        }

        return $chunk;
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

        throw new \InvalidArgumentException('Reader must be null, a string, stream resource, or object with a read method');
    }
}
