<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderScanCheckpointPayloadCodec
{
    public const PAYLOAD_SCHEMA = 1;

    /**
     * @return array<string, mixed>
     */
    public static function snapshotToPayload(FolderScanCheckpointSnapshot $snapshot): array
    {
        return [
            'schema' => self::PAYLOAD_SCHEMA,
            'revision' => $snapshot->revision,
            'updatedAt' => $snapshot->updatedAt,
            'expiresAt' => $snapshot->expiresAt,
            'checkpoint' => self::checkpointToPayload($snapshot->checkpoint),
        ];
    }

    public static function snapshotFromPayload(mixed $payload, string $label = 'folder scan checkpoint'): FolderScanCheckpointSnapshot
    {
        if ($payload instanceof FolderScanCheckpointSnapshot) {
            return $payload;
        }
        if (!is_array($payload)) {
            throw new \RuntimeException($label . ' payload must be an array');
        }
        $schema = self::intValue($payload, 'schema', 0, $label);
        if ($schema !== self::PAYLOAD_SCHEMA) {
            throw new \RuntimeException('Unsupported ' . $label . ' schema: ' . $schema);
        }

        return new FolderScanCheckpointSnapshot(
            self::checkpointFromPayload(self::arrayValue($payload, 'checkpoint', null, $label), $label),
            self::intValue($payload, 'revision', null, $label),
            self::intValue($payload, 'updatedAt', null, $label),
            self::nullableIntValue($payload, 'expiresAt', $label),
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

    private static function checkpointFromPayload(array $payload, string $label): FolderScanCheckpoint
    {
        return new FolderScanCheckpoint(
            self::stringValue($payload, 'folderId', null, $label),
            self::stringListValue($payload, 'resumeSubs', $label),
            array_map(
                static fn (mixed $file): FileInfo => self::fileInfoFromPayload($file, $label),
                self::arrayValue($payload, 'currentFiles', [], $label),
            ),
            self::boolValue($payload, 'cancelled', false, $label),
            self::nullableStringValue($payload, 'cancelledAt', $label),
            array_values(self::arrayValue($payload, 'scanEvents', [], $label)),
            array_values(self::arrayValue($payload, 'scanErrors', [], $label)),
            self::intValue($payload, 'attempts', 0, $label),
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

    private static function fileInfoFromPayload(mixed $payload, string $label): FileInfo
    {
        if (!is_array($payload)) {
            throw new \RuntimeException($label . ' FileInfo payload must be an array');
        }

        return new FileInfo(
            name: self::stringValue($payload, 'name', '', $label),
            modifiedS: self::intValue($payload, 'modifiedS', 0, $label),
            modifiedNs: self::intValue($payload, 'modifiedNs', 0, $label),
            version: VersionVector::fromCounters(self::arrayValue($payload, 'version', [], $label)),
            deleted: self::boolValue($payload, 'deleted', false, $label),
            localFlags: self::intValue($payload, 'localFlags', 0, $label),
            size: self::intValue($payload, 'size', 0, $label),
            blocksHash: self::stringValue($payload, 'blocksHash', '', $label),
            previousBlocksHash: self::stringValue($payload, 'previousBlocksHash', '', $label),
            type: self::intValue($payload, 'type', FileInfo::TYPE_FILE, $label),
            permissions: self::intValue($payload, 'permissions', 0, $label),
            noPermissions: self::boolValue($payload, 'noPermissions', false, $label),
            rawBlockSize: self::intValue($payload, 'rawBlockSize', 0, $label),
            sequence: self::intValue($payload, 'sequence', 0, $label),
            symlinkTarget: self::stringValue($payload, 'symlinkTarget', '', $label),
            blocks: array_map(
                static fn (mixed $block): Block => self::blockFromPayload($block, $label),
                self::arrayValue($payload, 'blocks', [], $label),
            ),
            unixOwnerName: self::nullableStringValue($payload, 'unixOwnerName', $label),
            unixGroupName: self::nullableStringValue($payload, 'unixGroupName', $label),
            unixUid: self::nullableIntValue($payload, 'unixUid', $label),
            unixGid: self::nullableIntValue($payload, 'unixGid', $label),
            modifiedBy: self::intValue($payload, 'modifiedBy', 0, $label),
            encryptedPayload: self::stringValue($payload, 'encryptedPayload', '', $label),
            xattrs: self::arrayValue($payload, 'xattrs', [], $label),
        );
    }

    private static function blockFromPayload(mixed $payload, string $label): Block
    {
        if (!is_array($payload)) {
            throw new \RuntimeException($label . ' block payload must be an array');
        }

        return new Block(
            self::intValue($payload, 'offset', 0, $label),
            self::intValue($payload, 'size', 0, $label),
            self::stringValue($payload, 'hashHex', null, $label),
        );
    }

    private static function intValue(array $payload, string $key, ?int $default, string $label): int
    {
        $value = $payload[$key] ?? $default;
        if (!is_int($value)) {
            throw new \RuntimeException($label . ' payload field must be an integer: ' . $key);
        }

        return $value;
    }

    private static function nullableIntValue(array $payload, string $key, string $label): ?int
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }
        if (!is_int($payload[$key])) {
            throw new \RuntimeException($label . ' payload field must be an integer or null: ' . $key);
        }

        return $payload[$key];
    }

    private static function stringValue(array $payload, string $key, ?string $default, string $label): string
    {
        $value = $payload[$key] ?? $default;
        if (!is_string($value)) {
            throw new \RuntimeException($label . ' payload field must be a string: ' . $key);
        }

        return $value;
    }

    private static function nullableStringValue(array $payload, string $key, string $label): ?string
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }
        if (!is_string($payload[$key])) {
            throw new \RuntimeException($label . ' payload field must be a string or null: ' . $key);
        }

        return $payload[$key];
    }

    private static function boolValue(array $payload, string $key, bool $default, string $label): bool
    {
        $value = $payload[$key] ?? $default;
        if (!is_bool($value)) {
            throw new \RuntimeException($label . ' payload field must be a boolean: ' . $key);
        }

        return $value;
    }

    private static function arrayValue(array $payload, string $key, ?array $default, string $label): array
    {
        $value = $payload[$key] ?? $default;
        if (!is_array($value)) {
            throw new \RuntimeException($label . ' payload field must be an array: ' . $key);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function stringListValue(array $payload, string $key, string $label): array
    {
        $values = self::arrayValue($payload, $key, [], $label);
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new \RuntimeException($label . ' payload list must contain only strings: ' . $key);
            }
        }

        return array_values($values);
    }
}
