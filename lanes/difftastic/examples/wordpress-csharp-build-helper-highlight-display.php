<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = <<<'CSHARP'
namespace Acme.Tools;

public class Main {
    public static void Main() {}
}
CSHARP;

$after = <<<'CSHARP'
using System.Collections.Generic;

namespace Acme.Tools;

public sealed class BlockRegistry {
    private readonly string name;
    private readonly bool enabled;

    public BlockRegistry(string name, bool enabled) {
        this.name = name;
        this.enabled = enabled;
    }

    public bool Register(IEnumerable<BlockRegistry> blocks) {
        foreach (BlockRegistry block in blocks) {
            if (block.enabled == false) {
                return false;
            }
        }
        return true;
    }
}
CSHARP;

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/tools/BlockRegistry.cs',
    'C#',
    ['language' => 'csharp'],
);
