<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-group.is-style-fluid-card {
  padding: 22px;
  padding: max(4%, 22px);
  width: 22px;
  width: max(2cqw, 22px);
  border-radius: 8px;
  border-radius: max(2cqw, 8px);
}
CSS;

$actual = [
    'legacy_safari10' => $prefixer->prefixForTargets($css, ['safari' => 10]),
    'safari14' => $prefixer->prefixForTargets($css, ['safari' => 14]),
    'safari16' => $prefixer->prefixForTargets($css, ['safari' => 16]),
];

$expected = [
    'legacy_safari10' => '.wp-block-group.is-style-fluid-card{padding:22px;padding:max(4%,22px);width:22px;width:max(2cqw,22px);border-radius:8px;border-radius:max(2cqw,8px)}',
    'safari14' => '.wp-block-group.is-style-fluid-card{padding:max(4%,22px);width:22px;width:max(2cqw,22px);border-radius:8px;border-radius:max(2cqw,8px)}',
    'safari16' => '.wp-block-group.is-style-fluid-card{padding:max(4%,22px);width:max(2cqw,22px);border-radius:max(2cqw,8px)}',
];

if (in_array('--self-test', $argv, true)) {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected fluid length target fallback output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "fluid length target fallback example self-test passed\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
