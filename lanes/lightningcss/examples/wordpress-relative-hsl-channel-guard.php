<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-relative-hsl-token {
  color: hsl(from rebeccapurple s h l);
  outline-color: hsl(from rebeccapurple s s s / s);
  background-color: hsl(from rebeccapurple calc(alpha * 100) calc(alpha * 100) calc(alpha * 100) / alpha);
}
CSS;

$expected = '.wp-block-cover.has-relative-hsl-token{color:hsl(from rebeccapurple s h l);outline-color:#bfaa40;background-color:#fff}';
$actual = (new CssMinifier())->minify($css);

if (($argv[1] ?? null) === '--self-test' && $actual !== $expected) {
    fwrite(STDERR, "Unexpected relative HSL channel guard output:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
