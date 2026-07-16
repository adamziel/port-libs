<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class EncryptionConsistency
{
    public const ERR_INVALID_LOCAL_CONFIG = 'errEncryptionInvConfigLocal';
    public const ERR_INVALID_REMOTE_CONFIG = 'errEncryptionInvConfigRemote';
    public const ERR_NOT_ENCRYPTED_LOCAL = 'errEncryptionNotEncryptedLocal';
    public const ERR_PLAIN_FOR_RECEIVE_ENCRYPTED = 'errEncryptionPlainForReceiveEncrypted';
    public const ERR_PLAIN_FOR_REMOTE_ENCRYPTED = 'errEncryptionPlainForRemoteEncrypted';
    public const ERR_NOT_ENCRYPTED_UNTRUSTED = 'errEncryptionNotEncryptedUntrusted';
    public const ERR_PASSWORD = 'errEncryptionPassword';

    private const ERROR_MESSAGES = [
        self::ERR_INVALID_LOCAL_CONFIG => "can't encrypt outgoing data because local data is encrypted (folder-type receive-encrypted)",
        self::ERR_INVALID_REMOTE_CONFIG => 'remote has encrypted data and encrypts that data for us - this is impossible',
        self::ERR_NOT_ENCRYPTED_LOCAL => 'remote expects to exchange encrypted data, but is configured for plain data',
        self::ERR_PLAIN_FOR_RECEIVE_ENCRYPTED => 'remote expects to exchange plain data, but is configured to be encrypted',
        self::ERR_PLAIN_FOR_REMOTE_ENCRYPTED => 'remote expects to exchange plain data, but local data is encrypted (folder-type receive-encrypted)',
        self::ERR_NOT_ENCRYPTED_UNTRUSTED => 'device is untrusted, but configured to receive plain data',
        self::ERR_PASSWORD => 'different encryption passwords used',
    ];

    public static function checkClusterConfig(
        Folder $localFolder,
        Device $remoteClusterDevice,
        Device $localClusterDevice,
        bool $remoteDeviceEncryptionConfigured,
        bool $deviceUntrusted,
        string $remotePasswordTokenHex = '',
        string $storedFolderTokenHex = '',
        ?string $remoteEncryptionPassword = null,
    ): EncryptionConsistencyDecision {
        self::assertHexBytes($remotePasswordTokenHex, 'remote password token');
        self::assertHexBytes($storedFolderTokenHex, 'stored folder token');

        $hasTokenRemote = $remoteClusterDevice->encryptionPasswordTokenHex !== '';
        $hasTokenLocal = $localClusterDevice->encryptionPasswordTokenHex !== '';
        $isEncryptedRemote = $remoteDeviceEncryptionConfigured;
        $isEncryptedLocal = $localFolder->type === Folder::TYPE_RECEIVE_ENCRYPTED;

        if (!$isEncryptedRemote && !$isEncryptedLocal && $deviceUntrusted) {
            return self::error(self::ERR_NOT_ENCRYPTED_UNTRUSTED);
        }

        if (!($hasTokenRemote || $hasTokenLocal || $isEncryptedRemote || $isEncryptedLocal)) {
            return new EncryptionConsistencyDecision();
        }

        if ($isEncryptedRemote && $isEncryptedLocal) {
            return self::error(self::ERR_INVALID_LOCAL_CONFIG);
        }

        if ($hasTokenRemote && $hasTokenLocal) {
            return self::error(self::ERR_INVALID_REMOTE_CONFIG);
        }

        if (!($hasTokenRemote || $hasTokenLocal)) {
            return self::error($isEncryptedRemote
                ? self::ERR_PLAIN_FOR_REMOTE_ENCRYPTED
                : self::ERR_PLAIN_FOR_RECEIVE_ENCRYPTED);
        }

        if (!($isEncryptedRemote || $isEncryptedLocal)) {
            return self::error(self::ERR_NOT_ENCRYPTED_LOCAL);
        }

        if ($isEncryptedRemote) {
            if ($remotePasswordTokenHex === '') {
                if ($remoteEncryptionPassword === null) {
                    throw new \InvalidArgumentException('Remote encrypted devices require a password token or password for comparison');
                }
                $remotePasswordTokenHex = EncryptionKey::passwordTokenHex($localFolder->id, $remoteEncryptionPassword);
            }

            $clusterTokenHex = $hasTokenLocal
                ? $localClusterDevice->encryptionPasswordTokenHex
                : $remoteClusterDevice->encryptionPasswordTokenHex;

            return hash_equals($clusterTokenHex, $remotePasswordTokenHex)
                ? new EncryptionConsistencyDecision()
                : self::error(self::ERR_PASSWORD);
        }

        $clusterTokenHex = $hasTokenLocal
            ? $localClusterDevice->encryptionPasswordTokenHex
            : $remoteClusterDevice->encryptionPasswordTokenHex;

        if ($storedFolderTokenHex === '') {
            return new EncryptionConsistencyDecision(
                acceptedFolderTokenHex: $clusterTokenHex,
                clusterConfigResendNeeded: true,
            );
        }

        return hash_equals($storedFolderTokenHex, $clusterTokenHex)
            ? new EncryptionConsistencyDecision()
            : self::error(self::ERR_PASSWORD);
    }

    public static function messageFor(string $errorCode): string
    {
        if (!isset(self::ERROR_MESSAGES[$errorCode])) {
            throw new \InvalidArgumentException('Unknown encryption consistency error code');
        }

        return self::ERROR_MESSAGES[$errorCode];
    }

    private static function error(string $errorCode): EncryptionConsistencyDecision
    {
        return new EncryptionConsistencyDecision($errorCode, self::messageFor($errorCode));
    }

    private static function assertHexBytes(string $hex, string $label): void
    {
        if ($hex !== '' && !preg_match('/^(?:[0-9a-f]{2})+$/', $hex)) {
            throw new \InvalidArgumentException('Expected lowercase hexadecimal bytes for ' . $label);
        }
    }
}
