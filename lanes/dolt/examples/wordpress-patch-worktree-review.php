<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\PatchFunctionCall;

$fixture = require dirname(__DIR__) . '/fixtures/wp-patch-worktree-review.php';
$call = new PatchFunctionCall();
$options = $fixture['options'];

return [
    'stagedPostPatch' => $call->rows([], ['HEAD', 'STAGED', 'wp_posts'], $options),
    'worktreePostPatch' => $call->rows([], ['STAGED', 'WORKING', 'wp_posts'], $options),
    'reverseWorktreePatch' => $call->rows([], ['WORKING', 'STAGED', 'wp_posts'], $options),
    'sameWorkingPatch' => $call->rows([], ['WORKING', 'WORKING', 'wp_posts'], $options),
];
