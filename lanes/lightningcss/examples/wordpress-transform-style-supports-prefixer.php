<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
@supports (transform-style: preserve-3d) {
  .wp-block-cover.is-style-flip-card {
    transform-style: preserve-3d;
  }
}
CSS;

$staleCss = <<<'CSS'
@supports ((-webkit-transform-style: preserve-3d) or (transform-style: preserve-3d)) {
  .wp-block-cover.is-style-flip-card {
    -webkit-transform-style: preserve-3d;
    transform-style: preserve-3d;
  }
}
CSS;

$actual = [
    'android2_2' => $prefixer->prefixForTargets($css, ['android' => '2.2']),
    'android3' => $prefixer->prefixForTargets($css, ['android' => 3]),
    'safari3_1' => $prefixer->prefixForTargets($css, ['safari' => '3.1']),
    'safari4' => $prefixer->prefixForTargets($css, ['safari' => 4]),
    'firefox9' => $prefixer->prefixForTargets($css, ['firefox' => 9]),
    'firefox10' => $prefixer->prefixForTargets($css, ['firefox' => 10]),
    'stale_safari3_1' => $prefixer->prefixForTargets($staleCss, ['safari' => '3.1']),
];

$modern = '@supports (transform-style:preserve-3d){.wp-block-cover.is-style-flip-card{transform-style:preserve-3d}}';
$webkit = '@supports ((-webkit-transform-style:preserve-3d) or (transform-style:preserve-3d)){.wp-block-cover.is-style-flip-card{-webkit-transform-style:preserve-3d;transform-style:preserve-3d}}';
$moz = '@supports ((-moz-transform-style:preserve-3d) or (transform-style:preserve-3d)){.wp-block-cover.is-style-flip-card{-moz-transform-style:preserve-3d;transform-style:preserve-3d}}';

$expected = [
    'android2_2' => $modern,
    'android3' => $webkit,
    'safari3_1' => $modern,
    'safari4' => $webkit,
    'firefox9' => $modern,
    'firefox10' => $moz,
    'stale_safari3_1' => $modern,
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected transform-style supports prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

if (in_array('--self-test', $argv, true)) {
    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
