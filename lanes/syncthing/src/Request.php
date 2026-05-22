<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class Request
{
    public function __construct(
        public readonly int $id = 0,
        public readonly string $folder = '',
        public readonly string $name = '',
        public readonly int $offset = 0,
        public readonly int $size = 0,
        public readonly string $hashHex = '',
        public readonly bool $fromTemporary = false,
        public readonly int $blockNo = 0,
    ) {
        if ($this->hashHex !== '' && !preg_match('/^(?:[0-9a-f]{2})+$/', $this->hashHex)) {
            throw new \InvalidArgumentException('Expected lowercase even-length hex for request hash bytes');
        }
    }

    public function normalizedForWire(string $directorySeparator = DIRECTORY_SEPARATOR): self
    {
        return new self(
            id: $this->id,
            folder: $this->folder,
            name: ProtocolValidation::normalizeWireName($this->name, $directorySeparator),
            offset: $this->offset,
            size: $this->size,
            hashHex: $this->hashHex,
            fromTemporary: $this->fromTemporary,
            blockNo: $this->blockNo,
        );
    }
}
