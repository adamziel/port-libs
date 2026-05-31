<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme.blocks {
  @media not (width = 782px) {
    .wp-block-query.is-not-editor-width {
      color: yellow;
    }
  }

  @media not (--wp-breakpoint = env(--wp-breakpoint)) {
    .wp-block-query.is-not-env-breakpoint {
      color: chartreuse;
    }
  }
}
CSS;

$actual = (new CssMinifier())->minify($css);
$expected = '@layer theme.blocks{@media (width=782px){.wp-block-query.is-not-editor-width{color:#ff0}}@media (--wp-breakpoint=env(--wp-breakpoint)){.wp-block-query.is-not-env-breakpoint{color:#7fff00}}}';

if (($argv[1] ?? null) === '--self-test' && $actual !== $expected) {
    fwrite(STDERR, "Unexpected negated equality media layer output:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
