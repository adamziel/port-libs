<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Syncthing\RequestServer;

$uploadPaths = [
    'wp-content/uploads/2026/05/hero.jpg',
    'wp-content/uploads/2026/05/.syncthing.hero.jpg.tmp',
    'wp-content/uploads/2026/05/~syncthing~header.png.tmp',
    'wp-content/uploads/2026/05/gallery.jpg',
];

$publishableMedia = array_values(array_filter(
    $uploadPaths,
    static fn (string $path): bool => !RequestServer::isTemporaryName($path) && !RequestServer::isInternalName($path),
));

print_r($publishableMedia);
