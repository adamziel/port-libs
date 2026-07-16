<?php

declare(strict_types=1);

use PortLibs\Syncthing\Device;
use PortLibs\Syncthing\EncryptionConsistency;
use PortLibs\Syncthing\EncryptionKey;
use PortLibs\Syncthing\Folder;
use PortLibs\Syncthing\ReceiveEncrypted;

return [
    'matches upstream scrypt and AES SIV key derivation fixtures' => static function (TestRunner $t): void {
        $key = EncryptionKey::folderKeyFromPassword('my folder', 'my password');

        $t->same(32, strlen($key));
        $t->same(
            '1f4a929e449614207dd273b669f350065d996fe830a521d0f5c3ce5d',
            bin2hex(EncryptionKey::encryptDeterministic('filename.txt', $key)),
            'upstream TestKeyDerivation encrypted filename token bytes',
        );
        $t->same(
            '2aa51051f5d6fa60e99f0660cff9e34d4ce1f29f091ca4ead27327216fc44094cd5d',
            EncryptionKey::passwordTokenHex('my folder', 'my password'),
            'upstream PasswordToken for the same folder/password',
        );

        $token = ReceiveEncrypted::passwordTokenHex('my folder', 'my password');
        $remoteEncrypted = EncryptionConsistency::checkClusterConfig(
            localFolder: new Folder('my folder'),
            remoteClusterDevice: new Device('aa', encryptionPasswordTokenHex: $token),
            localClusterDevice: new Device('bb'),
            remoteDeviceEncryptionConfigured: true,
            deviceUntrusted: false,
            remoteEncryptionPassword: 'my password',
        );
        $t->true($remoteEncrypted->ok());
        $t->same('', $remoteEncrypted->acceptedFolderTokenHex);
    },
    'uses password tokens for receive encrypted WordPress peer consistency' => static function (TestRunner $t): void {
        $token = ReceiveEncrypted::passwordTokenHex('my folder', 'my password');
        $decision = EncryptionConsistency::checkClusterConfig(
            localFolder: new Folder('my folder', type: Folder::TYPE_RECEIVE_ENCRYPTED),
            remoteClusterDevice: new Device('aa', encryptionPasswordTokenHex: $token),
            localClusterDevice: new Device('bb'),
            remoteDeviceEncryptionConfigured: false,
            deviceUntrusted: true,
        );

        $t->true($decision->ok());
        $t->same($token, $decision->acceptedFolderTokenHex);
        $t->true($decision->clusterConfigResendNeeded);

        $wrongToken = '00' . substr($token, 2);
        $mismatch = EncryptionConsistency::checkClusterConfig(
            localFolder: new Folder('my folder', type: Folder::TYPE_RECEIVE_ENCRYPTED),
            remoteClusterDevice: new Device('aa', encryptionPasswordTokenHex: $token),
            localClusterDevice: new Device('bb'),
            remoteDeviceEncryptionConfigured: false,
            deviceUntrusted: true,
            storedFolderTokenHex: $wrongToken,
        );

        $t->same(EncryptionConsistency::ERR_PASSWORD, $mismatch->errorCode);
    },
];
