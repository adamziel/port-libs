<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FileInfoBatch
{
    public const MAX_BATCH_SIZE_BYTES = 250 * 1024;
    public const MAX_BATCH_SIZE_FILES = 1000;

    /**
     * @var list<FileInfo>
     */
    private array $infos = [];

    private int $size = 0;

    private ?\Throwable $error = null;

    /**
     * @param callable(list<FileInfo>): (\Throwable|null) $flushFn
     */
    public function __construct(private \Closure $flushFn)
    {
    }

    /**
     * @param callable(list<FileInfo>): (\Throwable|null) $flushFn
     */
    public static function withFlushFunction(callable $flushFn): self
    {
        return new self(\Closure::fromCallable($flushFn));
    }

    /**
     * @param callable(list<FileInfo>): (\Throwable|null) $flushFn
     */
    public function setFlushFunction(callable $flushFn): void
    {
        $this->flushFn = \Closure::fromCallable($flushFn);
    }

    public function append(FileInfo $file): void
    {
        if ($this->error !== null) {
            throw new \LogicException('calling append on a failed FileInfoBatch');
        }

        $this->infos[] = $file;
        $this->size += strlen(BepWire::encodeFileInfoPayload($file));
    }

    public function full(): bool
    {
        return count($this->infos) >= self::MAX_BATCH_SIZE_FILES
            || $this->size >= self::MAX_BATCH_SIZE_BYTES;
    }

    public function flushIfFull(): ?\Throwable
    {
        if ($this->error !== null) {
            return $this->error;
        }

        return $this->full() ? $this->flush() : null;
    }

    public function flush(): ?\Throwable
    {
        if ($this->error !== null) {
            return $this->error;
        }
        if ($this->infos === []) {
            return null;
        }

        try {
            $result = ($this->flushFn)($this->infos);
        } catch (\Throwable $throwable) {
            $this->error = $throwable;

            return $throwable;
        }

        if ($result instanceof \Throwable) {
            $this->error = $result;

            return $result;
        }
        if ($result !== null) {
            throw new \UnexpectedValueException('FileInfoBatch flush function must return null or Throwable');
        }

        $this->reset();

        return null;
    }

    public function reset(): void
    {
        $this->infos = [];
        $this->error = null;
        $this->size = 0;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function count(): int
    {
        return count($this->infos);
    }

    /**
     * @return list<FileInfo>
     */
    public function pending(): array
    {
        return $this->infos;
    }
}
