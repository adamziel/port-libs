<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-cover.is-style-glass {
  transition-property: -webkit-backdrop-filter, backdrop-filter;
}

.wp-block-group.is-style-glass {
  transition-property: backdrop-filter;
}

.wp-block-media-text.is-style-static {
  transition-property: -webkit-backdrop-filter;
}
CSS;

$actual = [
    'safari15' => $prefixer->prefixForTargets($css, ['safari' => 15]),
];

$expected = [
    'safari15' => '.wp-block-cover.is-style-glass,.wp-block-group.is-style-glass{transition-property:-webkit-backdrop-filter,backdrop-filter}.wp-block-media-text.is-style-static{transition-property:-webkit-backdrop-filter}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected target-prefix rule merge output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
