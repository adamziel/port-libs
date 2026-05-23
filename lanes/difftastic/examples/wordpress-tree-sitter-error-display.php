<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = "wp.blocks.registerBlockType('acme/card', { title: 'Card' });\n";
$after = "wp.blocks.registerBlockType('acme/card', { title: 'Card' }});\n";

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/index.js',
    'JavaScript',
    ['language' => 'javascript', 'parseErrorLimit' => 1],
);
