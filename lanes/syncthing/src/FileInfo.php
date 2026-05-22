<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FileInfo
{
    public const FLAG_LOCAL_UNSUPPORTED = 1;
    public const FLAG_LOCAL_IGNORED = 1 << 1;
    public const FLAG_LOCAL_MUST_RESCAN = 1 << 2;
    public const FLAG_LOCAL_RECEIVE_ONLY = 1 << 3;
    public const FLAG_LOCAL_GLOBAL = 1 << 4;
    public const FLAG_LOCAL_NEEDED = 1 << 5;
    public const FLAG_LOCAL_REMOTE_INVALID = 1 << 6;

    public const LOCAL_INVALID_FLAGS = self::FLAG_LOCAL_UNSUPPORTED
        | self::FLAG_LOCAL_IGNORED
        | self::FLAG_LOCAL_MUST_RESCAN
        | self::FLAG_LOCAL_RECEIVE_ONLY
        | self::FLAG_LOCAL_REMOTE_INVALID;

    public const LOCAL_CONFLICT_FLAGS = self::FLAG_LOCAL_UNSUPPORTED
        | self::FLAG_LOCAL_IGNORED
        | self::FLAG_LOCAL_RECEIVE_ONLY;

    public function __construct(
        public readonly string $name = '',
        public readonly int $modifiedS = 0,
        public readonly int $modifiedNs = 0,
        public readonly VersionVector $version = new VersionVector(),
        public readonly bool $deleted = false,
        public readonly int $localFlags = 0,
        public readonly int $size = 0,
        public readonly string $blocksHash = '',
        public readonly string $previousBlocksHash = '',
    ) {
        if ($this->modifiedS < 0 || $this->modifiedNs < 0 || $this->size < 0 || $this->localFlags < 0) {
            throw new \InvalidArgumentException('FileInfo numeric fields must not be negative');
        }
        $this->assertOptionalHash($this->blocksHash, 'blocks hash');
        $this->assertOptionalHash($this->previousBlocksHash, 'previous blocks hash');
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function isInvalid(): bool
    {
        return ($this->localFlags & self::LOCAL_INVALID_FLAGS) !== 0;
    }

    public function shouldConflict(): bool
    {
        return ($this->localFlags & self::LOCAL_CONFLICT_FLAGS) !== 0;
    }

    public function inConflictWith(self $previous): bool
    {
        if ($this->version->greaterEqual($previous->version)) {
            return false;
        }

        if ($this->previousBlocksHash === '' || $this->blocksHash === '') {
            return true;
        }

        return !hash_equals($previous->blocksHash, $this->previousBlocksHash);
    }

    public function winsConflict(self $other): bool
    {
        if ($this->isInvalid() !== $other->isInvalid()) {
            return !$this->isInvalid();
        }

        $timeComparison = $this->compareModTime($other);
        if ($timeComparison > 0) {
            return true;
        }
        if ($timeComparison < 0) {
            return false;
        }

        return $this->version->compare($other->version) === VersionVector::ORDER_CONCURRENT_GREATER;
    }

    public function withDeleted(int $deviceId, int $modifiedS): self
    {
        return new self(
            name: $this->name,
            modifiedS: $modifiedS,
            modifiedNs: 0,
            version: $this->version->update($deviceId, $modifiedS),
            deleted: true,
            localFlags: $this->localFlags,
        );
    }

    private function compareModTime(self $other): int
    {
        return [$this->modifiedS, $this->modifiedNs] <=> [$other->modifiedS, $other->modifiedNs];
    }

    private function assertOptionalHash(string $hashHex, string $label): void
    {
        if ($hashHex !== '' && !preg_match('/^[0-9a-f]{64}$/', $hashHex)) {
            throw new \InvalidArgumentException('Expected lowercase SHA-256 hex for ' . $label);
        }
    }
}
