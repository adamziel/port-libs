<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FakeSeeker;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
$wxr = $tree['exports/site.wxr'];
$reader = new FakeSeeker($wxr, strlen($wxr));

$reportedLength = $reader->seek(0, SEEK_END);
$reader->seek(0, SEEK_SET);
$probe = $reader->read(5);

$seekAfterReadError = null;
try {
    $reader->seek(0, SEEK_SET);
} catch (RuntimeException $throwable) {
    $seekAfterReadError = $throwable->getMessage();
}

return [
    'reportedLength' => $reportedLength,
    'probe' => $probe,
    'isWxr' => $probe === '<rss ',
    'seekAfterReadError' => $seekAfterReadError,
];
