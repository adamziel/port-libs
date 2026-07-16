<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-import-attributes-before.ts');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-import-attributes-after.ts');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'typescript',
    'title' => 'Block import attribute diff',
]);
