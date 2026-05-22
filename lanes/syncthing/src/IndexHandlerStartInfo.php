<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class IndexHandlerStartInfo
{
    public const REMOTE_INDEX_KEEP = 'keep';
    public const REMOTE_INDEX_DROP = 'drop';
    public const REMOTE_INDEX_DROP_AND_STORE = 'drop-and-store';

    public function __construct(
        public readonly Device $local,
        public readonly Device $remote,
    ) {
    }

    public static function fromClusterFolder(Folder $folder, string $localDeviceIdHex, string $remoteDeviceIdHex): self
    {
        $local = null;
        $remote = null;
        foreach ($folder->devices as $device) {
            if ($device->idHex === $localDeviceIdHex) {
                $local = $device;
            }
            if ($device->idHex === $remoteDeviceIdHex) {
                $remote = $device;
            }
        }

        if (!$local instanceof Device) {
            throw new \InvalidArgumentException('ClusterConfig folder is missing local device info');
        }
        if (!$remote instanceof Device) {
            throw new \InvalidArgumentException('ClusterConfig folder is missing remote device info');
        }

        return new self($local, $remote);
    }

    public function localStartSequence(int $localIndexId, int $localCurrentSequence): int
    {
        if ($localIndexId < 0 || $localCurrentSequence < 0) {
            throw new \InvalidArgumentException('Index IDs and sequence numbers must not be negative');
        }

        if ($this->local->indexId !== $localIndexId) {
            return 0;
        }
        if ($this->local->maxSequence > $localCurrentSequence) {
            return 0;
        }

        return $this->local->maxSequence;
    }

    public function remoteIndexAction(int $knownRemoteIndexId): string
    {
        if ($knownRemoteIndexId < 0) {
            throw new \InvalidArgumentException('Known remote index ID must not be negative');
        }
        if ($this->remote->indexId === 0) {
            return self::REMOTE_INDEX_DROP;
        }
        if ($this->remote->indexId !== $knownRemoteIndexId) {
            return self::REMOTE_INDEX_DROP_AND_STORE;
        }

        return self::REMOTE_INDEX_KEEP;
    }
}
