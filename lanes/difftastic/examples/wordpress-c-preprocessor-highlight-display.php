<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = <<<'C'
int acme_block_flags(void) {
    return 0;
}
C;

$after = <<<'C'
#include <stdint.h>
#define ACME_BLOCK_FLAG_DYNAMIC 1

static uint32_t acme_block_flags(uint8_t enabled) {
    return enabled ? ACME_BLOCK_FLAG_DYNAMIC : 0;
}
C;

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/native/block-support.c',
    'C',
    ['language' => 'c'],
);
