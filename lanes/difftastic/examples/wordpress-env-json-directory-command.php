<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\DiffCommandRunner;

$fixtures = dirname(__DIR__) . '/fixtures';

$result = (new DiffCommandRunner())->runJsonDirectoryDiff(
    $fixtures . '/wordpress-directory-before',
    $fixtures . '/wordpress-directory-after',
    [
        'sortPaths' => true,
    ],
    [
        'DFT_DISPLAY' => 'json',
        'DFT_UNSTABLE' => 'yes',
    ],
);

echo $result['stdout'];
