<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = <<<'SQL'
-- Schema migration starts empty.
SQL;

$after = <<<'SQL'
CREATE TABLE wp_acme_cards (
    id BIGINT NOT NULL,
    slug VARCHAR(191) DEFAULT '',
    visible BOOLEAN DEFAULT true,
    PRIMARY KEY (id)
);

SELECT slug FROM wp_acme_cards WHERE visible = true;
SQL;

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/schema/install.sql',
    'SQL',
    ['language' => 'sql'],
);
