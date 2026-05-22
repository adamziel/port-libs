<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class LimitedReadCloser
{
    private object $reader;
    private int $remaining;

    public function __construct(mixed $reader, int $limit)
    {
        $this->reader = self::reader($reader);
        $this->remaining = max(0, $limit);
    }

    public static function wrap(mixed $reader, int $limit): object
    {
        $reader = self::reader($reader);

        return $limit < 0 ? $reader : new self($reader, $limit);
    }

    public function read(int $length): string
    {
        if ($length <= 0 || $this->remaining <= 0) {
            return '';
        }

        $chunk = $this->reader->read(min($length, $this->remaining));
        if (!is_string($chunk)) {
            throw new \UnexpectedValueException('Reader read() must return a string');
        }

        if (strlen($chunk) > $this->remaining) {
            $chunk = substr($chunk, 0, $this->remaining);
        }
        $this->remaining -= strlen($chunk);

        return $chunk;
    }

    public function close(): void
    {
        if (!method_exists($this->reader, 'close')) {
            return;
        }

        try {
            $result = $this->reader->close();
            if ($result instanceof \Throwable) {
                throw $result;
            }
        } catch (\Throwable $throwable) {
            if ($this->remaining === 0) {
                return;
            }

            throw $throwable;
        }
    }

    public function remaining(): int
    {
        return $this->remaining;
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

        throw new \InvalidArgumentException('Reader must be a string, stream resource, or object with a read method');
    }
}
