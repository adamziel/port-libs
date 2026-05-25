<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = <<<'GO'
package main

func main() {}
GO;

$after = <<<'GO'
package main

type Block struct {
    Name string
    Dynamic bool
}

func register(blocks []Block) error {
    for _, block := range blocks {
        if block.Dynamic == false || block.Name == "" {
            return nil
        }
    }
    return nil
}
GO;

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/tools/register-blocks.go',
    'Go',
    ['language' => 'go'],
);
