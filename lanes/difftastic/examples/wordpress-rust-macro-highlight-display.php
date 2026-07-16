<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = <<<'RS'
pub fn register_blocks() {
    let blocks = ["acme/card"];
}
RS;

$after = <<<'RS'
pub fn register_blocks() {
    let blocks = vec!["acme/card", "acme/gallery"];
    println!("registered {} native block manifests", blocks.len());
}
RS;

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/native/register_blocks.rs',
    'Rust',
    ['language' => 'rust'],
);
