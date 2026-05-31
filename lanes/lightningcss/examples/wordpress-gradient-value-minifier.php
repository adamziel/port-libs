<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-directional-gradient {
  background: linear-gradient(to top, blue 10%, yellow 20%);
}

.wp-block-cover.has-bookend-gradient {
  background-image: linear-gradient(0, yellow, blue);
}

.wp-block-group.has-stop-gradient {
  border-image-source: linear-gradient(yellow, red 30%, red 40%, blue);
}

.wp-block-cover.has-radial-focus {
  background: radial-gradient(farthest-corner circle at 100% 50%, #333, #333 50%, #eee 75%, #333 75%);
}

.wp-block-cover.has-radial-size {
  background-image: radial-gradient(ellipse calc(20px + 10px) 40px, yellow, blue);
}

.wp-block-cover.has-conic-accent {
  background: conic-gradient(from 0deg at center, #f06, gold);
}
CSS;

$expected = '.wp-block-cover.has-directional-gradient{background:linear-gradient(#ff0 80%,#00f 90%)}.wp-block-cover.has-bookend-gradient{background-image:linear-gradient(#00f,#ff0)}.wp-block-group.has-stop-gradient{border-image-source:linear-gradient(#ff0,red 30% 40%,#00f)}.wp-block-cover.has-radial-focus{background:radial-gradient(circle at 100%,#333,#333 50%,#eee 75%,#333 75%)}.wp-block-cover.has-radial-size{background-image:radial-gradient(30px 40px,#ff0,#00f)}.wp-block-cover.has-conic-accent{background:conic-gradient(#f06,gold)}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected gradient value minifier output:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
