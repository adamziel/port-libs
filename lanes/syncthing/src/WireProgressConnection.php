<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class WireProgressConnection implements ProgressConnection
{
    private readonly \Closure $writer;

    /**
     * @param callable(string, string, DownloadProgress): void $writer
     */
    public function __construct(
        private readonly string $deviceId,
        callable $writer,
        private readonly int $compressionMode = Device::COMPRESSION_NEVER,
        private readonly string $directorySeparator = DIRECTORY_SEPARATOR,
    ) {
        if (!in_array($this->compressionMode, [
            Device::COMPRESSION_METADATA,
            Device::COMPRESSION_NEVER,
            Device::COMPRESSION_ALWAYS,
        ], true)) {
            throw new \InvalidArgumentException('Unknown Syncthing compression mode');
        }
        if ($this->directorySeparator === '') {
            throw new \InvalidArgumentException('Directory separator must not be empty');
        }

        $this->writer = \Closure::fromCallable($writer);
    }

    public function deviceId(): string
    {
        return $this->deviceId;
    }

    public function sendDownloadProgress(DownloadProgress $progress): void
    {
        $normalized = $progress->normalizedForWire($this->directorySeparator);
        $frame = BepWire::encodeDownloadProgressMessage($normalized, $this->compressionMode);

        ($this->writer)($this->deviceId, $frame, $normalized);
    }
}
