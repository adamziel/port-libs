<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-content.is-style-columns {
  columns: 2 16rem;
  column-gap: 1.5rem;
  column-rule: 1px solid #ddd;
}

.wp-block-pullquote.is-style-column-span {
  column-span: all;
  column-fill: balance;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'chrome49' => $prefixer->prefixForTargets($css, ['chrome' => 49]),
    'firefox51' => $prefixer->prefixForTargets($css, ['firefox' => 51]),
    'modern' => $prefixer->prefixForTargets($css, ['chrome' => 50, 'firefox' => 52, 'safari' => 9]),
];

$expected = [
    'chrome49' => '.wp-block-post-content.is-style-columns{-webkit-columns:2 16rem;columns:2 16rem;-webkit-column-gap:1.5rem;column-gap:1.5rem;-webkit-column-rule:1px solid #ddd;column-rule:1px solid #ddd}.wp-block-pullquote.is-style-column-span{-webkit-column-span:all;column-span:all;-webkit-column-fill:balance;column-fill:balance}',
    'firefox51' => '.wp-block-post-content.is-style-columns{-moz-columns:2 16rem;columns:2 16rem;-moz-column-gap:1.5rem;column-gap:1.5rem;-moz-column-rule:1px solid #ddd;column-rule:1px solid #ddd}.wp-block-pullquote.is-style-column-span{-moz-column-span:all;column-span:all;-moz-column-fill:balance;column-fill:balance}',
    'modern' => '.wp-block-post-content.is-style-columns{columns:2 16rem;column-gap:1.5rem;column-rule:1px solid #ddd}.wp-block-pullquote.is-style-column-span{column-span:all;column-fill:balance}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected column prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
