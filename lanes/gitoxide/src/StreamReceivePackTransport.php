<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class StreamReceivePackTransport implements ReceivePackTransport
{
    private const MAX_PACKET_LINE_LENGTH = 65520;

    private bool $advertisementRead = false;
    private bool $requestWritten = false;
    private bool $responseRead = false;

    /**
     * @param resource $readStream
     * @param resource $writeStream
     */
    public function __construct(
        private readonly mixed $readStream,
        private readonly mixed $writeStream,
    ) {
        if (!is_resource($readStream) || !is_resource($writeStream)) {
            throw new \InvalidArgumentException('receive-pack stream transport expects readable and writable stream resources');
        }
    }

    public function readAdvertisement(): string
    {
        if ($this->advertisementRead) {
            throw new \LogicException('receive-pack transport advertisement was already read');
        }

        $this->advertisementRead = true;

        return $this->readPacketStreamUntilTerminator('advertisement');
    }

    public function writeRequest(string $requestBytes): void
    {
        if (!$this->advertisementRead) {
            throw new \LogicException('receive-pack transport request cannot be written before advertisement');
        }
        if ($this->requestWritten) {
            throw new \LogicException('receive-pack transport request was already written');
        }

        self::writeAll($this->writeStream, $requestBytes);
        if (!fflush($this->writeStream)) {
            throw new \RuntimeException('receive-pack transport failed to flush request bytes');
        }

        $this->requestWritten = true;
    }

    public function readResponse(): string
    {
        if (!$this->requestWritten) {
            throw new \LogicException('receive-pack transport response cannot be read before request');
        }
        if ($this->responseRead) {
            throw new \LogicException('receive-pack transport response was already read');
        }

        $this->responseRead = true;

        return $this->readPacketStreamUntilTerminator('response');
    }

    private function readPacketStreamUntilTerminator(string $label): string
    {
        $bytes = '';
        while (true) {
            $header = self::readExactly($this->readStream, 4, "{$label} packet length");
            if (preg_match('/^[0-9a-fA-F]{4}$/', $header) !== 1) {
                throw new \InvalidArgumentException("receive-pack transport: invalid {$label} packet length {$header}");
            }

            $bytes .= $header;
            $length = hexdec($header);
            if ($length === 0 || $length === 2) {
                break;
            }
            if ($length === 1) {
                continue;
            }
            if ($length < 4) {
                throw new \InvalidArgumentException("receive-pack transport: invalid {$label} packet length {$header}");
            }
            if ($length > self::MAX_PACKET_LINE_LENGTH) {
                throw new \InvalidArgumentException("receive-pack transport: {$label} packet line exceeds maximum length {$header}");
            }

            $bytes .= self::readExactly($this->readStream, $length - 4, "{$label} packet payload");
        }

        return $bytes;
    }

    /**
     * @param resource $stream
     */
    private static function readExactly(mixed $stream, int $length, string $label): string
    {
        $bytes = '';
        while (strlen($bytes) < $length) {
            $chunk = fread($stream, $length - strlen($bytes));
            if ($chunk === false) {
                $metadata = stream_get_meta_data($stream);
                if (!empty($metadata['timed_out'])) {
                    throw new \RuntimeException("receive-pack transport timed out while reading {$label}");
                }

                throw new \RuntimeException("receive-pack transport failed while reading {$label}");
            }
            if ($chunk === '') {
                $metadata = stream_get_meta_data($stream);
                if (!empty($metadata['timed_out'])) {
                    throw new \RuntimeException("receive-pack transport timed out while reading {$label}");
                }

                throw new \RuntimeException("receive-pack transport ended while reading {$label}");
            }
            $bytes .= $chunk;
        }

        return $bytes;
    }

    /**
     * @param resource $stream
     */
    private static function writeAll(mixed $stream, string $bytes): void
    {
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $written = fwrite($stream, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException('receive-pack transport failed while writing request bytes');
            }
            $offset += $written;
        }
    }
}
