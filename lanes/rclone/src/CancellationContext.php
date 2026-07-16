<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class CancellationContext
{
    public const ERR_CANCELED = 'context canceled';

    private ?\Throwable $error = null;

    public static function canceled(\Throwable|string|null $error = null): self
    {
        $context = new self();
        $context->cancel($error);

        return $context;
    }

    public function cancel(\Throwable|string|null $error = null): void
    {
        if ($this->error !== null) {
            return;
        }

        $this->error = $error instanceof \Throwable
            ? $error
            : new \RuntimeException($error ?? self::ERR_CANCELED);
    }

    public function error(): ?\Throwable
    {
        return $this->error;
    }

    public function isCanceled(): bool
    {
        return $this->error !== null;
    }
}
