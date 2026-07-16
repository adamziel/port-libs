<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-alpha-hsl-mix {
  color: color-mix(in hsl, 25% hsl(120deg 10% 20% / .4), hsl(30deg 30% 40% / .8));
  border-color: color-mix(in hsl, hsl(120deg 10% 20% / .4) 12.5%, hsl(30deg 30% 40% / .8) 37.5%);
}

.wp-block-cover.has-alpha-hwb-mix {
  background-color: color-mix(in hwb, hwb(120deg 10% 20% / .4), hwb(30deg 30% 40% / .8));
  outline-color: color-mix(in hwb, hwb(120deg 10% 20% / .4) 12.5%, hwb(30deg 30% 40% / .8) 37.5%);
}

.wp-block-button.has-duotone-yellow-green {
  color: color-mix(in hsl, hsl(120 100% 49.898%) 80%, yellow);
}
CSS;

$expected = '.wp-block-cover.has-alpha-hsl-mix{color:#797245b3;border-color:#79724559}.wp-block-cover.has-alpha-hwb-mix{background-color:#8faa3c99;outline-color:#a0954659}.wp-block-button.has-duotone-yellow-green{color:#33fe00}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected alpha color-mix output:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
