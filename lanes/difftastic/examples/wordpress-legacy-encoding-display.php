<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-legacy-encoding-before.hex')));
$after = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-legacy-encoding-after.hex')));

echo (new JsonDiffRenderer())->renderFileBytesDiff(
    is_string($before) ? $before : '',
    is_string($after) ? $after : '',
    'wp-content/plugins/acme-blocks/readme.txt',
    'Text',
    ['language' => 'text'],
);
