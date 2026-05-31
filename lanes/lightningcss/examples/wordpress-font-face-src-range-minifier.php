<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@font-face {
  font-family: "Inter Variable";
  font-style: oblique 0deg 10deg;
  font-weight: 100 900;
  src: local("Inter Variable"), url("./assets/fonts/inter-var.woff2") format(woff2) tech(variations);
  unicode-range: U+0025-00FF, U+400-4FF;
  font-display: swap;
}

@font-face {
  font-family: Inter;
  font-style: oblique 14deg 14deg;
  font-weight: 400 400;
  font-stretch: 50% 50%;
  src: local(Inter), url("./assets/fonts/inter-static.woff") format(woff) tech(palettes);
  unicode-range: u+????, U+1????, U+10????;
}
CSS;

$expected = '@font-face{font-family:Inter Variable;font-style:oblique 0deg 10deg;font-weight:100 900;src:local(Inter Variable),url(./assets/fonts/inter-var.woff2)format("woff2")tech(variations);unicode-range:U+25-FF,U+4??;font-display:swap}@font-face{font-family:Inter;font-style:oblique;font-weight:400;font-stretch:50%;src:local(Inter),url(./assets/fonts/inter-static.woff)format("woff")tech(palettes);unicode-range:U+????,U+1????,U+10????}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
