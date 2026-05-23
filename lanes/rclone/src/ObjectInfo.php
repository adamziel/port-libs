<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class ObjectInfo
{
    /**
     * @param array<string, string> $metadata
     * @param array<string, string> $hashes
     */
    public function __construct(
        public readonly string $path,
        public readonly int $size,
        public readonly string $sha256,
        public readonly ?string $modTime = null,
        public readonly ?string $mimeType = null,
        public readonly array $metadata = [],
        public readonly ?string $id = null,
        public readonly ?string $tier = null,
        public readonly array $hashes = [],
        public readonly ?string $providerKey = null,
        public readonly ?string $parentId = null,
        public readonly ?\Throwable $metadataError = null,
    ) {
    }

    /**
     * Model providers whose listed entries are available but whose metadata
     * reader can fail later, such as OneDrive permission metadata reads.
     *
     * @return array<string, string>
     */
    public function readMetadata(): array
    {
        if ($this->metadataError !== null) {
            throw $this->metadataError;
        }

        return $this->metadata;
    }
}
