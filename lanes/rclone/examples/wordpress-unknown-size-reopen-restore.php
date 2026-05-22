<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\ReOpenReader;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
$provider = new MemoryProvider();

$provider->put('exports/site.wxr', $tree['exports/site.wxr'], [
    'unknownSize' => true,
    'readError' => 'temporary unknown-length object interruption',
    'readBreaks' => [5, 7],
]);

$reader = new ReOpenReader($provider, 'exports/site.wxr', 10, [
    'rangeStart' => 0,
    'rangeEnd' => -1,
]);

$restored = '';
while (!$reader->eof()) {
    $chunk = $reader->read(8);
    if ($chunk === '') {
        break;
    }
    $restored .= $chunk;
}

return [
    'reportedSize' => $provider->info('exports/site.wxr')->size,
    'restored' => $restored,
    'matchesOriginal' => $restored === $tree['exports/site.wxr'],
    'reopenOffsets' => array_column($provider->openLog(), 'offset'),
    'boundedLengths' => array_column($provider->openLog(), 'length'),
];
