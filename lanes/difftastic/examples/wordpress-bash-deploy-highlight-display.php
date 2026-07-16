<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = <<<'SH'
#!/usr/bin/env bash
set -e

wp plugin list
SH;

$after = <<<'SH'
#!/usr/bin/env bash
set -e

export WP_ENV=development

if wp plugin is-installed acme-card --path=wp; then
    wp plugin activate acme-card && wp cache flush
else
    wp plugin install acme-card --activate
fi
SH;

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/bin/deploy.sh',
    'Bash',
    ['language' => 'bash'],
);
