<?php

declare(strict_types=1);

use PortLibs\Syncthing\ServiceMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$events = [];
$services = new ServiceMap(
    static function (string|int $folder, array $service) use (&$events): void {
        $events[] = 'start:' . $folder . ':' . $service['kind'];
    },
    static function (string|int $folder, array $service) use (&$events): void {
        $events[] = 'stop:' . $folder . ':' . $service['kind'];
    },
);

$services->add('wordpress-media', [
    'kind' => 'media-indexer',
    'path' => 'wp-content/uploads',
]);
$services->add('wordpress-private', [
    'kind' => 'receive-encrypted-indexer',
    'path' => 'wp-content/private-exports',
]);

$services->stop('wordpress-private');
$retainedPrivate = $services->get('wordpress-private');

$services->add('wordpress-media', [
    'kind' => 'media-indexer-rescan',
    'path' => 'wp-content/uploads',
]);
$services->removeAndWait('wordpress-private');

echo json_encode([
    'activeFolders' => $services->runningKeys(),
    'registeredFolders' => $services->keys(),
    'retainedStoppedPrivateService' => $retainedPrivate,
    'events' => $events,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
