<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-css-before.css');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-css-after.css');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'css',
    'title' => 'Block style CSS selector diff',
]);
