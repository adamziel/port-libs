<?php

declare(strict_types=1);

use PortLibs\Syncthing\EncryptionKey;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\ReceiveEncrypted;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-recvenc-parents-' . bin2hex(random_bytes(6));
$cleanup = static function (string $path) use (&$cleanup): void {
    if (is_link($path) || is_file($path)) {
        unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $cleanup($path . DIRECTORY_SEPARATOR . $entry);
    }
    rmdir($path);
};

mkdir($root, 0777, true);
try {
    $folder = 'wordpress-private-media';
    $folderKey = EncryptionKey::folderKeyFromPassword($folder, 'wordpress media sync secret');
    $encryptedName = ReceiveEncrypted::encryptName('wp-content/uploads/private/cleanup-demo.jpg', $folderKey);
    $parentCreation = ReceiveEncrypted::ensureReceiveEncryptedParentDirectory($root, $encryptedName);

    file_put_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $encryptedName), 'encrypted WordPress media bytes');
    $nonEmptyParent = ReceiveEncrypted::receiveEncryptedScanUpdate(new FileInfo(
        name: $parentCreation['parent'],
        type: FileInfo::TYPE_DIRECTORY,
        localFlags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY,
    ), $root);

    $emptyParent = '7.syncthing-enc/AA';
    mkdir($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $emptyParent), 0777, true);
    $emptyParentResult = ReceiveEncrypted::receiveEncryptedScanUpdate(new FileInfo(
        name: $emptyParent,
        type: FileInfo::TYPE_DIRECTORY,
        localFlags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY,
    ), $root);

    echo json_encode([
        'folder' => $folder,
        'encryptedName' => $encryptedName,
        'syntheticParent' => $parentCreation['parent'],
        'parentCreatedForPull' => $parentCreation['created'],
        'scanAfterPullScheduled' => $parentCreation['scanAfterPull'],
        'nonEmptyParentIndexed' => $nonEmptyParent['file'] instanceof FileInfo,
        'nonEmptyParentRemoved' => $nonEmptyParent['removedEmptyParent'],
        'emptyParentIndexed' => $emptyParentResult['file'] instanceof FileInfo,
        'emptyParentRemoved' => $emptyParentResult['removedEmptyParent'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $cleanup($root);
}
