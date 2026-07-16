<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\SideBySideDiffRenderer;

$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-created-import-report-after.txt');

echo (new SideBySideDiffRenderer())->renderTextDiff('', $after, [
    'tabWidth' => 4,
    'columnWidth' => 64,
]);
