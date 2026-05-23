<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderScanApiResponse
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $body,
    ) {
        if ($this->statusCode < 100 || $this->statusCode > 599) {
            throw new \InvalidArgumentException('Folder scan API status code must be an HTTP status code');
        }
    }

    public function successful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * @return array{statusCode:int, body:array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'statusCode' => $this->statusCode,
            'body' => $this->body,
        ];
    }
}
