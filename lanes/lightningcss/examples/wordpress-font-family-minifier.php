<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-title {
  font-family: "Inter", "Inter", "Helvetica Neue", "Helvetica Neue", sans-serif;
  font-stretch: expanded;
}

@font-face {
  font-family: "revert";
  src: url("./fonts/revert.woff2") format("woff2");
}
CSS;

$expected = '.wp-block-post-title{font-family:Inter,Helvetica Neue,sans-serif;font-stretch:125%}@font-face{font-family:"revert";src:url(./fonts/revert.woff2)format("woff2")}';
$actual = (new CssMinifier())->minify($css);

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected font-family minifier output:\n" . $actual . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual . PHP_EOL;
