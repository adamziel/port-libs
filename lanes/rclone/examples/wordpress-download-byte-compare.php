<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ReaderComparison;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

$export = ReaderComparison::checkEqualReaders($tree['exports/site.wxr'], $tree['exports/site.wxr']);
$database = ReaderComparison::checkEqualReaders($tree['database/site.sql'], $tree['database/site.sql']);
$corruptUpload = ReaderComparison::checkEqualReaders(
    $tree['wp-content/uploads/2026/05/hero.jpg'],
    substr($tree['wp-content/uploads/2026/05/hero.jpg'], 0, 5),
);

return [
    'wxrExportEqual' => $export->equal && $export->error === null,
    'databaseDumpEqual' => $database->equal && $database->error === null,
    'corruptUploadEqual' => $corruptUpload->equal,
];
