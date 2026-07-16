<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-nearby-hunks-before.txt');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-nearby-hunks-after.txt');

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-review-tools/readme.txt',
    'Text',
    ['language' => 'text'],
);
