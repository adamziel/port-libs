<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-module-imports-before.ts');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-module-imports-after.ts');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'typescript',
    'title' => 'Block module import/export diff',
]);
