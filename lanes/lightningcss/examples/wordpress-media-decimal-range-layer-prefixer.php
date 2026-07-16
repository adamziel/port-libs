<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme.blocks {
  @media (width >= 0.5px) {
    .wp-block-query.is-hairline-breakpoint {
      color: yellow;
    }
  }

  @media (0.5px <= width <= 1.50px) {
    .wp-block-query.is-hairline-window {
      color: yellow;
    }
  }

  @media (theme-breakpoint >= +0.5rem) {
    .wp-block-query.is-token-breakpoint {
      color: yellow;
    }
  }
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'legacy' => $prefixer->prefixForTargets($css, ['firefox' => 60]),
    'modern' => $prefixer->prefixForTargets($css, ['firefox' => 64]),
    'forcedRangeFallback' => $prefixer->prefixForTargets($css, [
        'include' => ['MediaRangeSyntax', 'MediaIntervalSyntax'],
    ]),
];

$expected = [
    'legacy' => '@layer theme.blocks{@media (min-width:.5px){.wp-block-query.is-hairline-breakpoint{color:#ff0}}@media (min-width:.5px) and (max-width:1.5px){.wp-block-query.is-hairline-window{color:#ff0}}@media (min-theme-breakpoint:.5rem){.wp-block-query.is-token-breakpoint{color:#ff0}}}',
    'modern' => '@layer theme.blocks{@media (width>=.5px){.wp-block-query.is-hairline-breakpoint{color:#ff0}}@media (min-width:.5px) and (max-width:1.5px){.wp-block-query.is-hairline-window{color:#ff0}}@media (theme-breakpoint>=.5rem){.wp-block-query.is-token-breakpoint{color:#ff0}}}',
    'forcedRangeFallback' => '@layer theme.blocks{@media (min-width:.5px){.wp-block-query.is-hairline-breakpoint{color:#ff0}}@media (min-width:.5px) and (max-width:1.5px){.wp-block-query.is-hairline-window{color:#ff0}}@media (min-theme-breakpoint:.5rem){.wp-block-query.is-token-breakpoint{color:#ff0}}}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected decimal media range layer output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
