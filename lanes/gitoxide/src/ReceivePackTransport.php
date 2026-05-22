<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

interface ReceivePackTransport
{
    public function readAdvertisement(): string;

    public function writeRequest(string $requestBytes): void;

    public function readResponse(): string;
}
