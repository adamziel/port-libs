<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-query-pagination {
  break-before: page;
}

.wp-block-column {
  break-after: column;
  break-inside: avoid;
}
CSS;

$supportsCss = <<<'CSS'
@supports (break-before: page) {
  .wp-block-query-pagination {
    break-before: page;
  }
}
CSS;

$actual = [
    'legacy_webkit_editor' => $prefixer->prefixForTargets($css, [
        'android' => '4.4.3',
        'chrome' => 49,
        'ios_saf' => '8.1',
        'opera' => 36,
        'safari' => 8,
        'samsung' => 4,
    ]),
    'modern_frontend' => $prefixer->prefixForTargets($css, [
        'android' => '4.4.4',
        'chrome' => 50,
        'ios_saf' => '8.2',
        'opera' => 37,
        'safari' => 9,
        'samsung' => 5,
    ]),
    'legacy_webkit_supports' => $prefixer->prefixForTargets($supportsCss, [
        'chrome' => 49,
    ]),
    'modern_supports' => $prefixer->prefixForTargets($supportsCss, [
        'chrome' => 50,
    ]),
];

$expected = [
    'legacy_webkit_editor' => '.wp-block-query-pagination{-webkit-break-before:page;break-before:page}.wp-block-column{-webkit-break-after:column;break-after:column;-webkit-break-inside:avoid;break-inside:avoid}',
    'modern_frontend' => '.wp-block-query-pagination{break-before:page}.wp-block-column{break-after:column;break-inside:avoid}',
    'legacy_webkit_supports' => '@supports ((-webkit-break-before:page) or (break-before:page)){.wp-block-query-pagination{-webkit-break-before:page;break-before:page}}',
    'modern_supports' => '@supports (break-before:page){.wp-block-query-pagination{break-before:page}}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected break target-prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
