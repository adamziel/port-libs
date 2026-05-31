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

  @media (width = 320px) {
    .wp-block-query.is-exact-width {
      color: yellow;
    }
  }

  @media not screen and (width < 240px) {
    .wp-block-query.is-print-narrow {
      color: yellow;
    }
  }

  @media screen and (width > max(10px, 1rem)) {
    .wp-block-query.is-fluid-breakpoint {
      color: yellow;
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

  @media (aspect-ratio >= 16 / 9) and (color-index > 2) {
    .wp-block-query.is-wide-color {
      color: yellow;
    }
  }

  @media (theme-breakpoint >= 2) {
    .wp-block-query.is-custom-breakpoint {
      color: yellow;
    }
  }

  @media (theme-state = expanded) {
    .wp-block-query.is-expanded-state {
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
    'chrome95' => $prefixer->prefixForTargets($css, ['chrome' => 95]),
    'forcedRangeFallback' => $prefixer->prefixForTargets($css, [
        'include' => ['MediaRangeSyntax', 'MediaIntervalSyntax'],
    ]),
    'compoundResolutionRange' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (min-resolution: 2dppx) and (max-resolution: 3dppx) { .wp-block-query.is-density-window { color: yellow; } } }',
        ['safari' => 15, 'firefox' => 10]
    ),
    'negatedRangeGroup' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media not ((100px <= width <= 200px) or (hover)) { .wp-block-query.is-not-compact-hover { color: yellow; } } }',
        ['firefox' => 85]
    ),
    'negatedIntervalWithHover' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (hover) and (not (200px <= width < 500px)) { .wp-block-query.is-not-middle-hover { color: yellow; } } }',
        ['chrome' => 95]
    ),
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

try {
    $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (100px < width > 200px) { .wp-block-query { color: chartreuse; } } }',
        ['firefox' => 60]
    );
    $actual['invalidIntervalGuard'] = 'missing';
} catch (InvalidArgumentException) {
    $actual['invalidIntervalGuard'] = 'invalid-media-query';
}

try {
    $prefixer->prefixForTargets(
        '@layer theme.blocks { @media var(--theme-breakpoint) { .wp-block-query { color: chartreuse; } } }',
        ['firefox' => 60]
    );
    $actual['invalidFunctionGuard'] = 'missing';
} catch (InvalidArgumentException) {
    $actual['invalidFunctionGuard'] = 'invalid-media-query';
}

try {
    $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (width >= var(--theme-breakpoint)) { .wp-block-query { color: chartreuse; } } }',
        ['firefox' => 60]
    );
    $actual['invalidRangeValueGuard'] = 'missing';
} catch (InvalidArgumentException) {
    $actual['invalidRangeValueGuard'] = 'invalid-media-query';
}

try {
    $prefixer->prefixForTargets(
        '@layer theme.blocks { @media screen not (width >= 240px) { .wp-block-query { color: chartreuse; } } }',
        ['firefox' => 60]
    );
    $actual['missingAndGuard'] = 'missing';
} catch (InvalidArgumentException) {
    $actual['missingAndGuard'] = 'invalid-media-query';
}

