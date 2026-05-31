<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-hwb-overlay {
  color: hwb(194 0% 0% / 50%);
  background: linear-gradient(hwb(194 50% 0%), hwb(194 0% 50%));
  border-color: ButtonBorder;
}

.wp-block-button .wp-element-button {
  color: light-dark(#FFF, #FFF);
  outline-color: hsl(none none none);
}
CSS;

$expected = '.wp-block-cover.has-hwb-overlay{color:#00c4ff80;background:linear-gradient(#80e1ff,#006280);border-color:buttonborder}.wp-block-button .wp-element-button{color:#fff;outline-color:#000}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
