<?php

declare(strict_types=1);

use PortLibs\Syncthing\ProtocolValidation;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\Response;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-request-size-' . bin2hex(random_bytes(6));
$mediaDir = $root . '/wp-content/uploads/2026';
mkdir($mediaDir, 0777, true);

$name = 'wp-content/uploads/2026/hero.jpg';
$bytes = 'wordpress media block';
file_put_contents($root . '/' . $name, $bytes);

$server = new RequestServer('wordpress-media', $root, ['playground-peer']);

$zeroLength = $server->serve('playground-peer', new Request(
    id: 9401,
    folder: 'wordpress-media',
    name: $name,
    size: 0,
));
$tooLarge = $server->serve('playground-peer', new Request(
    id: 9402,
    folder: 'wordpress-media',
    name: $name,
    size: ProtocolValidation::MAX_REQUEST_SIZE + 1,
));
$maximumAccepted = $server->serve('playground-peer', new Request(
    id: 9403,
    folder: 'wordpress-media',
    name: $name,
    size: ProtocolValidation::MAX_REQUEST_SIZE,
));

echo json_encode([
    'zeroLength' => [
        'code' => $zeroLength->response->code,
        'blocked' => $zeroLength->response->code === Response::CODE_INVALID_FILE,
        'reason' => $zeroLength->reason,
    ],
    'tooLarge' => [
        'code' => $tooLarge->response->code,
        'blocked' => $tooLarge->response->code === Response::CODE_INVALID_FILE,
        'reason' => $tooLarge->reason,
    ],
    'maximumAccepted' => [
        'code' => $maximumAccepted->response->code,
        'bytes' => strlen($maximumAccepted->response->data),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

unlink($root . '/' . $name);
rmdir($mediaDir);
rmdir($root . '/wp-content/uploads');
rmdir($root . '/wp-content');
rmdir($root);
