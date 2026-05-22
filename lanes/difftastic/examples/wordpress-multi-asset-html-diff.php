<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-multi-asset-html-before.html');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-multi-asset-html-after.html');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'html',
    'title' => 'Multi inline asset sub-language diff',
]);
