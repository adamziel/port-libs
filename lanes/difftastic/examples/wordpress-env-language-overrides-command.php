<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\DiffCommandRunner;

$fixtures = dirname(__DIR__) . '/fixtures';

$result = (new DiffCommandRunner())->runJsonDirectoryDiff(
    $fixtures . '/wordpress-language-override-before',
    $fixtures . '/wordpress-language-override-after',
    [
        'sortPaths' => true,
        'exitCode' => true,
    ],
    [
        'DFT_OVERRIDE' => '*.asset.php:text',
        'DFT_OVERRIDE_1' => '*.blade.php:HTML',
    ],
);

echo $result['stdout'];
