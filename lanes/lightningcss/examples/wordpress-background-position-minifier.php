<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-featured {
  background: url("hero.jpg") no-repeat bottom right;
}

.wp-block-cover.is-style-centered {
  background: none center;
}

.wp-block-media-text .wp-block-media-text__media {
  background-position: left 10px top 20px;
}

.wp-block-group.has-transparent-background {
  background: transparent;
}
CSS;

$expected = '.wp-block-cover.is-style-featured{background:url(hero.jpg) 100% 100% no-repeat}.wp-block-cover.is-style-centered{background:50%}.wp-block-media-text .wp-block-media-text__media{background-position:10px 20px}.wp-block-group.has-transparent-background{background:0 0}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
