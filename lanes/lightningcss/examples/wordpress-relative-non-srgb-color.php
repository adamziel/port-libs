<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-wide-gamut-relative {
  color: rgb(from color(display-p3 0 1 0) r g b / alpha);
  background-color: hsl(from lab(0% 104.3 -50.9) h s l / alpha);
  border-color: hwb(from oklch(100% 0.399 336.3) h w b / alpha);
}
CSS;

$expected = '.wp-block-cover.has-wide-gamut-relative{color:#00f942;background-color:#2a0022;border-color:#fff}';
$actual = (new CssMinifier())->minify($css);

if (in_array('--self-test', $argv, true) && $actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n" . $actual . PHP_EOL);
    exit(1);
}

echo $actual . PHP_EOL;
