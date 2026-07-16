<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class ReOpenReader
{
    public const ERR_FILE_CLOSED = 'file already closed';
    public const ERR_TOO_MANY_TRIES = 'failed to reopen: too many retries';
    public const ERR_INVALID_WHENCE = 'reopen Seek: invalid whence';
    public const ERR_NEGATIVE_SEEK = 'reopen Seek: negative position';
    public const ERR_SEEK_PAST_END = 'reopen Seek: attempt to seek past end of data';
    public const ERR_BAD_END_SEEK = "reopen Seek: can't seek from end with unknown sized object";

    private ?object $reader = null;
    private int $offset = 0;
    private ?int $newOffset = null;
    private int $tries = 0;
    private ?\Throwable $err = null;
    private bool $opened = false;
    private $account = null;
    private int $reads = 0;
    private int $accountOn = 0;
    private readonly int $start;
    private readonly ?int $end;
    private readonly ?int $selectedSize;
    private bool $atEnd = false;

    /**
     * @param array{rangeStart?: int, rangeEnd?: int, seekOffset?: int} $options
     */
    public function __construct(
        private readonly MemoryProvider $provider,
        private readonly string $path,
        private readonly int $maxTries,
        array $options = [],
    ) {
        $size = $provider->info($path)->size;
        $this->start = max(0, (int) ($options['seekOffset'] ?? $options['rangeStart'] ?? 0));
        $rangeEnd = $options['rangeEnd'] ?? null;
        if ($size < 0 && ($rangeEnd === null || (int) $rangeEnd < 0)) {
            $this->end = null;
            $this->selectedSize = null;
        } else {
            $this->end = $rangeEnd === null || (int) $rangeEnd < 0
                ? $size
                : ($size < 0 ? (int) $rangeEnd + 1 : min($size, (int) $rangeEnd + 1));
            if ($this->start > $this->end) {
                throw new \InvalidArgumentException('reopen range starts past end of data');
            }

            $this->selectedSize = $this->end - $this->start;
        }
        $this->open();
    }

    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }
        if ($this->err !== null) {
            throw $this->err;
        }

        if ($this->newOffset !== null) {
            if ($this->offset !== $this->newOffset) {
                $this->offset = $this->newOffset;
                $this->reopen();
            }
            $this->newOffset = null;
        }

        $bytes = '';
        $startOffset = $this->offset;

        while (strlen($bytes) < $length && !$this->eof()) {
            try {
                $chunk = $this->reader()->read($length - strlen($bytes));
            } catch (\Throwable $throwable) {
                $this->err = $throwable;
                if (self::isNoLowLevelRetryError($throwable)) {
                    if ($bytes !== '') {
                        break;
                    }

                    throw $throwable;
                }

                try {
                    $this->reopen();
                    $this->err = null;
                    continue;
                } catch (\Throwable $reopenError) {
                    $this->err = $reopenError;
                    if ($bytes !== '') {
                        break;
                    }

                    throw $throwable;
                }
            }

            if (!is_string($chunk)) {
                throw new \UnexpectedValueException('Reader read() must return a string');
            }
            if ($chunk === '') {
                if ($this->selectedSize === null) {
                    $this->atEnd = true;
                }
                break;
            }

            $bytes .= $chunk;
            $this->offset += strlen($chunk);
        }

        if ($startOffset === 0 && $bytes !== '') {
            $this->reads++;
        }

        $this->accountRead(strlen($bytes));

        return $bytes;
    }

    public function readAt(int $length, int $offset): string
    {
        $current = $this->seek(0, SEEK_CUR);
        $this->seek($offset, SEEK_SET);
        try {
            return $this->read($length);
        } finally {
            $this->seek($current, SEEK_SET);
        }
    }

    public function seek(int $offset, int $whence = SEEK_SET): int
    {
        if ($this->err !== null) {
            throw $this->err;
        }

        $current = $this->newOffset ?? $this->offset;
        $absolute = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $current + $offset,
            SEEK_END => $this->selectedSize === null
                ? throw new \RuntimeException(self::ERR_BAD_END_SEEK)
                : $this->selectedSize + $offset,
            default => throw new \InvalidArgumentException(self::ERR_INVALID_WHENCE),
        };

        if ($absolute < 0) {
            throw new \RuntimeException(self::ERR_NEGATIVE_SEEK);
        }
        if ($this->selectedSize !== null && $absolute > $this->selectedSize) {
            throw new \RuntimeException(self::ERR_SEEK_PAST_END);
        }

        $this->tries = 0;
        if ($this->selectedSize === null && $absolute !== $this->offset) {
            $this->atEnd = false;
        }
        $this->newOffset = $absolute;

        return $absolute;
    }

    public function eof(): bool
    {
        if ($this->selectedSize === null) {
            return $this->atEnd && $this->newOffset === null;
        }

        return $this->offset >= $this->selectedSize && $this->newOffset === null;
    }

    public function close(): void
    {
        if (!$this->opened) {
            throw new \RuntimeException(self::ERR_FILE_CLOSED);
        }

        $this->opened = false;
        $this->err = new \RuntimeException(self::ERR_FILE_CLOSED);
        if (is_object($this->reader) && method_exists($this->reader, 'close')) {
            $this->reader->close();
        }
    }

    public function setAccounting(callable $account): self
    {
        $this->account = $account;

        return $this;
    }

    public function delayAccounting(int $readNumber): void
    {
        $this->accountOn = max(0, $readNumber);
        $this->reads = 0;
    }

    private function open(): void
    {
        $this->tries++;
        if ($this->tries > $this->maxTries) {
            $this->opened = false;
            $this->err = new \RuntimeException(self::ERR_TOO_MANY_TRIES);
            throw $this->err;
        }

        $absoluteOffset = $this->start + $this->offset;
        $remaining = $this->selectedSize === null ? null : $this->selectedSize - $this->offset;
        $this->reader = $this->provider->openReader($this->path, $absoluteOffset, $remaining);
        $this->opened = true;
        $this->atEnd = false;
    }

    private function reopen(): void
    {
        if ($this->opened && is_object($this->reader) && method_exists($this->reader, 'close')) {
            $this->reader->close();
        }
        $this->opened = false;
        $this->open();
    }

    private function reader(): object
    {
        if (!$this->opened || $this->reader === null) {
            throw new \RuntimeException(self::ERR_FILE_CLOSED);
        }

        return $this->reader;
    }

    private function accountRead(int $bytes): void
    {
        if ($this->account === null) {
            return;
        }
        if ($this->reads >= $this->accountOn) {
            $error = ($this->account)($bytes);
            if ($error instanceof \Throwable) {
                throw $error;
            }
            if ($error !== null) {
                throw new \UnexpectedValueException('Accounting callback must return null or Throwable');
            }
        }
    }

    private static function isNoLowLevelRetryError(\Throwable $throwable): bool
    {
        do {
            if ($throwable instanceof NoLowLevelRetryException) {
                return true;
            }
            if (method_exists($throwable, 'noLowLevelRetry') && $throwable->noLowLevelRetry() === true) {
                return true;
            }

            $throwable = $throwable->getPrevious();
        } while ($throwable !== null);

        return false;
    }
}
