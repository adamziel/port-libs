<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-readme-before.txt');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-readme-after.txt');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff(
    $before,
    $after,
    [
        'language' => 'text',
        'title' => 'Plugin readme text diff',
    ],
);
