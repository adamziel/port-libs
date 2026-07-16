<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-multiline-copy-before.php');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-multiline-copy-after.php');

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-campaign/render.php',
    'PHP',
);
