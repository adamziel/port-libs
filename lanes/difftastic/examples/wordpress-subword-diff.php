<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\TokenDiffer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-before.php');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-after.php');

$ops = (new TokenDiffer())->diffWords($before, $after, ['splitNumbers' => true]);

foreach ($ops as $op) {
    if ($op['op'] !== '=') {
        echo $op['op'] . $op['text'] . PHP_EOL;
    }
}
