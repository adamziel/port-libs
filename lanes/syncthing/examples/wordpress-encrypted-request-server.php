<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\EncryptionKey;
use PortLibs\Syncthing\ReceiveEncrypted;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\Response;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-encrypted-request-server-' . bin2hex(random_bytes(6));
mkdir($root . '/wp-content/uploads/2026/private', 0777, true);

$fixture = file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-media-upload.bin');
if (!is_string($fixture)) {
    throw new RuntimeException('Missing WordPress media fixture');
}

$name = 'wp-content/uploads/2026/private/editorial-preview.jpg';
$bytes = substr($fixture, 0, 96);
$path = $root . '/' . $name;
file_put_contents($path, $bytes);

$folderKey = EncryptionKey::folderKeyFromPassword('wordpress-media', 'wordpress media sync secret');
$fileKey = ReceiveEncrypted::fileKey($name, $folderKey);
$plainRequest = new Request(
    id: 9701,
    folder: 'wordpress-media',
    name: $name,
    offset: 0,
    size: strlen($bytes),
    hashHex: hash('sha256', $bytes),
    blockNo: 0,
);
$encryptedRequest = BepWire::decodeRequestMessage(
    BepWire::encodeRequestMessage(ReceiveEncrypted::encryptRequestForEncryptedPeer($plainRequest, $folderKey)),
);

$server = new RequestServer('wordpress-media', $root, ['untrusted-media-peer']);
$served = ReceiveEncrypted::serveEncryptedRequestFromPeer(
    $server,
    'untrusted-media-peer',
    $encryptedRequest,
    $folderKey,
    str_repeat('W', ReceiveEncrypted::MIN_PADDED_SIZE),
    str_repeat(chr(14), ReceiveEncrypted::NONCE_SIZE),
);
$wireResponse = BepWire::decodeResponseMessage(BepWire::encodeResponseMessage($served->response));
$trusted = ReceiveEncrypted::decryptResponseFromEncryptedPeer($wireResponse, $fileKey, strlen($bytes));

$wrongHash = ReceiveEncrypted::serveEncryptedRequestFromPeer(
    $server,
    'untrusted-media-peer',
    ReceiveEncrypted::encryptRequestForEncryptedPeer(new Request(
        id: 9702,
        folder: 'wordpress-media',
        name: $name,
        offset: 0,
        size: strlen($bytes),
        hashHex: hash('sha256', 'stale WordPress media bytes'),
        blockNo: 0,
    ), $folderKey),
    $folderKey,
);

echo json_encode([
    'encryptedRequest' => [
        'name' => $encryptedRequest->name,
        'decryptedName' => ReceiveEncrypted::decryptName($encryptedRequest->name, $folderKey),
        'size' => $encryptedRequest->size,
        'encryptedHashTokenBytes' => intdiv(strlen($encryptedRequest->hashHex), 2),
    ],
    'served' => $served->toArray(),
    'wireResponse' => [
        'code' => $wireResponse->code,
        'encryptedBytes' => strlen($wireResponse->data),
        'messageType' => BepWire::decodeMessageFrame(BepWire::encodeResponseMessage($served->response))['type'],
    ],
    'trustedPeer' => [
        'code' => $trusted->code,
        'trimmedBytes' => strlen($trusted->data),
        'matchesOriginal' => $trusted->data === $bytes,
    ],
    'staleHash' => [
        'code' => $wrongHash->response->code,
        'reason' => $wrongHash->reason,
        'encrypted' => $wrongHash->response->code === Response::CODE_NO_ERROR,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

unlink($path);
rmdir($root . '/wp-content/uploads/2026/private');
rmdir($root . '/wp-content/uploads/2026');
rmdir($root . '/wp-content/uploads');
rmdir($root . '/wp-content');
rmdir($root);
