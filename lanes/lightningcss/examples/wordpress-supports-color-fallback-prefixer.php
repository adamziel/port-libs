<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@supports (color: lab(0% 0 0)) or (color: color(display-p3 0 0 0)) {
  .wp-block-cover.has-accent-overlay {
    color: lab(40% 56.6 39);
    background-color: color(display-p3 .643308 .192455 .167712);
  }
}

@supports (color: light-dark(#000, #fff)) {
  .wp-block-cover.has-scheme-overlay {
    color: light-dark(#fff, #000);
  }
}
CSS;

$actual = (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 4]);
$expected = '@supports (color:lab(0% 0 0)) or (color:color(display-p3 0 0 0)){.wp-block-cover.has-accent-overlay{color:#b32323;color:lab(40% 56.6 39);background-color:#b32323;background-color:color(display-p3 .643308 .192455 .167712)}}@supports (color:light-dark(#000,#fff)){.wp-block-cover.has-scheme-overlay{color:light-dark(#fff,#000)}}';

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected supports color fallback output:\n{$actual}\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual . PHP_EOL;
