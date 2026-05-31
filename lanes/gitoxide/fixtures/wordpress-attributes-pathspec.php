<?php

declare(strict_types=1);

return [
    'attributes' => <<<'ATTRS'
*.php text diff=php
wp-content/cache/** export-ignore
wp-content/uploads/** binary
wp-content/plugins/gutenberg/** merge=union deploy=plugin
wp-content/plugins/gutenberg/build/** -deploy
wp-content/themes/twentytwentyfour/*.json merge=union deploy=theme
ATTRS,
    'paths' => [
        'index.php' => false,
        'wp-content/cache/page.html' => false,
        'wp-content/uploads/logo.png' => false,
        'wp-content/plugins/gutenberg/block.json' => false,
        'wp-content/plugins/gutenberg/build/index.js' => false,
        'wp-content/themes/twentytwentyfour/theme.json' => false,
    ],
    'deploymentPathspecs' => [
        ':(attr:merge=union)wp-content/**',
        ':(attr:-diff)wp-content/uploads/**',
        ':!wp-content/cache/**',
        ':!wp-content/plugins/gutenberg/build/**',
    ],
];
