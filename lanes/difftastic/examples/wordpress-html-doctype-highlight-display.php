<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = "<!DOCTYPE html>\n<html><body><main>Legacy landing</main></body></html>\n";
$after = "<!doctype html>\n"
    . "<html><body><main>Modern block landing</main></body></html>\n";

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/themes/acme/templates/front-page.html',
    'HTML',
    ['language' => 'html'],
);
