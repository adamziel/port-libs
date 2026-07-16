<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\SideBySideDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-pattern-context-before.php');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-pattern-context-after.php');

echo (new SideBySideDiffRenderer())->renderTextDiff($before, $after, [
    'tabWidth' => 4,
    'columnWidth' => 64,
    'contextLines' => 1,
]);
