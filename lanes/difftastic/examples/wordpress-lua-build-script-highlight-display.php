<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = <<<'LUA'
local steps = {}

return steps
LUA;

$after = <<<'LUA'
local steps = {}

function register_blocks(blocks)
    for _, block in ipairs(blocks) do
        if block.dynamic == false then
            return nil
        end
    end

    return steps
end
LUA;

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/tools/register-blocks.lua',
    'Lua',
    ['language' => 'lua'],
);
