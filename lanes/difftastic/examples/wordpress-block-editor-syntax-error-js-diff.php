<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-editor-syntax-error-before.js');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-editor-syntax-error-after.js');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'javascript',
    'title' => 'Block editor JavaScript parse fallback diff',
]);
