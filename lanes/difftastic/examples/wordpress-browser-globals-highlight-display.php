<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$fixtures = dirname(__DIR__) . '/fixtures';
$before = (string) file_get_contents($fixtures . '/wordpress-browser-globals-before.js');
$after = (string) file_get_contents($fixtures . '/wordpress-browser-globals-after.js');

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/src/browser-globals.js',
    'JavaScript',
    ['language' => 'javascript'],
);
