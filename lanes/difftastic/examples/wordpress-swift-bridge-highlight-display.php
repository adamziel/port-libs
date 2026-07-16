<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = <<<'SWIFT'
import Foundation

func main() {}
SWIFT;

$after = <<<'SWIFT'
import Foundation

struct Block {
    let name: String
    let isDynamic: Bool
}

func register(_ blocks: [Block]) -> Bool {
    for block in blocks {
        if block.isDynamic == false {
            return false
        }
    }
    return true
}
SWIFT;

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/tools/BlockBridge.swift',
    'Swift',
    ['language' => 'swift'],
);
