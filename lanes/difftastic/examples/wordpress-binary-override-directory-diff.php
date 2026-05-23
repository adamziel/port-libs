<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\DirectoryDiffer;

$fixtures = dirname(__DIR__) . '/fixtures';

echo (new DirectoryDiffer())->renderJsonDirectoryDiff(
    $fixtures . '/wordpress-binary-override-before',
    $fixtures . '/wordpress-binary-override-after',
    [
        'binaryOverrides' => ['*.min.js'],
    ],
);
