<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-content a.has-brand-underline {
  text-decoration: underline dotted;
}

.wp-block-post-content a.has-dotted-underline {
  text-decoration-line: underline;
  text-decoration-style: dotted;
  text-decoration-color: red;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'safari12' => $prefixer->prefixForTargets($css, ['safari' => 12]),
    'safari12_1' => $prefixer->prefixForTargets($css, ['safari' => '12.1']),
    'safari16' => $prefixer->prefixForTargets($css, ['safari' => 16]),
    'safari27' => $prefixer->prefixForTargets($css, ['safari' => 27]),
];

$expected = [
    'safari12' => '.wp-block-post-content a.has-brand-underline{-webkit-text-decoration:underline dotted;text-decoration:underline dotted}.wp-block-post-content a.has-dotted-underline{-webkit-text-decoration-line:underline;text-decoration-line:underline;-webkit-text-decoration-style:dotted;text-decoration-style:dotted;-webkit-text-decoration-color:red;text-decoration-color:red}',
    'safari12_1' => '.wp-block-post-content a.has-brand-underline{-webkit-text-decoration:underline dotted;text-decoration:underline dotted}.wp-block-post-content a.has-dotted-underline{text-decoration-line:underline;text-decoration-style:dotted;text-decoration-color:red}',
    'safari16' => '.wp-block-post-content a.has-brand-underline{-webkit-text-decoration:underline dotted;text-decoration:underline dotted}.wp-block-post-content a.has-dotted-underline{text-decoration-line:underline;text-decoration-style:dotted;text-decoration-color:red}',
    'safari27' => '.wp-block-post-content a.has-brand-underline{text-decoration:underline dotted}.wp-block-post-content a.has-dotted-underline{text-decoration-line:underline;text-decoration-style:dotted;text-decoration-color:red}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected text-decoration prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
