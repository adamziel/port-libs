<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\RepeatableReader;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
$reader = new RepeatableReader($tree['exports/site.wxr']);

$probe = $reader->read(5);
$reader->seek(0, SEEK_SET);

$restored = '';
while (($chunk = $reader->read(8)) !== '') {
    $restored .= $chunk;
}

return [
    'probe' => $probe,
    'isWxr' => $probe === '<rss ',
    'restored' => $restored,
    'matchesOriginal' => $restored === $tree['exports/site.wxr'],
    'cachedProbeBytes' => $reader->cacheLength(),
];
