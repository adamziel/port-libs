<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-media-text__media {
  vertical-align: 0.3em;
}

.wp-block-media-text__content {
  vertical-align: middle;
}
CSS;

$expected = '.wp-block-media-text__media{vertical-align:.3em}.wp-block-media-text__content{vertical-align:middle}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
