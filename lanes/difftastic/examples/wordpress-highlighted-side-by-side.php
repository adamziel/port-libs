<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\SideBySideDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-footer-before.txt');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-footer-after.txt');

echo (new SideBySideDiffRenderer())->renderTextDiff($before, $after, [
    'columnWidth' => 56,
    'contextLines' => 1,
    'useColor' => true,
]);
