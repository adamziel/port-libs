<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-navigation .menu-item {
  unicode-bidi: isolate;
}

.wp-block-post-title .title-fragment {
  unicode-bidi: plaintext;
}

.wp-block-quote cite {
  unicode-bidi: isolate-override;
}
CSS;

$actual = [
    'legacy_editor' => $prefixer->prefixForTargets($css, [
        'chrome' => 47,
        'firefox' => 49,
        'safari' => '10.1',
    ]),
    'modern_frontend' => $prefixer->prefixForTargets($css, [
        'chrome' => 48,
        'firefox' => 50,
        'safari' => 11,
    ]),
];

$expected = [
    'legacy_editor' => '.wp-block-navigation .menu-item{unicode-bidi:-webkit-isolate;unicode-bidi:-moz-isolate;unicode-bidi:isolate}.wp-block-post-title .title-fragment{unicode-bidi:-webkit-plaintext;unicode-bidi:-moz-plaintext;unicode-bidi:plaintext}.wp-block-quote cite{unicode-bidi:-webkit-isolate-override;unicode-bidi:-moz-isolate-override;unicode-bidi:isolate-override}',
    'modern_frontend' => '.wp-block-navigation .menu-item{unicode-bidi:isolate}.wp-block-post-title .title-fragment{unicode-bidi:plaintext}.wp-block-quote cite{unicode-bidi:isolate-override}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected unicode-bidi prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
