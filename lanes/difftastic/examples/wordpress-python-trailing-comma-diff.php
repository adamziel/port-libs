<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-trailing-comma-before.py');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-trailing-comma-after.py');

echo (new HtmlDiffRenderer())->renderTokenDiff($before, $after, [
    'language' => 'python',
    'title' => 'WordPress Python trailing comma diff',
]);
