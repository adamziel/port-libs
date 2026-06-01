<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-title {
  font-family: "Inter", system-ui, sans-serif;
  font-size: 22px;
  font-size: max(2cqw, 22px);
}

.wp-block-button .wp-element-button {
  font-weight: 700;
  font-weight: 789;
}

.wp-block-heading.is-style-slanted {
  font: 22px Helvetica;
  font: oblique 40deg 22px system-ui, sans-serif;
}
CSS;

$prefixer = new TransitionPrefixer();
$systemFallback = 'system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Noto Sans,Ubuntu,Cantarell,Helvetica Neue';
$actual = [
    'legacy_editor' => $prefixer->prefixForTargets($css, ['safari' => 8]),
    'modern_chrome' => $prefixer->prefixForTargets($css, ['chrome' => 80]),
];

$expected = [
    'legacy_editor' => '.wp-block-post-title{font-family:Inter,' . $systemFallback . ',sans-serif;font-size:22px;font-size:max(2cqw,22px)}.wp-block-button .wp-element-button{font-weight:700;font-weight:789}.wp-block-heading.is-style-slanted{font:22px Helvetica;font:oblique 40deg 22px ' . $systemFallback . ',sans-serif}',
    'modern_chrome' => '.wp-block-post-title{font-family:Inter,system-ui,sans-serif;font-size:22px;font-size:max(2cqw,22px)}.wp-block-button .wp-element-button{font-weight:789}.wp-block-heading.is-style-slanted{font:oblique 40deg 22px system-ui,sans-serif}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected font target fallback output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
