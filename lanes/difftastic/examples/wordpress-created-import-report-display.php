<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-created-import-report-after.txt');

echo (new JsonDiffRenderer())->renderFileDiff(
    '',
    $after,
    'wp-content/uploads/migration/import-report.csv',
    'Text',
    ['language' => 'text'],
);
