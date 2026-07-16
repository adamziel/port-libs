<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\TokenDiffer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-render-callback-before.php');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-render-callback-after.php');

$ops = (new TokenDiffer())->diff($before, $after, ['ignoreComments' => true]);

foreach ($ops as $op) {
    if ($op['op'] !== '=') {
        echo $op['op'] . $op['text'] . PHP_EOL;
    }
}
