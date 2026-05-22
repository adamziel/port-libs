<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\ReOpenReader;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
$provider = new MemoryProvider();

$provider->put('exports/site.wxr', $tree['exports/site.wxr'], [
    'readError' => 'temporary object stream interruption',
    'readBreaks' => [5, 7],
]);

$reader = new ReOpenReader($provider, 'exports/site.wxr', 10);
$restored = '';

while (!$reader->eof()) {
    $chunk = $reader->read(8);
    if ($chunk === '') {
        break;
    }
    $restored .= $chunk;
}

return [
    'restored' => $restored,
    'matchesOriginal' => $restored === $tree['exports/site.wxr'],
    'reopenOffsets' => array_column($provider->openLog(), 'offset'),
];
