<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\CountingReader;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
$wxr = $tree['exports/site.wxr'];
$reader = new CountingReader($wxr);

$probe = $reader->read(5);
$uploaded = $probe;
while (($chunk = $reader->read(8)) !== '') {
    $uploaded .= $chunk;
}

return [
    'probe' => $probe,
    'isWxr' => $probe === '<rss ',
    'uploadedBytes' => $reader->bytesRead(),
    'expectedBytes' => strlen($wxr),
    'matchesOriginal' => $uploaded === $wxr,
];
