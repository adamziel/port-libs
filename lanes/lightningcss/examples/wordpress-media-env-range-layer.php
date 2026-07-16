<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;
use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme.blocks {
  @media (max-width: env(--wp--custom--query-card-breakpoint 1, 782px)) {
    .wp-block-query {
      gap: env(--wp--preset--spacing--40, 1rem);
    }
  }

  @media (max-width: env(safe-area-inset-top)) {
    .wp-block-query.is-safe-area {
      padding-top: env(safe-area-inset-top);
    }
  }
}
CSS;

$minifier = new CssMinifier();
$prefixer = new TransitionPrefixer();

$actual = [
    'minified' => $minifier->minify($css),
    'firefox60' => $prefixer->prefixForTargets($css, ['firefox' => 60]),
    'firefox64' => $prefixer->prefixForTargets($css, ['firefox' => 64]),
    'chrome95' => $prefixer->prefixForTargets($css, ['chrome' => 95]),
];

$expected = [
    'minified' => '@layer theme.blocks{@media (width<=env(--wp--custom--query-card-breakpoint 1,782px)){.wp-block-query{gap:env(--wp--preset--spacing--40,1rem)}}@media (width<=env(safe-area-inset-top)){.wp-block-query.is-safe-area{padding-top:env(safe-area-inset-top)}}}',
    'firefox60' => '@layer theme.blocks{@media (max-width:env(--wp--custom--query-card-breakpoint 1,782px)){.wp-block-query{gap:env(--wp--preset--spacing--40,1rem)}}@media (max-width:env(safe-area-inset-top)){.wp-block-query.is-safe-area{padding-top:env(safe-area-inset-top)}}}',
    'firefox64' => '@layer theme.blocks{@media (width<=env(--wp--custom--query-card-breakpoint 1,782px)){.wp-block-query{gap:env(--wp--preset--spacing--40,1rem)}}@media (width<=env(safe-area-inset-top)){.wp-block-query.is-safe-area{padding-top:env(safe-area-inset-top)}}}',
    'chrome95' => '@layer theme.blocks{@media (max-width:env(--wp--custom--query-card-breakpoint 1,782px)){.wp-block-query{gap:env(--wp--preset--spacing--40,1rem)}}@media (max-width:env(safe-area-inset-top)){.wp-block-query.is-safe-area{padding-top:env(safe-area-inset-top)}}}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected media env range layer output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
