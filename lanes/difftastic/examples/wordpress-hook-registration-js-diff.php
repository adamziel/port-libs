<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-hook-registration-before.js');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-hook-registration-after.js');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'javascript',
    'title' => 'WordPress hook registration JavaScript diff',
]);
