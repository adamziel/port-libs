<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-json-before.json');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-json-after.json');

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/block.json',
    'JSON',
    ['language' => 'json'],
);
