<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-search__input:placeholder-shown {
  opacity: .75;
}
CSS;

$actual = [
    'ie9' => $prefixer->prefixForTargets($css, ['ie' => 9]),
    'ie10' => $prefixer->prefixForTargets($css, ['ie' => 10]),
    'firefox50' => $prefixer->prefixForTargets($css, ['firefox' => 50]),
];

$expected = [
    'ie9' => '.wp-block-search__input:placeholder-shown{opacity:.75}',
    'ie10' => '.wp-block-search__input:-ms-placeholder-shown{opacity:.75}.wp-block-search__input:placeholder-shown{opacity:.75}',
    'firefox50' => '.wp-block-search__input:-moz-placeholder-shown{opacity:.75}.wp-block-search__input:placeholder-shown{opacity:.75}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected placeholder-shown selector prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

if (in_array('--self-test', $argv, true)) {
    echo "placeholder-shown selector prefixer example self-test passed\n";
    return;
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
