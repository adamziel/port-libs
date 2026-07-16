<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class SendPackRequest
{
    public function __construct(
        private readonly PushCommand $command,
        private readonly string $requestBytes,
        private readonly ?PackBuildResult $pack,
    ) {
    }

    public function command(): PushCommand
    {
        return $this->command;
    }

    public function requestBytes(): string
    {
        return $this->requestBytes;
    }

    public function pack(): ?PackBuildResult
    {
        return $this->pack;
    }

    public function hasPack(): bool
    {
        return $this->pack !== null;
    }
}
