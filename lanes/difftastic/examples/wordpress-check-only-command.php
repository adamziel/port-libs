<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\DiffCommandRunner;

$fixtures = dirname(__DIR__) . '/fixtures';
$before = (string) file_get_contents($fixtures . '/wordpress-block-json-before.json');
$after = (string) file_get_contents($fixtures . '/wordpress-block-json-after.json');

$result = (new DiffCommandRunner())->runCheckOnly(
    $before,
    $after,
    'wp-content/plugins/acme-card/block.json',
    'JSON',
    [
        'language' => 'json',
        'exitCode' => true,
        'extraInfo' => 'WordPress plugin metadata gate',
    ],
);

echo $result['stdout'];
echo 'exit_code=' . $result['exitCode'] . "\n";
