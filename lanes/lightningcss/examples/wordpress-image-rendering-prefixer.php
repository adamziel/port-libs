<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-image.is-style-pixel-art img {
  image-rendering: pixelated;
}

.wp-block-gallery.has-posterized-thumbnails img {
  -ms-interpolation-mode: nearest-neighbor;
  image-rendering: -webkit-optimize-contrast;
  image-rendering: -moz-crisp-edges;
  image-rendering: -o-pixelated;
  image-rendering: pixelated;
}
CSS;

$actual = [
    'legacy_editor' => $prefixer->prefixForTargets($css, [
        'safari' => 6,
        'firefox' => 64,
        'opera' => '12.1',
        'ie' => 11,
    ]),
    'modern_frontend' => $prefixer->prefixForTargets($css, [
        'chrome' => 120,
        'safari' => 17,
        'firefox' => 120,
        'edge' => 120,
    ]),
    'supports_firefox64' => $prefixer->prefixForTargets(
        '@supports (image-rendering: pixelated) { .wp-block-image img { image-rendering: pixelated; } }',
        ['firefox' => 64]
    ),
];

$expected = [
    'legacy_editor' => '.wp-block-image.is-style-pixel-art img,.wp-block-gallery.has-posterized-thumbnails img{-ms-interpolation-mode:nearest-neighbor;image-rendering:-webkit-optimize-contrast;image-rendering:-moz-crisp-edges;image-rendering:-o-pixelated;image-rendering:pixelated}',
    'modern_frontend' => '.wp-block-image.is-style-pixel-art img,.wp-block-gallery.has-posterized-thumbnails img{image-rendering:pixelated}',
    'supports_firefox64' => '@supports ((image-rendering:-moz-crisp-edges) or (image-rendering:pixelated)){.wp-block-image img{image-rendering:-moz-crisp-edges;image-rendering:pixelated}}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected image-rendering prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
