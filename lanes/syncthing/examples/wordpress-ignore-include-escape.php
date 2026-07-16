<?php

declare(strict_types=1);

use PortLibs\Syncthing\IgnoreMatcher;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\Response;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-ignore-' . bin2hex(random_bytes(6));
mkdir($root . '/wp-content/uploads/2026/private', 0777, true);
mkdir($root . '/wp-content/uploads/2026/literal', 0777, true);
mkdir($root . '/wp-content/uploads/2026/public', 0777, true);

$privateName = 'wp-content/uploads/2026/private/export.zip';
$literalName = 'wp-content/uploads/2026/literal/*cache*.zip';
$publicName = 'wp-content/uploads/2026/public/hero.jpg';

file_put_contents($root . '/.stignore', "#include wp-private.ignore\n#escape=|\nwp-content/uploads/2026/literal/|*cache|*.zip\n");
file_put_contents($root . '/wp-private.ignore', "(?d)(?i)wp-content/uploads/2026/private/**\n");
file_put_contents($root . '/' . $privateName, 'private export must stay local');
file_put_contents($root . '/' . $literalName, 'literal glob cache must stay local');
file_put_contents($root . '/' . $publicName, 'public media bytes');

$server = new RequestServer(
    'wordpress-media',
    $root,
    ['playground-peer'],
    ignoreMatcher: IgnoreMatcher::fromFile($root . '/.stignore'),
);

$private = $server->serve('playground-peer', new Request(
    id: 9301,
    folder: 'wordpress-media',
    name: $privateName,
    size: strlen('private export must stay local'),
    hashHex: hash('sha256', 'private export must stay local'),
));
$literal = $server->serve('playground-peer', new Request(
    id: 9302,
    folder: 'wordpress-media',
    name: $literalName,
    size: strlen('literal glob cache must stay local'),
    hashHex: hash('sha256', 'literal glob cache must stay local'),
));
$public = $server->serve('playground-peer', new Request(
    id: 9303,
    folder: 'wordpress-media',
    name: $publicName,
    size: strlen('public media bytes'),
    hashHex: hash('sha256', 'public media bytes'),
));

echo json_encode([
    'includedPrivateExportRule' => [
        'name' => $privateName,
        'code' => $private->response->code,
        'blocked' => $private->response->code === Response::CODE_INVALID_FILE,
        'reason' => $private->reason,
    ],
    'escapedLiteralGlobRule' => [
        'name' => $literalName,
        'code' => $literal->response->code,
        'blocked' => $literal->response->code === Response::CODE_INVALID_FILE,
        'reason' => $literal->reason,
    ],
    'publicMediaRequest' => [
        'name' => $publicName,
        'served' => $public->toArray(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

unlink($root . '/' . $publicName);
unlink($root . '/' . $literalName);
unlink($root . '/' . $privateName);
unlink($root . '/wp-private.ignore');
unlink($root . '/.stignore');
rmdir($root . '/wp-content/uploads/2026/public');
rmdir($root . '/wp-content/uploads/2026/literal');
rmdir($root . '/wp-content/uploads/2026/private');
rmdir($root . '/wp-content/uploads/2026');
rmdir($root . '/wp-content/uploads');
rmdir($root . '/wp-content');
rmdir($root);
