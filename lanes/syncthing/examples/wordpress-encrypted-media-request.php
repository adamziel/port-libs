<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\IgnoreMatcher;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\Response;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-encrypted-request-' . bin2hex(random_bytes(6));
mkdir($root . '/wp-content/uploads/2026/private', 0777, true);
mkdir($root . '/wp-content/uploads/2026/encrypted', 0777, true);

$blockedName = 'wp-content/uploads/2026/private/export.zip';
$encryptedName = 'wp-content/uploads/2026/encrypted/media.enc';
$encryptedBytes = file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-media-upload.bin');
if (!is_string($encryptedBytes)) {
    throw new RuntimeException('Missing WordPress media fixture');
}
$encryptedBytes = strrev($encryptedBytes);
$staleTemporaryBytes = substr($encryptedBytes, 0, 48) . '-stale-temp';
$encryptedHashToken = bin2hex('receive-encrypted-token');

file_put_contents($root . '/' . $blockedName, 'private export must stay local');
file_put_contents($root . '/' . $encryptedName, $encryptedBytes);
file_put_contents($root . '/' . RequestServer::temporaryName($encryptedName), $staleTemporaryBytes);

$ignoreMatcher = IgnoreMatcher::fromLines(['wp-content/uploads/2026/private/**']);
$server = new RequestServer(
    'wordpress-media',
    $root,
    ['untrusted-backup-peer'],
    receiveEncrypted: true,
    ignoreMatcher: $ignoreMatcher,
);

$blocked = $server->serve('untrusted-backup-peer', new Request(
    id: 9101,
    folder: 'wordpress-media',
    name: $blockedName,
    size: strlen('private export must stay local'),
    hashHex: hash('sha256', 'private export must stay local'),
));

$restored = $server->serve('untrusted-backup-peer', new Request(
    id: 9102,
    folder: 'wordpress-media',
    name: $encryptedName,
    size: strlen($encryptedBytes),
    hashHex: $encryptedHashToken,
    fromTemporary: true,
));

$frame = BepWire::encodeResponseMessage($restored->response);
$decoded = BepWire::decodeResponseMessage($frame);

echo json_encode([
    'ignoredPrivateExport' => [
        'name' => $blockedName,
        'code' => $blocked->response->code,
        'reason' => $blocked->reason,
    ],
    'receiveEncryptedRestore' => [
        'name' => $encryptedName,
        'hashTokenHex' => $encryptedHashToken,
        'served' => $restored->toArray(),
    ],
    'wireResponse' => [
        'messageType' => BepWire::decodeMessageFrame($frame)['type'],
        'code' => $decoded->code,
        'ok' => $decoded->code === Response::CODE_NO_ERROR,
        'bytes' => strlen($decoded->data),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

unlink($root . '/' . RequestServer::temporaryName($encryptedName));
unlink($root . '/' . $encryptedName);
unlink($root . '/' . $blockedName);
rmdir($root . '/wp-content/uploads/2026/encrypted');
rmdir($root . '/wp-content/uploads/2026/private');
rmdir($root . '/wp-content/uploads/2026');
rmdir($root . '/wp-content/uploads');
rmdir($root . '/wp-content');
rmdir($root);
