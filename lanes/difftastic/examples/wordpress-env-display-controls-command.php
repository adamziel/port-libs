<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\DiffCommandRunner;

$fixtures = dirname(__DIR__) . '/fixtures';
$runner = new DiffCommandRunner();

$display = $runner->runTextDiff(
    "render_label('legacy-card');\n",
    "render_label('modern-card');\n",
    'wp-content/plugins/acme-card/src/render.php',
    'PHP',
    [
        'language' => 'php',
    ],
    [
        'DFT_DISPLAY' => 'side-by-side',
        'DFT_CONTEXT' => '0',
        'DFT_COLOR' => 'always',
        'DFT_BACKGROUND' => 'dark',
        'DFT_SYNTAX_HIGHLIGHT' => 'off',
    ],
);

$directory = $runner->runJsonDirectoryDiff(
    $fixtures . '/wordpress-language-override-before',
    $fixtures . '/wordpress-language-override-after',
    [],
    [
        'DFT_SORT_PATHS' => 'true',
        'DFT_OVERRIDE' => '*.asset.php:text',
        'DFT_OVERRIDE_1' => '*.blade.php:HTML',
    ],
);

echo $display['stdout'];
echo $directory['stdout'] . "\n";
