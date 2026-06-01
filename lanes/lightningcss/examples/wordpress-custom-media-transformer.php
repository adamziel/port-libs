<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;
use PortLibs\LightningCSS\CustomMediaTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@custom-media --wp-mobile (max-width: 599px /* old alias: (--wp-phone), ) */);
@custom-media --wp-motion (prefers-reduced-motion: no-preference /* old alias: (--wp-legacy-motion), */);
@custom-media --wp-wide (min-width: 782px);
@custom-media --wp-theme-token not (min-Theme-Breakpoint: 2);

@import \75rl(./blocks/query-card.css) /* wp block layer, stale layer(old) supports((display:flex)) old alias: (--wp-tablet), ) */ l\61yer(theme.blocks) s\75pports((--wp-wide)) screen and (min-width: 480px /* old floor, ) */) and (--wp-wide), print and (--wp-motion);

@media (min-width: 480px /* old floor, ) */) and /* old alias: (--wp-legacy-mobile), */ (--wp-mobile), print and (--wp-motion) {
  .wp-block-cover.is-style-animated {
    animation-duration: 100ms;
    color: yellow;
  }
}

@layer theme.blocks {
  @media (--wp-theme-token) {
    .wp-block-query {
      color: yellow;
    }
  }
}
CSS;

$transformed = (new CustomMediaTransformer())->transform($css);
$result = (new CssMinifier())->minify($transformed);

$expected = '@import "./blocks/query-card.css" layer(theme.blocks) supports((--wp-wide)) screen and (width>=480px) and (width>=782px),print and (prefers-reduced-motion:no-preference);@media (width>=480px) and (width<=599px),print and (prefers-reduced-motion:no-preference){.wp-block-cover.is-style-animated{animation-duration:.1s;color:#ff0}}@layer theme.blocks{@media not (min-Theme-Breakpoint:2){.wp-block-query{color:#ff0}}}';

if (($argv[1] ?? '') === '--self-test' && $result !== $expected) {
    fwrite(STDERR, "Unexpected WordPress custom media transformer output:\n{$result}\n");
    exit(1);
}

echo $result . PHP_EOL;
