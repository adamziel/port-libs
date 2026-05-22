<?php

declare(strict_types=1);

use PortLibs\Syncthing\Device;
use PortLibs\Syncthing\EncryptionConsistency;
use PortLibs\Syncthing\Folder;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$token = hash('sha256', 'wordpress media receive-encrypted token');

$plainPeer = EncryptionConsistency::checkClusterConfig(
    localFolder: new Folder('wordpress-media'),
    remoteClusterDevice: new Device('01'),
    localClusterDevice: new Device('02'),
    remoteDeviceEncryptionConfigured: false,
    deviceUntrusted: true,
);

$receiveEncryptedPeer = EncryptionConsistency::checkClusterConfig(
    localFolder: new Folder('wordpress-media', label: 'WordPress Media', type: Folder::TYPE_RECEIVE_ENCRYPTED),
    remoteClusterDevice: new Device('03', encryptionPasswordTokenHex: $token),
    localClusterDevice: new Device('04'),
    remoteDeviceEncryptionConfigured: false,
    deviceUntrusted: true,
);

$mismatch = EncryptionConsistency::checkClusterConfig(
    localFolder: new Folder('wordpress-media', label: 'WordPress Media', type: Folder::TYPE_RECEIVE_ENCRYPTED),
    remoteClusterDevice: new Device('03', encryptionPasswordTokenHex: $token),
    localClusterDevice: new Device('04'),
    remoteDeviceEncryptionConfigured: false,
    deviceUntrusted: true,
    storedFolderTokenHex: hash('sha256', 'old local token'),
);

echo json_encode([
    'plainUntrustedPeer' => [
        'accepted' => $plainPeer->ok(),
        'error' => $plainPeer->errorCode,
        'message' => $plainPeer->message,
    ],
    'receiveEncryptedPeer' => [
        'accepted' => $receiveEncryptedPeer->ok(),
        'acceptedTokenPrefix' => substr($receiveEncryptedPeer->acceptedFolderTokenHex, 0, 16),
        'resendClusterConfig' => $receiveEncryptedPeer->clusterConfigResendNeeded,
    ],
    'mismatchedToken' => [
        'accepted' => $mismatch->ok(),
        'error' => $mismatch->errorCode,
        'message' => $mismatch->message,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
