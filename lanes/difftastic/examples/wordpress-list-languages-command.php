<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\DiffCommandRunner;

$result = (new DiffCommandRunner())->runListLanguages([
    '*.blade.php:HTML',
    '*.asset.php:PHP',
    '*.wp-env.json:JSON',
]);

echo $result['stdout'];
echo 'exit_code=' . $result['exitCode'] . "\n";
