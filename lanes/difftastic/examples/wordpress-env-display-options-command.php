<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\DiffCommandRunner;

$fixtures = dirname(__DIR__) . '/fixtures';
$before = (string) file_get_contents($fixtures . '/wordpress-tabbed-block-json-before.json');
$after = (string) file_get_contents($fixtures . '/wordpress-tabbed-block-json-after.json');

$result = (new DiffCommandRunner())->runTextDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/block.json',
    'JSON',
    [
        'language' => 'json',
        'exitCode' => true,
    ],
    [
        'DFT_DISPLAY' => 'side-by-side-show-both',
        'DFT_CONTEXT' => '0',
        'DFT_TAB_WIDTH' => '2',
        'DFT_WIDTH' => '44',
    ],
);

echo $result['stdout'];
echo 'exit_code=' . $result['exitCode'] . "\n";
