<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class EncryptedDownloadProgress
{
    /** @var array<string, string> */
    private array $folderKeys = [];

    /**
     * @param array<string, string> $folderKeys
     */
    public function __construct(array $folderKeys)
    {
        foreach ($folderKeys as $folder => $key) {
            if (!is_string($folder) || $folder === '') {
                throw new \InvalidArgumentException('Encrypted folder IDs must be non-empty strings');
            }
            if (!is_string($key) || strlen($key) !== EncryptionKey::KEY_SIZE) {
                throw new \LengthException('Encrypted folder keys must be 32 bytes');
            }

            $this->folderKeys[$folder] = $key;
        }
    }

    /**
     * @param array<string, string> $passwords
     */
    public static function fromPasswords(array $passwords): self
    {
        $folderKeys = [];
        foreach ($passwords as $folder => $password) {
            if (!is_string($folder) || $folder === '') {
                throw new \InvalidArgumentException('Encrypted folder IDs must be non-empty strings');
            }
            if (!is_string($password)) {
                throw new \InvalidArgumentException('Encrypted folder passwords must be strings');
            }

            $folderKeys[$folder] = EncryptionKey::folderKeyFromPassword($folder, $password);
        }

        return new self($folderKeys);
    }

    public function hasFolderKey(string $folder): bool
    {
        return isset($this->folderKeys[$folder]);
    }

    public function outgoingToEncryptedPeer(DownloadProgress $progress): ?DownloadProgress
    {
        return $this->hasFolderKey($progress->folder) ? null : $progress;
    }

    public function incomingFromEncryptedPeer(DownloadProgress $progress): ?DownloadProgress
    {
        return $this->hasFolderKey($progress->folder) ? null : $progress;
    }

    public function sendOutgoing(ProgressConnection $connection, DownloadProgress $progress): bool
    {
        $forwarded = $this->outgoingToEncryptedPeer($progress);
        if ($forwarded === null) {
            return false;
        }

        $connection->sendDownloadProgress($forwarded);

        return true;
    }

    /**
     * @return array{device:string, folder:string, state:array<string, int>}|null
     */
    public function receiveIncoming(
        RemoteDownloadProgressTracker $tracker,
        string $deviceId,
        DownloadProgress $progress,
    ): ?array {
        $forwarded = $this->incomingFromEncryptedPeer($progress);
        if ($forwarded === null) {
            return null;
        }

        return $tracker->receiveDownloadProgress($deviceId, $forwarded);
    }
}
