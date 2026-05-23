<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\DiffCommandRunner;

$before = "wp.blocks.registerBlockType('acme/card', { title: 'Card' });\n";
$after = "wp.blocks.registerBlockType('acme/card', { title: 'Card' }});\n";
$runner = new DiffCommandRunner();

$result = $runner->runTextDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/index.js',
    'JavaScript',
    ['language' => 'javascript', 'parseErrorLimit' => 1],
    [
        'DFT_COLOR' => 'always',
        'DFT_DISPLAY' => 'inline',
        'DFT_CONTEXT' => '0',
        'DFT_SYNTAX_HIGHLIGHT' => 'on',
    ],
);

echo $result['stdout'];