$expected = [
    'safari15' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query{color:#7fff00}}@media (width:320px){.wp-block-query.is-exact-width{color:#ff0}}@media not screen and not (min-width:240px){.wp-block-query.is-print-narrow{color:#ff0}}@media screen and not (max-width:max(10px,1rem)){.wp-block-query.is-fluid-breakpoint{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (-webkit-min-device-pixel-ratio:2),(color) and (min-resolution:2dppx){.wp-block-query.is-density-aware{color:#ff0}}@media (min-aspect-ratio:16/9) and (not (max-color-index:2)){.wp-block-query.is-wide-color{color:#ff0}}@media (min-theme-breakpoint:2){.wp-block-query.is-custom-breakpoint{color:#ff0}}@media (theme-state:expanded){.wp-block-query.is-expanded-state{color:#ff0}}}',
    'firefox60' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query{color:#7fff00}}@media (width:320px){.wp-block-query.is-exact-width{color:#ff0}}@media not screen and not (min-width:240px){.wp-block-query.is-print-narrow{color:#ff0}}@media screen and not (max-width:max(10px,1rem)){.wp-block-query.is-fluid-breakpoint{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (min-resolution:2dppx){.wp-block-query.is-density-aware{color:#ff0}}@media (min-aspect-ratio:16/9) and (not (max-color-index:2)){.wp-block-query.is-wide-color{color:#ff0}}@media (min-theme-breakpoint:2){.wp-block-query.is-custom-breakpoint{color:#ff0}}@media (theme-state:expanded){.wp-block-query.is-expanded-state{color:#ff0}}}',
    'firefox64' => '@layer theme.blocks{@media (width>=240px){.wp-block-query{color:#7fff00}}@media (width=320px){.wp-block-query.is-exact-width{color:#ff0}}@media not screen and (width<240px){.wp-block-query.is-print-narrow{color:#ff0}}@media screen and (width>max(10px,1rem)){.wp-block-query.is-fluid-breakpoint{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (resolution>=2x){.wp-block-query.is-density-aware{color:#ff0}}@media (aspect-ratio>=16/9) and (color-index>2){.wp-block-query.is-wide-color{color:#ff0}}@media (theme-breakpoint>=2){.wp-block-query.is-custom-breakpoint{color:#ff0}}@media (theme-state=expanded){.wp-block-query.is-expanded-state{color:#ff0}}}',
    'firefox85' => '@layer theme.blocks{@media (width>=240px){.wp-block-query{color:#7fff00}}@media (width=320px){.wp-block-query.is-exact-width{color:#ff0}}@media not screen and (width<240px){.wp-block-query.is-print-narrow{color:#ff0}}@media screen and (width>max(10px,1rem)){.wp-block-query.is-fluid-breakpoint{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (resolution>=2x){.wp-block-query.is-density-aware{color:#ff0}}@media (aspect-ratio>=16/9) and (color-index>2){.wp-block-query.is-wide-color{color:#ff0}}@media (theme-breakpoint>=2){.wp-block-query.is-custom-breakpoint{color:#ff0}}@media (theme-state=expanded){.wp-block-query.is-expanded-state{color:#ff0}}}',
    'chrome95' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query{color:#7fff00}}@media (width:320px){.wp-block-query.is-exact-width{color:#ff0}}@media not screen and not (min-width:240px){.wp-block-query.is-print-narrow{color:#ff0}}@media screen and not (max-width:max(10px,1rem)){.wp-block-query.is-fluid-breakpoint{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (min-resolution:2x){.wp-block-query.is-density-aware{color:#ff0}}@media (min-aspect-ratio:16/9) and (not (max-color-index:2)){.wp-block-query.is-wide-color{color:#ff0}}@media (min-theme-breakpoint:2){.wp-block-query.is-custom-breakpoint{color:#ff0}}@media (theme-state:expanded){.wp-block-query.is-expanded-state{color:#ff0}}}',
    'forcedRangeFallback' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query{color:#7fff00}}@media (width:320px){.wp-block-query.is-exact-width{color:#ff0}}@media not screen and not (min-width:240px){.wp-block-query.is-print-narrow{color:#ff0}}@media screen and not (max-width:max(10px,1rem)){.wp-block-query.is-fluid-breakpoint{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (min-resolution:2dppx){.wp-block-query.is-density-aware{color:#ff0}}@media (min-aspect-ratio:16/9) and (not (max-color-index:2)){.wp-block-query.is-wide-color{color:#ff0}}@media (min-theme-breakpoint:2){.wp-block-query.is-custom-breakpoint{color:#ff0}}@media (theme-state:expanded){.wp-block-query.is-expanded-state{color:#ff0}}}',
    'compoundResolutionRange' => '@layer theme.blocks{@media (-webkit-min-device-pixel-ratio:2) and (-webkit-max-device-pixel-ratio:3),(min--moz-device-pixel-ratio:2) and (max--moz-device-pixel-ratio:3),(min-resolution:2dppx) and (max-resolution:3dppx){.wp-block-query.is-density-window{color:#ff0}}}',
    'negatedRangeGroup' => '@layer theme.blocks{@media not (((min-width:100px) and (max-width:200px)) or (hover)){.wp-block-query.is-not-compact-hover{color:#ff0}}}',
    'negatedIntervalWithHover' => '@layer theme.blocks{@media (hover) and (not ((min-width:200px) and (not (min-width:500px)))){.wp-block-query.is-not-middle-hover{color:#ff0}}}',
    'invalidRangeGuard' => 'invalid-media-query',
    'invalidIntervalGuard' => 'invalid-media-query',
    'invalidFunctionGuard' => 'invalid-media-query',
    'invalidRangeValueGuard' => 'invalid-media-query',
    'missingAndGuard' => 'invalid-media-query',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected media range layer prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
