<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-heading.has-gradient-text {
  background: linear-gradient(90deg, var(--wp--preset--color--primary), var(--wp--preset--color--secondary));
  background-clip: text;
  color: transparent;
}
CSS;

$supportsCss = <<<'CSS'
@supports (background-clip: text) {
  .wp-block-heading.has-gradient-text {
    background-clip: text;
    color: transparent;
  }
}
CSS;

$actual = [
    'legacy_chrome' => $prefixer->prefixForTargets($css, ['chrome' => 119]),
    'modern_chrome' => $prefixer->prefixForTargets($css, ['chrome' => 120]),
    'legacy_edge' => $prefixer->prefixForTargets($css, ['edge' => 13]),
    'legacy_chrome_supports' => $prefixer->prefixForTargets($supportsCss, ['chrome' => 119]),
    'modern_chrome_supports' => $prefixer->prefixForTargets(
        '@supports ((-webkit-background-clip: text) or (background-clip: text)) { .wp-block-heading.has-gradient-text { -webkit-background-clip: text; background-clip: text; color: transparent; } }',
        ['chrome' => 120]
    ),
    'legacy_edge_supports' => $prefixer->prefixForTargets($supportsCss, ['edge' => 13]),
];

$expected = [
    'legacy_chrome' => '.wp-block-heading.has-gradient-text{background:linear-gradient(90deg,var(--wp--preset--color--primary),var(--wp--preset--color--secondary));-webkit-background-clip:text;background-clip:text;color:#0000}',
    'modern_chrome' => '.wp-block-heading.has-gradient-text{background:linear-gradient(90deg,var(--wp--preset--color--primary),var(--wp--preset--color--secondary));background-clip:text;color:#0000}',
    'legacy_edge' => '.wp-block-heading.has-gradient-text{background:linear-gradient(90deg,var(--wp--preset--color--primary),var(--wp--preset--color--secondary));-ms-background-clip:text;background-clip:text;color:#0000}',
    'legacy_chrome_supports' => '@supports ((-webkit-background-clip:text) or (background-clip:text)){.wp-block-heading.has-gradient-text{-webkit-background-clip:text;background-clip:text;color:#0000}}',
    'modern_chrome_supports' => '@supports (background-clip:text){.wp-block-heading.has-gradient-text{background-clip:text;color:#0000}}',
    'legacy_edge_supports' => '@supports ((-ms-background-clip:text) or (background-clip:text)){.wp-block-heading.has-gradient-text{-ms-background-clip:text;background-clip:text;color:#0000}}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected background-clip prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
