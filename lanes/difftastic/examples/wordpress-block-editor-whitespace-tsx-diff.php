<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-editor-whitespace-before.tsx');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-editor-whitespace-after.tsx');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'tsx',
    'title' => 'Block editor TSX whitespace diff',
]);
