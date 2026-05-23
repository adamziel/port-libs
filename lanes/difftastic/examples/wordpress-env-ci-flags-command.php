<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\DiffCommandRunner;

$fixtures = dirname(__DIR__) . '/fixtures';
$before = (string) file_get_contents($fixtures . '/wordpress-render-callback-before.php');
$after = (string) file_get_contents($fixtures . '/wordpress-render-callback-after.php');

$result = (new DiffCommandRunner())->runTextDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/src/render.php',
    'PHP',
    [
        'language' => 'php',
    ],
    [
        'DFT_CHECK_ONLY' => 'true',
        'DFT_EXIT_CODE' => 'true',
        'DFT_IGNORE_COMMENTS' => 'true',
        'DFT_SKIP_UNCHANGED' => 'false',
    ],
);

echo $result['stdout'];
echo 'exit_code=' . $result['exitCode'] . "\n";
