<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-hwb-advanced-origin {
  color: color-mix(in hwb, color(display-p3 0 1 0) 100%, rgb(0, 0, 0) 0%);
  background-color: color-mix(in hwb, lab(0% 104.3 -50.9) 100%, rgb(0, 0, 0) 0%);
  border-color: color-mix(in hwb, oklch(100% 0.399 336.3) 100%, rgb(0, 0, 0) 0%);
  --wp--preset--color--duotone-shadow: color-mix(in hwb, oklab(0% 0.365 -0.16) 100%, rgb(0, 0, 0) 0%);
}
CSS;

$expected = '.wp-block-cover.has-hwb-advanced-origin{color:#00f942;background-color:#2a0022;border-color:#fff;--wp--preset--color--duotone-shadow:#000}';
$actual = (new CssMinifier())->minify($css);

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected HWB color-mix advanced-origin output:\n{$actual}\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual . PHP_EOL;
