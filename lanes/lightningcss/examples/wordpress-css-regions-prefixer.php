<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-post-template.is-region-source {
  flow-into: wp-region-feed;
}

.wp-block-query-pagination.is-region-frame {
  flow-from: wp-region-feed;
  region-fragment: break;
}
CSS;

$actual = [
    'legacy_editor' => $prefixer->prefixForTargets($css, [
        'chrome' => 18,
        'ie' => 10,
    ]),
    'legacy_safari' => $prefixer->prefixForTargets($css, [
        'safari' => 11,
    ]),
    'modern_frontend' => $prefixer->prefixForTargets($css, [
        'chrome' => 19,
        'edge' => 19,
        'safari' => 12,
        'ios_saf' => 12,
    ]),
];

$expected = [
    'legacy_editor' => '.wp-block-post-template.is-region-source{-webkit-flow-into:wp-region-feed;-ms-flow-into:wp-region-feed;flow-into:wp-region-feed}.wp-block-query-pagination.is-region-frame{-webkit-flow-from:wp-region-feed;-ms-flow-from:wp-region-feed;flow-from:wp-region-feed;-webkit-region-fragment:break;-ms-region-fragment:break;region-fragment:break}',
    'legacy_safari' => '.wp-block-post-template.is-region-source{-webkit-flow-into:wp-region-feed;flow-into:wp-region-feed}.wp-block-query-pagination.is-region-frame{-webkit-flow-from:wp-region-feed;flow-from:wp-region-feed;-webkit-region-fragment:break;region-fragment:break}',
    'modern_frontend' => '.wp-block-post-template.is-region-source{flow-into:wp-region-feed}.wp-block-query-pagination.is-region-frame{flow-from:wp-region-feed;region-fragment:break}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Regions target-prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
