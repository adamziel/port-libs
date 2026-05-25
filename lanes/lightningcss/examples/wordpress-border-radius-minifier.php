<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/MediaQueryParser.php';
require_once __DIR__ . '/../src/CssMinifier.php';

use PortLibs\LightningCSS\CssMinifier;

$css = <<<'CSS'
.wp-block-group.is-style-card {
  border-radius: 10px 100px 10px 100px / 120px 120px;
}

.wp-block-image.is-style-rounded img {
  -webkit-border-radius: 0px 10px 0px 10px;
  border-radius: 0px 10px 0px 10px;
}
CSS;

$expected = '.wp-block-group.is-style-card{border-radius:10px 100px/120px}.wp-block-image.is-style-rounded img{-webkit-border-radius:0 10px;border-radius:0 10px}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
