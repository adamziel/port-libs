<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class SmartHttpStatusException extends \RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        private readonly string $kind,
        private readonly bool $retryable,
        string $label,
    ) {
        parent::__construct("{$label}: Received HTTP status {$statusCode} ({$kind})");
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function retryable(): bool
    {
        return $this->retryable;
    }
}
