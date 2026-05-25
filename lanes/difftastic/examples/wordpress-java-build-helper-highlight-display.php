<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = <<<'JAVA'
package tools;

public class Main {
    public static void main(String[] args) {}
}
JAVA;

$after = <<<'JAVA'
package tools;

public final class BlockRegistry {
    private final String name;
    private final boolean dynamic;

    public BlockRegistry(String name, boolean dynamic) {
        this.name = name;
        this.dynamic = dynamic;
    }

    public boolean register(BlockRegistry[] blocks) {
        for (BlockRegistry block : blocks) {
            if (block.dynamic == false) {
                return false;
            }
        }
        return true;
    }
}
JAVA;

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/tools/BlockRegistry.java',
    'Java',
    ['language' => 'java'],
);
