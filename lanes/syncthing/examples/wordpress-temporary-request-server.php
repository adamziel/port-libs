<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\Response;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-request-' . bin2hex(random_bytes(6));
mkdir($root . '/wp-content/uploads/2026', 0777, true);

$name = 'wp-content/uploads/2026/hero.jpg';
$finalBytes = file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-media-upload.bin');
if (!is_string($finalBytes)) {
    throw new RuntimeException('Missing WordPress media fixture');
}
$temporaryBytes = substr($finalBytes, 0, 96) . '-stale-temporary-copy';

$finalPath = $root . '/' . $name;
$temporaryPath = $root . '/' . RequestServer::temporaryName($name);
file_put_contents($finalPath, $finalBytes);
file_put_contents($temporaryPath, $temporaryBytes);

$blocks = (new BlockList())->fromBytes($finalBytes, strlen($finalBytes));
$request = new Request(
    id: 8101,
    folder: 'wordpress-media',
    name: $name,
    offset: $blocks[0]->offset,
    size: $blocks[0]->size,
    hashHex: $blocks[0]->hashHex,
    fromTemporary: true,
    blockNo: 0,
);

$server = new RequestServer('wordpress-media', $root, ['playground-importer']);
$result = $server->serve('playground-importer', $request);
$frame = BepWire::encodeResponseMessage($result->response);
$decoded = BepWire::decodeResponseMessage($frame);

echo json_encode([
    'request' => [
        'name' => $request->name,
        'fromTemporary' => $request->fromTemporary,
        'hashHex' => $request->hashHex,
    ],
    'served' => $result->toArray(),
    'wireResponse' => [
        'messageType' => BepWire::decodeMessageFrame($frame)['type'],
        'code' => $decoded->code,
        'bytes' => strlen($decoded->data),
    ],
    'restoreError' => $decoded->code === Response::CODE_NO_ERROR ? null : $result->reason,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

unlink($temporaryPath);
unlink($finalPath);
rmdir($root . '/wp-content/uploads/2026');
rmdir($root . '/wp-content/uploads');
rmdir($root . '/wp-content');
rmdir($root);
