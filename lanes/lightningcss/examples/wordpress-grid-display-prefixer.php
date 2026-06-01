<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-post-template.is-layout-grid {
  display: grid;
}

.wp-block-query-pagination {
  display: -ms-grid;
  display: grid;
}

.wp-block-navigation .wp-block-navigation__submenu {
  display: -ms-inline-grid;
  display: inline-grid;
}
CSS;

$actual = [
    'legacy_editor' => $prefixer->prefixForTargets($css, [
        'ie' => 10,
        'edge' => 15,
    ]),
    'edge_16' => $prefixer->prefixForTargets($css, ['edge' => 16]),
    'modern_frontend' => $prefixer->prefixForTargets($css, ['chrome' => 120]),
];

$expected = [
    'legacy_editor' => '.wp-block-post-template.is-layout-grid,.wp-block-query-pagination{display:-ms-grid;display:grid}.wp-block-navigation .wp-block-navigation__submenu{display:-ms-inline-grid;display:inline-grid}',
    'edge_16' => '.wp-block-post-template.is-layout-grid,.wp-block-query-pagination{display:grid}.wp-block-navigation .wp-block-navigation__submenu{display:inline-grid}',
    'modern_frontend' => '.wp-block-post-template.is-layout-grid,.wp-block-query-pagination{display:grid}.wp-block-navigation .wp-block-navigation__submenu{display:inline-grid}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected grid display prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
