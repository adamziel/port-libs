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
CSS;

$expected = '.wp-block-cover.has-directional-gradient{background:linear-gradient(#ff0 80%,#00f 90%)}.wp-block-cover.has-bookend-gradient{background-image:linear-gradient(#00f,#ff0)}.wp-block-group.has-stop-gradient{border-image-source:linear-gradient(#ff0,red 30% 40%,#00f)}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected gradient value minifier output:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
