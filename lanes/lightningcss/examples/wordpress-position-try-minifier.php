<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@position-try --wp-popover-below {
  top: anchor(bottom);
  margin: 0;
  width: auto;
}

@supports (anchor-name: --wp-toolbar) {
  @position-try --wp-popover-above {
    bottom: anchor(top);
    margin: 0;
  }
}

.wp-block-popover {
  position-anchor: --wp-toolbar;
  position-try-fallbacks: --wp-popover-below, --wp-popover-above flip-block;
  color: yellow;
}
CSS;

$expected = '@position-try --wp-popover-below{top:anchor(bottom);margin:0;width:auto}@supports (anchor-name:--wp-toolbar){@position-try --wp-popover-above{bottom:anchor(top);margin:0}}.wp-block-popover{position-anchor:--wp-toolbar;position-try-fallbacks:--wp-popover-below,--wp-popover-above flip-block;color:#ff0}';
$actual = (new CssMinifier())->minify($css);

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected position-try minifier output:\n{$actual}\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual . PHP_EOL;
