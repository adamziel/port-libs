<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-loop-migration-before.py');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-loop-migration-after.py');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'python',
    'title' => 'WordPress Python migration loop diff',
]);
