<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-array-syntax-before.php');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-array-syntax-after.php');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'title' => 'Block allow-list array syntax diff',
]);
