<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-log-no-eol-before.hex')));
$after = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-log-no-eol-after.hex')));

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/uploads/migration/import.log',
    'Text',
    ['language' => 'text'],
);
