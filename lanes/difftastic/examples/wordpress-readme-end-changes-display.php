<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-end-changes-before.txt');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-end-changes-after.txt');

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-end-review/readme.txt',
    'Text',
    ['language' => 'text'],
);
