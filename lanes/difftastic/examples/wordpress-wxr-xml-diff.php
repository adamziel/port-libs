<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-wxr-postmeta-before.xml');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-wxr-postmeta-after.xml');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'xml',
    'title' => 'WXR postmeta XML tag diff',
]);
