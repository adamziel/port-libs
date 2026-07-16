<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-editorial {
  block-size: var(--wp--custom--cover-height);
  inline-size: min(100%, 72rem);
  min-block-size: 25rem;
  min-inline-size: 18rem;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'safari12' => $prefixer->prefixForTargets($css, ['safari' => 12]),
    'safari12_1' => $prefixer->prefixForTargets($css, ['safari' => '12.1']),
];

$expected = [
    'safari12' => '.wp-block-cover.is-style-editorial{height:var(--wp--custom--cover-height);min-height:25rem;width:min(100%,72rem);min-width:18rem}',
    'safari12_1' => '.wp-block-cover.is-style-editorial{block-size:var(--wp--custom--cover-height);inline-size:min(100%,72rem);min-block-size:25rem;min-inline-size:18rem}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected logical size prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
