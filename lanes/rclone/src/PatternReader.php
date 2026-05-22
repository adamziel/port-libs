<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class PatternReader
{
    public const ERR_INVALID_WHENCE = 'patternReader: invalid whence';
    public const ERR_NEGATIVE_POSITION = 'patternReader: negative position';

    private const MODULO = 251;

    private int $offset = 0;
    private int $currentByte = 0;

    public function __construct(private readonly int $length)
    {
    }

    public function read(int $length): string
    {
        if ($length <= 0 || $this->offset >= $this->length) {
            return '';
        }

        $out = '';
        $remaining = min($length, $this->length - $this->offset);
        for ($i = 0; $i < $remaining; $i++) {
            $out .= chr($this->currentByte);
            $this->currentByte = ($this->currentByte + 1) % self::MODULO;
            $this->offset++;
        }

        return $out;
    }

    public function seek(int $offset, int $whence = SEEK_SET): int
    {
        $absolute = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->offset + $offset,
            SEEK_END => $this->length + $offset,
            default => throw new \InvalidArgumentException(self::ERR_INVALID_WHENCE),
        };

        if ($absolute < 0) {
            throw new \RuntimeException(self::ERR_NEGATIVE_POSITION);
        }

        $this->offset = $absolute;
        $this->currentByte = $absolute % self::MODULO;

        return $absolute;
    }

    public function tell(): int
    {
        return $this->offset;
    }
}
