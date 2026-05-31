<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme.blocks {
  @media (width >= 240px) {
    .wp-block-query {
      color: chartreuse;
    }
  }

  @media (hover) or (100px <= width <= 200px) {
    .wp-block-query.is-style-featured {
      color: yellow;
    }
  }

  @media (color) and (min-resolution: 2dppx) {
    .wp-block-query.is-density-aware {
      color: yellow;
    }
  }
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'safari15' => $prefixer->prefixForTargets($css, ['safari' => 15]),
    'firefox60' => $prefixer->prefixForTargets($css, ['firefox' => 60]),
    'firefox64' => $prefixer->prefixForTargets($css, ['firefox' => 64]),
    'firefox85' => $prefixer->prefixForTargets($css, ['firefox' => 85]),
    'forcedRangeFallback' => $prefixer->prefixForTargets($css, [
        'include' => ['MediaRangeSyntax', 'MediaIntervalSyntax'],
    ]),
];

try {
    $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (scan >= 1) { .wp-block-query { color: chartreuse; } } }',
        ['firefox' => 60]
    );
    $actual['invalidRangeGuard'] = 'missing';
} catch (InvalidArgumentException) {
    $actual['invalidRangeGuard'] = 'invalid-media-query';
}

$expected = [
    'safari15' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query{color:#7fff00}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (-webkit-min-device-pixel-ratio:2),(color) and (min-resolution:2dppx){.wp-block-query.is-density-aware{color:#ff0}}}',
    'firefox60' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query{color:#7fff00}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (min-resolution:2dppx){.wp-block-query.is-density-aware{color:#ff0}}}',
    'firefox64' => '@layer theme.blocks{@media (width>=240px){.wp-block-query{color:#7fff00}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (resolution>=2dppx){.wp-block-query.is-density-aware{color:#ff0}}}',
    'firefox85' => '@layer theme.blocks{@media (width>=240px){.wp-block-query{color:#7fff00}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (resolution>=2dppx){.wp-block-query.is-density-aware{color:#ff0}}}',
    'forcedRangeFallback' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query{color:#7fff00}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (min-resolution:2dppx){.wp-block-query.is-density-aware{color:#ff0}}}',
    'invalidRangeGuard' => 'invalid-media-query',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected media range layer prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
