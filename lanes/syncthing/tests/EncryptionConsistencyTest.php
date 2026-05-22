<?php

declare(strict_types=1);

use PortLibs\Syncthing\Device;
use PortLibs\Syncthing\EncryptionConsistency;
use PortLibs\Syncthing\Folder;

return [
    'maps upstream ccCheckEncryption table decisions' => static function (TestRunner $t): void {
        $token = hash('sha256', 'wordpress media encryption password');
        $wrongToken = hash('sha256', 'different wordpress media encryption password');
        $cases = [
            'remote and local tokens' => [true, true, false, false, EncryptionConsistency::ERR_INVALID_REMOTE_CONFIG],
            'remote and local encryption enabled' => [false, false, true, true, EncryptionConsistency::ERR_INVALID_LOCAL_CONFIG],
            'remote token only plain config' => [true, false, false, false, EncryptionConsistency::ERR_NOT_ENCRYPTED_LOCAL],
            'remote token remote encrypted' => [true, false, true, false, null],
            'remote token local receive-encrypted' => [true, false, false, true, null],
            'local token remote encrypted' => [false, true, true, false, null],
            'local token local receive-encrypted' => [false, true, false, true, null],
            'local token only plain config' => [false, true, false, false, EncryptionConsistency::ERR_NOT_ENCRYPTED_LOCAL],
            'remote encrypted without token' => [false, false, true, false, EncryptionConsistency::ERR_PLAIN_FOR_REMOTE_ENCRYPTED],
            'receive-encrypted without token' => [false, false, false, true, EncryptionConsistency::ERR_PLAIN_FOR_RECEIVE_ENCRYPTED],
            'plain config without token' => [false, false, false, false, null],
        ];

        foreach ($cases as $name => [$hasRemoteToken, $hasLocalToken, $isEncryptedRemote, $isEncryptedLocal, $expectedError]) {
            $folder = new Folder(
                id: 'wordpress-media',
                type: $isEncryptedLocal ? Folder::TYPE_RECEIVE_ENCRYPTED : Folder::TYPE_SEND_RECEIVE,
            );
            $remoteDevice = new Device('01', encryptionPasswordTokenHex: $hasRemoteToken ? $token : '');
            $localDevice = new Device('02', encryptionPasswordTokenHex: $hasLocalToken ? $token : '');

            $decision = EncryptionConsistency::checkClusterConfig(
                localFolder: $folder,
                remoteClusterDevice: $remoteDevice,
                localClusterDevice: $localDevice,
                remoteDeviceEncryptionConfigured: $isEncryptedRemote,
                deviceUntrusted: false,
                remotePasswordTokenHex: $token,
                storedFolderTokenHex: $isEncryptedLocal ? $token : '',
            );

            $t->same($expectedError, $decision->errorCode, $name);
            $t->same($expectedError === null, $decision->ok(), $name);

            if ($expectedError === null) {
                $untrusted = EncryptionConsistency::checkClusterConfig(
                    localFolder: $folder,
                    remoteClusterDevice: $remoteDevice,
                    localClusterDevice: $localDevice,
                    remoteDeviceEncryptionConfigured: $isEncryptedRemote,
                    deviceUntrusted: true,
                    remotePasswordTokenHex: $token,
                    storedFolderTokenHex: $isEncryptedLocal ? $token : '',
                );
                $t->same(
                    $isEncryptedRemote || $isEncryptedLocal ? null : EncryptionConsistency::ERR_NOT_ENCRYPTED_UNTRUSTED,
                    $untrusted->errorCode,
                    $name . ' untrusted branch',
                );
            }

            if ($expectedError === null && ($isEncryptedRemote || $isEncryptedLocal)) {
                $mismatch = EncryptionConsistency::checkClusterConfig(
                    localFolder: $folder,
                    remoteClusterDevice: $remoteDevice,
                    localClusterDevice: $localDevice,
                    remoteDeviceEncryptionConfigured: $isEncryptedRemote,
                    deviceUntrusted: false,
                    remotePasswordTokenHex: $isEncryptedRemote ? $wrongToken : $token,
                    storedFolderTokenHex: $isEncryptedLocal ? $wrongToken : '',
                );
                $t->same(EncryptionConsistency::ERR_PASSWORD, $mismatch->errorCode, $name . ' password mismatch');
            }
        }
    },
    'adopts a receive-encrypted cluster token before resending cluster config' => static function (TestRunner $t): void {
        $token = hash('sha256', 'remote wordpress media token');
        $folder = new Folder('wordpress-media', type: Folder::TYPE_RECEIVE_ENCRYPTED);
        $remoteDevice = new Device('aa', encryptionPasswordTokenHex: $token);
        $localDevice = new Device('bb');

        $first = EncryptionConsistency::checkClusterConfig(
            localFolder: $folder,
            remoteClusterDevice: $remoteDevice,
            localClusterDevice: $localDevice,
            remoteDeviceEncryptionConfigured: false,
            deviceUntrusted: true,
        );

        $t->true($first->ok());
        $t->same($token, $first->acceptedFolderTokenHex);
        $t->true($first->clusterConfigResendNeeded);

        $again = EncryptionConsistency::checkClusterConfig(
            localFolder: $folder,
            remoteClusterDevice: $remoteDevice,
            localClusterDevice: $localDevice,
            remoteDeviceEncryptionConfigured: false,
            deviceUntrusted: true,
            storedFolderTokenHex: $first->acceptedFolderTokenHex,
        );

        $t->true($again->ok());
        $t->same('', $again->acceptedFolderTokenHex);
        $t->true(!$again->clusterConfigResendNeeded);
    },
    'preserves upstream encryption error messages for WordPress peer setup' => static function (TestRunner $t): void {
        $plain = EncryptionConsistency::checkClusterConfig(
            localFolder: new Folder('wordpress-media'),
            remoteClusterDevice: new Device('01'),
            localClusterDevice: new Device('02'),
            remoteDeviceEncryptionConfigured: false,
            deviceUntrusted: true,
        );
        $t->same(EncryptionConsistency::ERR_NOT_ENCRYPTED_UNTRUSTED, $plain->errorCode);
        $t->same('device is untrusted, but configured to receive plain data', $plain->message);

        $remotePlain = EncryptionConsistency::checkClusterConfig(
            localFolder: new Folder('wordpress-media', type: Folder::TYPE_RECEIVE_ENCRYPTED),
            remoteClusterDevice: new Device('03'),
            localClusterDevice: new Device('04'),
            remoteDeviceEncryptionConfigured: false,
            deviceUntrusted: false,
        );
        $t->same(EncryptionConsistency::ERR_PLAIN_FOR_RECEIVE_ENCRYPTED, $remotePlain->errorCode);
        $t->same(
            'remote expects to exchange plain data, but is configured to be encrypted',
            EncryptionConsistency::messageFor($remotePlain->errorCode),
        );

        $t->throws(InvalidArgumentException::class, static fn () => EncryptionConsistency::messageFor('not-upstream'));
    },
];
