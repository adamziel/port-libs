<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-editor-scss-before.scss');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-editor-scss-after.scss');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'scss',
    'title' => 'Block editor SCSS mixin diff',
]);
