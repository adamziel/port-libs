<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class WordPressOptionCheckpointStore implements FolderScanCheckpointRepository
{
    public const DEFAULT_OPTION_PREFIX = 'local_first_syncthing_checkpoint_';

    private const MAX_OPTION_NAME_LENGTH = 191;
    private const PAYLOAD_SCHEMA = 1;

    public function __construct(
        private readonly string $optionPrefix,
        private readonly mixed $getOption,
        private readonly mixed $updateOption,
        private readonly mixed $deleteOption,
        private readonly mixed $compareAndSwapOption = null,
    ) {
        if ($this->optionPrefix === '') {
            throw new \InvalidArgumentException('WordPress checkpoint option prefix must not be empty');
        }
        if (strlen($this->optionPrefix) + 64 > self::MAX_OPTION_NAME_LENGTH) {
            throw new \InvalidArgumentException('WordPress checkpoint option prefix leaves no room for a hashed folder ID');
        }
        foreach ([
            'get option' => $this->getOption,
            'update option' => $this->updateOption,
            'delete option' => $this->deleteOption,
        ] as $label => $callback) {
            if (!is_callable($callback)) {
                throw new \InvalidArgumentException('WordPress checkpoint ' . $label . ' callback must be callable');
            }
        }
        if ($this->compareAndSwapOption !== null && !is_callable($this->compareAndSwapOption)) {
            throw new \InvalidArgumentException('WordPress checkpoint compare-and-swap callback must be callable or null');
        }
    }

    public static function fromWordPressOptions(string $optionPrefix = self::DEFAULT_OPTION_PREFIX): self
    {
        return new self(
            $optionPrefix,
            static fn (string $key): mixed => \get_option($key, null),
            static fn (string $key, mixed $value): bool => \update_option($key, $value, false),
            static fn (string $key): bool => \delete_option($key),
        );
    }

    public function optionName(string $folderId): string
    {
        self::assertFolderId($folderId);

        if (preg_match('/^[A-Za-z0-9_.-]+$/', $folderId) === 1) {
            $candidate = $this->optionPrefix . $folderId;
            if (strlen($candidate) <= self::MAX_OPTION_NAME_LENGTH) {
                return $candidate;
            }
        }

        return $this->optionPrefix . hash('sha256', $folderId);
    }

    public function load(string $folderId, ?int $now = null): ?FolderScanCheckpointSnapshot
    {
        self::assertFolderId($folderId);
        $now ??= time();
        self::assertClock($now);

        return $this->readSnapshot($folderId, $now, deleteExpired: true);
    }

    public function save(
        FolderScanCheckpoint $checkpoint,
        ?int $expectedRevision = null,
        ?int $now = null,
        ?int $ttlSeconds = null,
    ): FolderScanCheckpointSnapshot {
        $now ??= time();
        self::assertClock($now);
        self::assertExpectedRevision($expectedRevision);
        self::assertTtl($ttlSeconds);

        $folderId = $checkpoint->folderId();
        $current = $this->load($folderId, $now);
        $currentRevision = $current?->revision ?? 0;
        if ($expectedRevision !== null && $expectedRevision !== $currentRevision) {
            throw self::conflict($folderId, $expectedRevision, $currentRevision);
        }

        $snapshot = new FolderScanCheckpointSnapshot(
            $checkpoint,
            $currentRevision + 1,
            $now,
            $ttlSeconds === null ? $current?->expiresAt : $now + $ttlSeconds,
        );
        $this->writeSnapshot($folderId, $snapshot, $expectedRevision);

        return $snapshot;
    }

    public function mergeResult(
        string $folderId,
        FileInfoScanResult $result,
        ?int $expectedRevision = null,
        ?int $now = null,
        ?int $ttlSeconds = null,
    ): FolderScanCheckpointSnapshot {
        self::assertFolderId($folderId);
        $now ??= time();
        self::assertClock($now);
        self::assertExpectedRevision($expectedRevision);

        $current = $this->load($folderId, $now);
        $currentRevision = $current?->revision ?? 0;
        if ($expectedRevision !== null && $expectedRevision !== $currentRevision) {
            throw self::conflict($folderId, $expectedRevision, $currentRevision);
        }

        $checkpoint = $current === null
            ? FolderScanCheckpoint::fromResult($folderId, $result)
            : $current->checkpoint->withResult($result);

        return $this->save($checkpoint, $currentRevision, $now, $ttlSeconds);
    }

    public function delete(string $folderId, ?int $expectedRevision = null, ?int $now = null): bool
    {
        self::assertFolderId($folderId);
        $now ??= time();
        self::assertClock($now);
        self::assertExpectedRevision($expectedRevision);

        $current = $this->load($folderId, $now);
        $currentRevision = $current?->revision ?? 0;
        if ($expectedRevision !== null && $expectedRevision !== $currentRevision) {
            throw self::conflict($folderId, $expectedRevision, $currentRevision);
        }
        if ($current === null) {
            return false;
        }

        $result = ($this->deleteOption)($this->optionName($folderId));
        if ($result === false) {
            throw new \RuntimeException('Failed to delete WordPress checkpoint option for folder ' . $folderId);
        }

        return true;
    }

    private function readSnapshot(string $folderId, int $now, bool $deleteExpired): ?FolderScanCheckpointSnapshot
    {
        $key = $this->optionName($folderId);
        $payload = ($this->getOption)($key);
        if ($payload === null || $payload === false) {
            return null;
        }

        $snapshot = self::snapshotFromPayload($payload);
        if ($snapshot->folderId() !== $folderId) {
            throw new \RuntimeException('WordPress checkpoint option folder mismatch for ' . $folderId);
        }
        if ($snapshot->isExpired($now)) {
            if ($deleteExpired) {
                $result = ($this->deleteOption)($key);
                if ($result === false) {
                    throw new \RuntimeException('Failed to delete expired WordPress checkpoint option for folder ' . $folderId);
                }
            }

            return null;
        }

        return $snapshot;
    }

    private function writeSnapshot(string $folderId, FolderScanCheckpointSnapshot $snapshot, ?int $expectedRevision): void
    {
        $key = $this->optionName($folderId);
        $payload = self::snapshotToPayload($snapshot);

        $result = $this->compareAndSwapOption === null
            ? ($this->updateOption)($key, $payload)
            : ($this->compareAndSwapOption)($key, $payload, $expectedRevision);

        if ($result !== false) {
            return;
        }

        if ($expectedRevision !== null) {
            $actualRevision = $this->readSnapshot($folderId, $snapshot->updatedAt, deleteExpired: false)?->revision ?? 0;
            if ($actualRevision !== $expectedRevision) {
                throw self::conflict($folderId, $expectedRevision, $actualRevision);
            }
        }

        throw new \RuntimeException('Failed to update WordPress checkpoint option for folder ' . $folderId);
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshotToPayload(FolderScanCheckpointSnapshot $snapshot): array
    {
        return [
            'schema' => self::PAYLOAD_SCHEMA,
            'revision' => $snapshot->revision,
            'updatedAt' => $snapshot->updatedAt,
            'expiresAt' => $snapshot->expiresAt,
            'checkpoint' => self::checkpointToPayload($snapshot->checkpoint),
        ];
    }

    private static function snapshotFromPayload(mixed $payload): FolderScanCheckpointSnapshot
    {
        if ($payload instanceof FolderScanCheckpointSnapshot) {
            return $payload;
        }
        if (!is_array($payload)) {
            throw new \RuntimeException('WordPress checkpoint option payload must be an array');
        }
        $schema = self::intValue($payload, 'schema', 0);
        if ($schema !== self::PAYLOAD_SCHEMA) {
            throw new \RuntimeException('Unsupported WordPress checkpoint option schema: ' . $schema);
        }

        return new FolderScanCheckpointSnapshot(
            self::checkpointFromPayload(self::arrayValue($payload, 'checkpoint')),
            self::intValue($payload, 'revision'),
            self::intValue($payload, 'updatedAt'),
            self::nullableIntValue($payload, 'expiresAt'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function checkpointToPayload(FolderScanCheckpoint $checkpoint): array
    {
        return [
            'folderId' => $checkpoint->folderId(),
            'resumeSubs' => $checkpoint->resumeSubs(),
            'currentFiles' => array_map(
                static fn (FileInfo $file): array => self::fileInfoToPayload($file),
                $checkpoint->resumeCurrentFiles(),
            ),
            'cancelled' => $checkpoint->cancelled(),
            'cancelledAt' => $checkpoint->cancelledAt(),
            'scanEvents' => $checkpoint->scanEvents(),
            'scanErrors' => $checkpoint->scanErrors(),
            'attempts' => $checkpoint->attempts(),
        ];
    }

    private static function checkpointFromPayload(array $payload): FolderScanCheckpoint
    {
        return new FolderScanCheckpoint(
            self::stringValue($payload, 'folderId'),
            self::stringListValue($payload, 'resumeSubs'),
            array_map(
                static fn (mixed $file): FileInfo => self::fileInfoFromPayload($file),
                self::arrayValue($payload, 'currentFiles', []),
            ),
            self::boolValue($payload, 'cancelled', false),
            self::nullableStringValue($payload, 'cancelledAt'),
            array_values(self::arrayValue($payload, 'scanEvents', [])),
            array_values(self::arrayValue($payload, 'scanErrors', [])),
            self::intValue($payload, 'attempts', 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function fileInfoToPayload(FileInfo $file): array
    {
        $version = [];
        foreach ($file->version->toArray() as $id => $value) {
            $version[] = ['id' => $id, 'value' => $value];
        }

        return [
            'name' => $file->name,
            'modifiedS' => $file->modifiedS,
            'modifiedNs' => $file->modifiedNs,
            'version' => $version,
            'deleted' => $file->deleted,
            'localFlags' => $file->localFlags,
            'size' => $file->size,
            'blocksHash' => $file->blocksHash,
            'previousBlocksHash' => $file->previousBlocksHash,
            'type' => $file->type,
            'permissions' => $file->permissions,
            'noPermissions' => $file->noPermissions,
            'rawBlockSize' => $file->rawBlockSize,
            'sequence' => $file->sequence,
            'symlinkTarget' => $file->symlinkTarget,
            'blocks' => array_map(
                static fn (Block $block): array => [
                    'offset' => $block->offset,
                    'size' => $block->size,
                    'hashHex' => $block->hashHex,
                ],
                $file->blocks,
            ),
            'unixOwnerName' => $file->unixOwnerName,
            'unixGroupName' => $file->unixGroupName,
            'unixUid' => $file->unixUid,
            'unixGid' => $file->unixGid,
            'modifiedBy' => $file->modifiedBy,
            'encryptedPayload' => $file->encryptedPayload,
            'xattrs' => $file->xattrs,
        ];
    }

    private static function fileInfoFromPayload(mixed $payload): FileInfo
    {
        if (!is_array($payload)) {
            throw new \RuntimeException('WordPress checkpoint FileInfo payload must be an array');
        }

        return new FileInfo(
            name: self::stringValue($payload, 'name', ''),
            modifiedS: self::intValue($payload, 'modifiedS', 0),
            modifiedNs: self::intValue($payload, 'modifiedNs', 0),
            version: VersionVector::fromCounters(self::arrayValue($payload, 'version', [])),
            deleted: self::boolValue($payload, 'deleted', false),
            localFlags: self::intValue($payload, 'localFlags', 0),
            size: self::intValue($payload, 'size', 0),
            blocksHash: self::stringValue($payload, 'blocksHash', ''),
            previousBlocksHash: self::stringValue($payload, 'previousBlocksHash', ''),
            type: self::intValue($payload, 'type', FileInfo::TYPE_FILE),
            permissions: self::intValue($payload, 'permissions', 0),
            noPermissions: self::boolValue($payload, 'noPermissions', false),
            rawBlockSize: self::intValue($payload, 'rawBlockSize', 0),
            sequence: self::intValue($payload, 'sequence', 0),
            symlinkTarget: self::stringValue($payload, 'symlinkTarget', ''),
            blocks: array_map(
                static fn (mixed $block): Block => self::blockFromPayload($block),
                self::arrayValue($payload, 'blocks', []),
            ),
            unixOwnerName: self::nullableStringValue($payload, 'unixOwnerName'),
            unixGroupName: self::nullableStringValue($payload, 'unixGroupName'),
            unixUid: self::nullableIntValue($payload, 'unixUid'),
            unixGid: self::nullableIntValue($payload, 'unixGid'),
            modifiedBy: self::intValue($payload, 'modifiedBy', 0),
            encryptedPayload: self::stringValue($payload, 'encryptedPayload', ''),
            xattrs: self::arrayValue($payload, 'xattrs', []),
        );
    }

    private static function blockFromPayload(mixed $payload): Block
    {
        if (!is_array($payload)) {
            throw new \RuntimeException('WordPress checkpoint block payload must be an array');
        }

        return new Block(
            self::intValue($payload, 'offset', 0),
            self::intValue($payload, 'size', 0),
            self::stringValue($payload, 'hashHex'),
        );
    }

    private static function assertFolderId(string $folderId): void
    {
        if ($folderId === '') {
            throw new \InvalidArgumentException('WordPress checkpoint store requires a folder ID');
        }
    }

    private static function assertClock(int $now): void
    {
        if ($now < 0) {
            throw new \InvalidArgumentException('WordPress checkpoint store clock must not be negative');
        }
    }

    private static function assertExpectedRevision(?int $expectedRevision): void
    {
        if ($expectedRevision !== null && $expectedRevision < 0) {
            throw new \InvalidArgumentException('WordPress checkpoint expected revision must not be negative');
        }
    }

    private static function assertTtl(?int $ttlSeconds): void
    {
        if ($ttlSeconds !== null && $ttlSeconds < 0) {
            throw new \InvalidArgumentException('WordPress checkpoint TTL must not be negative');
        }
    }

    private static function conflict(string $folderId, int $expectedRevision, int $actualRevision): FolderScanCheckpointConflictException
    {
        return new FolderScanCheckpointConflictException(
            sprintf(
                'WordPress checkpoint revision conflict for %s: expected %d, actual %d',
                $folderId,
                $expectedRevision,
                $actualRevision,
            ),
        );
    }

    private static function intValue(array $payload, string $key, ?int $default = null): int
    {
        $value = $payload[$key] ?? $default;
        if (!is_int($value)) {
            throw new \RuntimeException('WordPress checkpoint payload field must be an integer: ' . $key);
        }

        return $value;
    }

    private static function nullableIntValue(array $payload, string $key): ?int
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }
        if (!is_int($payload[$key])) {
            throw new \RuntimeException('WordPress checkpoint payload field must be an integer or null: ' . $key);
        }

        return $payload[$key];
    }

    private static function stringValue(array $payload, string $key, ?string $default = null): string
    {
        $value = $payload[$key] ?? $default;
        if (!is_string($value)) {
            throw new \RuntimeException('WordPress checkpoint payload field must be a string: ' . $key);
        }

        return $value;
    }

    private static function nullableStringValue(array $payload, string $key): ?string
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }
        if (!is_string($payload[$key])) {
            throw new \RuntimeException('WordPress checkpoint payload field must be a string or null: ' . $key);
        }

        return $payload[$key];
    }

    private static function boolValue(array $payload, string $key, bool $default): bool
    {
        $value = $payload[$key] ?? $default;
        if (!is_bool($value)) {
            throw new \RuntimeException('WordPress checkpoint payload field must be a boolean: ' . $key);
        }

        return $value;
    }

    private static function arrayValue(array $payload, string $key, ?array $default = null): array
    {
        $value = $payload[$key] ?? $default;
        if (!is_array($value)) {
            throw new \RuntimeException('WordPress checkpoint payload field must be an array: ' . $key);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function stringListValue(array $payload, string $key): array
    {
        $values = self::arrayValue($payload, $key, []);
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new \RuntimeException('WordPress checkpoint payload list must contain only strings: ' . $key);
            }
        }

        return array_values($values);
    }
}
