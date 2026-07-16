<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\TransitionPrefixer;

$css = <<<'CSS'
.wp-block-search__input:-moz-read-only {
  cursor: not-allowed;
}
.wp-block-search__input:read-only {
  cursor: not-allowed;
}
.wp-block-cover:-webkit-full-screen {
  background: black;
}
.wp-block-cover:-moz-full-screen {
  background: black;
}
.wp-block-cover:-ms-fullscreen {
  background: black;
}
.wp-block-cover:fullscreen {
  background: black;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'modern' => $prefixer->prefixForTargets($css, ['chrome' => 96, 'firefox' => 85, 'edge' => 19]),
    'legacy' => $prefixer->prefixForTargets($css, ['chrome' => 45, 'firefox' => 36, 'ie' => 11]),
];

$expected = [
    'modern' => '.wp-block-search__input:read-only{cursor:not-allowed}.wp-block-cover:fullscreen{background:#000}',
    'legacy' => '.wp-block-search__input:-moz-read-only{cursor:not-allowed}.wp-block-search__input:read-only{cursor:not-allowed}.wp-block-cover:-webkit-full-screen{background:#000}.wp-block-cover:-moz-full-screen{background:#000}.wp-block-cover:-ms-fullscreen{background:#000}.wp-block-cover:fullscreen{background:#000}',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected selector stale-prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "selector stale prefix boundary example self-test passed\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
