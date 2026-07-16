<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\DiffCommandRunner;

$fixtures = dirname(__DIR__) . '/fixtures';

$result = (new DiffCommandRunner())->runJsonDirectoryDiff(
    $fixtures . '/wordpress-binary-override-before',
    $fixtures . '/wordpress-binary-override-after',
    [
        'sortPaths' => true,
        'exitCode' => true,
    ],
    [
        'DFT_OVERRIDE_BINARY' => '*.png',
        'DFT_OVERRIDE_BINARY_1' => '*.min.js',
    ],
);

echo $result['stdout'];
