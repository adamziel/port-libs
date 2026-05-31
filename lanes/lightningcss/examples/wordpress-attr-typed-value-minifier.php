<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover[data-accent] {
  --block-accent: attr(data-accent type(<color>));
  background-color: attr(data-accent type(<color>));
  inline-size: attr(data-width type(<length>), 100px);
  margin-block-start: attr( data-offset    px, );
  max-inline-size: attr( data-width-percent    %, );
}
CSS;

$expected = '.wp-block-cover[data-accent]{--block-accent:attr(data-accent type(<color>));background-color:attr(data-accent type(<color>));inline-size:attr(data-width type(<length>), 100px);margin-block-start:attr(data-offset px,);max-inline-size:attr(data-width-percent %,)}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
