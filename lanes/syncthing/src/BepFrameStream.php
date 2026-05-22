<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class BepFrameStream
{
    /**
     * @param resource $stream
     */
    public function __construct(private $stream)
    {
        if (!is_resource($this->stream)) {
            throw new \InvalidArgumentException('Expected a PHP stream resource');
        }
    }

    /**
     * @param resource $stream
     */
    public static function from($stream): self
    {
        return new self($stream);
    }

    public function writeFrame(string $frame): int
    {
        self::encodedFrameLength($frame);

        $written = 0;
        $length = strlen($frame);
        while ($written < $length) {
            $bytes = fwrite($this->stream, substr($frame, $written));
            if ($bytes === false || $bytes === 0) {
                throw new \RuntimeException('writing message failed');
            }
            $written += $bytes;
        }

        return $written;
    }

    public function writeClusterConfig(ClusterConfig $config, int $compressionMode = Device::COMPRESSION_NEVER): int
    {
        return $this->writeFrame(BepWire::encodeClusterConfigMessage($config, $compressionMode));
    }

    public function writeIndex(
        Index $index,
        int $compressionMode = Device::COMPRESSION_NEVER,
        string $directorySeparator = DIRECTORY_SEPARATOR,
    ): int {
        return $this->writeFrame(BepWire::encodeIndexMessage($index->normalizedForWire($directorySeparator), $compressionMode));
    }

    public function writeIndexUpdate(
        IndexUpdate $indexUpdate,
        int $compressionMode = Device::COMPRESSION_NEVER,
        string $directorySeparator = DIRECTORY_SEPARATOR,
    ): int {
        return $this->writeFrame(BepWire::encodeIndexUpdateMessage($indexUpdate->normalizedForWire($directorySeparator), $compressionMode));
    }

    public function writeDownloadProgress(
        DownloadProgress $progress,
        int $compressionMode = Device::COMPRESSION_NEVER,
        string $directorySeparator = DIRECTORY_SEPARATOR,
    ): int {
        return $this->writeFrame(BepWire::encodeDownloadProgressMessage($progress->normalizedForWire($directorySeparator), $compressionMode));
    }

    public function writeRequest(
        Request $request,
        int $compressionMode = Device::COMPRESSION_NEVER,
        string $directorySeparator = DIRECTORY_SEPARATOR,
    ): int {
        return $this->writeFrame(BepWire::encodeRequestMessage($request->normalizedForWire($directorySeparator), $compressionMode));
    }

    public function writeResponse(Response $response, int $compressionMode = Device::COMPRESSION_NEVER): int
    {
        return $this->writeFrame(BepWire::encodeResponseMessage($response, $compressionMode));
    }

    public function writePing(int $compressionMode = Device::COMPRESSION_NEVER): int
    {
        return $this->writeFrame(BepWire::encodePingMessage($compressionMode));
    }

    public function writeClose(Close $close, int $compressionMode = Device::COMPRESSION_NEVER): int
    {
        return $this->writeFrame(BepWire::encodeCloseMessage($close, $compressionMode));
    }

    public function readFrame(): string
    {
        $headerLengthBytes = $this->readExact(2, 'reading length failed');
        $headerLength = unpack('n', $headerLengthBytes)[1];
        $header = $this->readExact($headerLength, 'reading header failed');
        $messageLengthBytes = $this->readExact(4, 'reading message length failed');
        $messageLength = unpack('N', $messageLengthBytes)[1];
        if ($messageLength > ProtocolValidation::MAX_MESSAGE_LEN) {
            throw new \LengthException('message length exceeds maximum');
        }

        $payload = $this->readExact($messageLength, 'reading message failed');

        return $headerLengthBytes . $header . $messageLengthBytes . $payload;
    }

    /**
     * @return array{type:int, compression:int, payload:string}
     */
    public function readMessageFrame(): array
    {
        return BepWire::decodeMessageFrame($this->readFrame());
    }

    /**
     * @param null|BepSessionHandlers|callable(Request): (RequestServingResult|Response|string|null) $handlers
     */
    public function receiveNext(BepSession $session, null|BepSessionHandlers|callable $handlers = null): BepSessionEvent
    {
        return $session->receiveFrame($this->readFrame(), $handlers);
    }

    /**
     * @return array{type:int, compression:int, payload:string}
     */
    public static function decodeOne(string $bytes, int &$offset = 0): array
    {
        $frame = self::sliceOneFrame($bytes, $offset);

        return BepWire::decodeMessageFrame($frame);
    }

    public static function sliceOneFrame(string $bytes, int &$offset = 0): string
    {
        if ($offset < 0 || $offset > strlen($bytes)) {
            throw new \OutOfBoundsException('Invalid BEP frame offset');
        }

        $frameLength = self::encodedFrameLength(substr($bytes, $offset));
        $frame = substr($bytes, $offset, $frameLength);
        $offset += $frameLength;

        return $frame;
    }

    private function readExact(int $length, string $error): string
    {
        if ($length === 0) {
            return '';
        }

        $out = '';
        while (strlen($out) < $length) {
            $chunk = fread($this->stream, $length - strlen($out));
            if ($chunk === false) {
                throw new \RuntimeException($error);
            }
            if ($chunk === '') {
                throw new \UnexpectedValueException($error);
            }
            $out .= $chunk;
        }

        return $out;
    }

    private static function encodedFrameLength(string $frame): int
    {
        if (strlen($frame) < 2) {
            throw new \UnexpectedValueException('reading length failed');
        }

        $headerLength = unpack('n', substr($frame, 0, 2))[1];
        if (strlen($frame) < 2 + $headerLength) {
            throw new \UnexpectedValueException('reading header failed');
        }

        $messageLengthOffset = 2 + $headerLength;
        if (strlen($frame) < $messageLengthOffset + 4) {
            throw new \UnexpectedValueException('reading message length failed');
        }

        $messageLength = unpack('N', substr($frame, $messageLengthOffset, 4))[1];
        if ($messageLength > ProtocolValidation::MAX_MESSAGE_LEN) {
            throw new \LengthException('message length exceeds maximum');
        }
        if (strlen($frame) < $messageLengthOffset + 4 + $messageLength) {
            throw new \UnexpectedValueException('reading message failed');
        }

        return $messageLengthOffset + 4 + $messageLength;
    }
}
