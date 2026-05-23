<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\DirectoryDiffer;

$fixtures = dirname(__DIR__) . '/fixtures';
$files = (new DirectoryDiffer())->diffDirectories(
    $fixtures . '/wordpress-language-override-before',
    $fixtures . '/wordpress-language-override-after',
    [
        'sortPaths' => true,
        'languageOverrides' => [
            '*.asset.php:text',
            '*.blade.php:HTML',
        ],
    ],
);

echo json_encode($files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
echo "\n";
