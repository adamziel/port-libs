<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class BlockRequestPlan
{
    public function __construct(
        public readonly string $deviceId,
        public readonly Request $request,
    ) {
        if ($this->deviceId === '') {
            throw new \InvalidArgumentException('Request plan device ID must not be empty');
        }
    }

    public function fromTemporary(): bool
    {
        return $this->request->fromTemporary;
    }

    /**
     * @return array<string, int|string|bool>
     */
    public function toArray(): array
    {
        return [
            'device' => $this->deviceId,
            'folder' => $this->request->folder,
            'name' => $this->request->name,
            'blockNo' => $this->request->blockNo,
            'offset' => $this->request->offset,
            'size' => $this->request->size,
            'hashHex' => $this->request->hashHex,
            'fromTemporary' => $this->request->fromTemporary,
        ];
    }
}
