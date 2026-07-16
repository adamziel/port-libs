<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FileInfo
{
    public const TYPE_FILE = 0;
    public const TYPE_DIRECTORY = 1;
    public const TYPE_SYMLINK_FILE = 2;
    public const TYPE_SYMLINK_DIRECTORY = 3;
    public const TYPE_SYMLINK = 4;

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
        public readonly int $type = self::TYPE_FILE,
        public readonly int $permissions = 0,
        public readonly bool $noPermissions = false,
        public readonly int $rawBlockSize = 0,
        public readonly int $sequence = 0,
        public readonly string $symlinkTarget = '',
        /** @var list<Block> */
        public readonly array $blocks = [],
        public readonly ?string $unixOwnerName = null,
        public readonly ?string $unixGroupName = null,
        public readonly ?int $unixUid = null,
        public readonly ?int $unixGid = null,
        public readonly int $modifiedBy = 0,
        public readonly string $encryptedPayload = '',
        /** @var array<string, string> */
        public readonly array $xattrs = [],
    ) {
        if (
            $this->modifiedS < 0
            || $this->modifiedNs < 0
            || $this->size < 0
            || $this->localFlags < 0
            || $this->permissions < 0
            || $this->rawBlockSize < 0
            || $this->sequence < 0
            || $this->modifiedBy < 0
        ) {
            throw new \InvalidArgumentException('FileInfo numeric fields must not be negative');
        }
        if (!in_array($this->type, [
            self::TYPE_FILE,
            self::TYPE_DIRECTORY,
            self::TYPE_SYMLINK_FILE,
            self::TYPE_SYMLINK_DIRECTORY,
            self::TYPE_SYMLINK,
        ], true)) {
            throw new \InvalidArgumentException('Unknown FileInfo type');
        }
        foreach ($this->blocks as $block) {
            if (!$block instanceof Block) {
                throw new \InvalidArgumentException('Expected only Block instances');
            }
        }
        foreach ($this->xattrs as $name => $value) {
            if (!is_string($name) || $name === '' || str_contains($name, "\0") || !is_string($value)) {
                throw new \InvalidArgumentException('Extended attributes must be a string map with non-empty names');
            }
        }
        if (($this->unixUid === null) !== ($this->unixGid === null)) {
            throw new \InvalidArgumentException('Unix ownership IDs must be both present or both absent');
        }
        $this->assertOptionalHash($this->blocksHash, 'blocks hash');
        $this->assertOptionalHash($this->previousBlocksHash, 'previous blocks hash');
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function withName(string $name): self
    {
        return new self(
            name: $name,
            modifiedS: $this->modifiedS,
            modifiedNs: $this->modifiedNs,
            version: $this->version,
            deleted: $this->deleted,
            localFlags: $this->localFlags,
            size: $this->size,
            blocksHash: $this->blocksHash,
            previousBlocksHash: $this->previousBlocksHash,
            type: $this->type,
            permissions: $this->permissions,
            noPermissions: $this->noPermissions,
            rawBlockSize: $this->rawBlockSize,
            sequence: $this->sequence,
            symlinkTarget: $this->symlinkTarget,
            blocks: $this->blocks,
            unixOwnerName: $this->unixOwnerName,
            unixGroupName: $this->unixGroupName,
            unixUid: $this->unixUid,
            unixGid: $this->unixGid,
            modifiedBy: $this->modifiedBy,
            encryptedPayload: $this->encryptedPayload,
            xattrs: $this->xattrs,
        );
    }

    public function withSequence(int $sequence): self
    {
        return new self(
            name: $this->name,
            modifiedS: $this->modifiedS,
            modifiedNs: $this->modifiedNs,
            version: $this->version,
            deleted: $this->deleted,
            localFlags: $this->localFlags,
            size: $this->size,
            blocksHash: $this->blocksHash,
            previousBlocksHash: $this->previousBlocksHash,
            type: $this->type,
            permissions: $this->permissions,
            noPermissions: $this->noPermissions,
            rawBlockSize: $this->rawBlockSize,
            sequence: $sequence,
            symlinkTarget: $this->symlinkTarget,
            blocks: $this->blocks,
            unixOwnerName: $this->unixOwnerName,
            unixGroupName: $this->unixGroupName,
            unixUid: $this->unixUid,
            unixGid: $this->unixGid,
            modifiedBy: $this->modifiedBy,
            encryptedPayload: $this->encryptedPayload,
            xattrs: $this->xattrs,
        );
    }

    public function withSize(int $size): self
    {
        return new self(
            name: $this->name,
            modifiedS: $this->modifiedS,
            modifiedNs: $this->modifiedNs,
            version: $this->version,
            deleted: $this->deleted,
            localFlags: $this->localFlags,
            size: $size,
            blocksHash: $this->blocksHash,
            previousBlocksHash: $this->previousBlocksHash,
            type: $this->type,
            permissions: $this->permissions,
            noPermissions: $this->noPermissions,
            rawBlockSize: $this->rawBlockSize,
            sequence: $this->sequence,
            symlinkTarget: $this->symlinkTarget,
            blocks: $this->blocks,
            unixOwnerName: $this->unixOwnerName,
            unixGroupName: $this->unixGroupName,
            unixUid: $this->unixUid,
            unixGid: $this->unixGid,
            modifiedBy: $this->modifiedBy,
            encryptedPayload: $this->encryptedPayload,
            xattrs: $this->xattrs,
        );
    }

    public function isInvalid(): bool
    {
        return self::flagsInvalid($this->localFlags);
    }

    public function isUnsupported(): bool
    {
        return ($this->localFlags & self::FLAG_LOCAL_UNSUPPORTED) !== 0;
    }

    public function isIgnored(): bool
    {
        return ($this->localFlags & self::FLAG_LOCAL_IGNORED) !== 0;
    }

    public function mustRescan(): bool
    {
        return ($this->localFlags & self::FLAG_LOCAL_MUST_RESCAN) !== 0;
    }

    public function isReceiveOnlyChanged(): bool
    {
        return ($this->localFlags & self::FLAG_LOCAL_RECEIVE_ONLY) !== 0;
    }

    public function isDirectory(): bool
    {
        return $this->type === self::TYPE_DIRECTORY;
    }

    public function isSymlink(): bool
    {
        return in_array($this->type, [
            self::TYPE_SYMLINK_FILE,
            self::TYPE_SYMLINK_DIRECTORY,
            self::TYPE_SYMLINK,
        ], true);
    }

    public function blockSize(): int
    {
        return max($this->rawBlockSize, BlockList::MIN_BLOCK_SIZE);
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

    public function blocksEqual(self $other): bool
    {
        if ($this->blocksHash !== '' && $other->blocksHash !== '' && hash_equals($this->blocksHash, $other->blocksHash)) {
            return true;
        }

        if (count($this->blocks) !== count($other->blocks)) {
            return false;
        }

        foreach ($this->blocks as $index => $block) {
            if (!hash_equals(strtolower($block->hashHex), strtolower($other->blocks[$index]->hashHex))) {
                return false;
            }
        }

        return true;
    }

    public function isEquivalent(self $other, ?FileInfoComparison $comparison = null): bool
    {
        $comparison ??= new FileInfoComparison();

        if ($this->mustRescan() || $other->mustRescan()) {
            return false;
        }

        $leftFlags = $this->localFlags & ~$comparison->ignoreFlags;
        $rightFlags = $other->localFlags & ~$comparison->ignoreFlags;

        if (
            $this->name !== $other->name
            || $this->type !== $other->type
            || $this->deleted !== $other->deleted
            || self::flagsInvalid($leftFlags) !== self::flagsInvalid($rightFlags)
        ) {
            return false;
        }

        if (!$comparison->ignoreOwnership && !$this->unixOwnershipEqual($other)) {
            return false;
        }
        if (!$comparison->ignoreXattrs && $this->xattrs !== $other->xattrs) {
            return false;
        }

        if (
            !$comparison->ignorePerms
            && !$this->noPermissions
            && !$other->noPermissions
            && !self::permissionsEqual($this->permissions, $other->permissions)
        ) {
            return false;
        }

        return match ($this->type) {
            self::TYPE_FILE => $this->size === $other->size
                && $this->modTimeEqual($other, $comparison->modTimeWindowNs)
                && ($comparison->ignoreBlocks || $this->blocksEqual($other)),
            self::TYPE_DIRECTORY => true,
            self::TYPE_SYMLINK => $this->symlinkTarget === $other->symlinkTarget,
            default => false,
        };
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
            type: $this->type,
            permissions: $this->permissions,
            noPermissions: $this->noPermissions,
            rawBlockSize: $this->rawBlockSize,
            sequence: $this->sequence,
            symlinkTarget: $this->symlinkTarget,
            unixOwnerName: $this->unixOwnerName,
            unixGroupName: $this->unixGroupName,
            unixUid: $this->unixUid,
            unixGid: $this->unixGid,
            modifiedBy: $deviceId,
            encryptedPayload: $this->encryptedPayload,
            xattrs: $this->xattrs,
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

    private static function flagsInvalid(int $flags): bool
    {
        return ($flags & self::LOCAL_INVALID_FLAGS) !== 0;
    }

    private static function permissionsEqual(int $left, int $right): bool
    {
        return ($left & 0777) === ($right & 0777);
    }

    private function modTimeEqual(self $other, int $windowNs): bool
    {
        if ($this->modifiedS === $other->modifiedS && $this->modifiedNs === $other->modifiedNs) {
            return true;
        }

        $diff = abs((($this->modifiedS - $other->modifiedS) * 1_000_000_000) + ($this->modifiedNs - $other->modifiedNs));

        return $diff < $windowNs;
    }

    private function unixOwnershipEqual(self $other): bool
    {
        $leftEmpty = $this->unixUid === null && $this->unixGid === null && $this->unixOwnerName === null && $this->unixGroupName === null;
        $rightEmpty = $other->unixUid === null && $other->unixGid === null && $other->unixOwnerName === null && $other->unixGroupName === null;

        if ($leftEmpty && $rightEmpty) {
            return true;
        }
        if ($leftEmpty || $rightEmpty || $this->unixUid === null || $this->unixGid === null || $other->unixUid === null || $other->unixGid === null) {
            return false;
        }
        if ($this->unixUid === $other->unixUid && $this->unixGid === $other->unixGid) {
            return true;
        }

        return ($this->unixOwnerName ?? '') === ($other->unixOwnerName ?? '')
            && ($this->unixGroupName ?? '') === ($other->unixGroupName ?? '');
    }
}
