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
    'importRangeLayerTailFallback' => $prefixer->prefixForTargets(
        '@import "blocks/query.css" layer(theme.blocks) (width >= 240px); @layer theme.blocks { .wp-block-query.is-imported-range { color: yellow; } }',
        ['firefox' => 60]
    ),
    'importIntervalLayerTailFallback' => $prefixer->prefixForTargets(
        '@import "blocks/query.css" layer(theme.blocks) (100px <= width <= 200px); @layer theme.blocks { .wp-block-query.is-imported-window { color: yellow; } }',
        ['firefox' => 85]
    ),
    'importResolutionRangeXUnitTailFallback' => $prefixer->prefixForTargets(
        '@import "blocks/density.css" layer(theme.blocks) (resolution >= 2dppx); @layer theme.blocks { .wp-block-query.is-imported-density { color: yellow; } }',
        ['chrome' => 95]
    ),
    'safari163RangeFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (width >= 240px) { .wp-block-query.is-range-boundary { color: yellow; } } }',
        ['safari' => '16.3']
    ),
    'safari164RangeModern' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (width >= 240px) { .wp-block-query.is-range-boundary { color: yellow; } } }',
        ['safari' => '16.4']
    ),
    'firefox62RangeFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (width >= 240px) { .wp-block-query.is-firefox-range-boundary { color: yellow; } } }',
        ['firefox' => 62]
    ),
    'firefox63RangeModern' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (width >= 240px) { .wp-block-query.is-firefox-range-boundary { color: yellow; } } }',
        ['firefox' => 63]
    ),
    'opera70RangeFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (width >= 240px) { .wp-block-query.is-opera-range-boundary { color: yellow; } } }',
        ['opera' => 70]
    ),
    'opera71RangeModern' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (width >= 240px) { .wp-block-query.is-opera-range-boundary { color: yellow; } } }',
        ['opera' => 71]
    ),
    'firefox101IntervalFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (100px <= width <= 200px) { .wp-block-query.is-firefox-window-boundary { color: yellow; } } }',
        ['firefox' => 101]
    ),
    'firefox102IntervalModern' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (100px <= width <= 200px) { .wp-block-query.is-firefox-window-boundary { color: yellow; } } }',
        ['firefox' => 102]
    ),
    'opera70IntervalFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (100px <= width <= 200px) { .wp-block-query.is-opera-window-boundary { color: yellow; } } }',
        ['opera' => 70]
    ),
    'opera71IntervalModern' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (100px <= width <= 200px) { .wp-block-query.is-opera-window-boundary { color: yellow; } } }',
        ['opera' => 71]
    ),
    'chrome28ResolutionPrefixBoundary' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (min-resolution: 2dppx) { .wp-block-query.is-density-boundary { color: yellow; } } }',
        ['chrome' => 28]
    ),
    'firefox30ResolutionNoPrefixBoundary' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (min-resolution: 2dppx) { .wp-block-query.is-density-boundary { color: yellow; } } }',
        ['firefox' => '3.0']
    ),
    'forcedRangeFallback' => $prefixer->prefixForTargets($css, [
        'include' => ['MediaRangeSyntax', 'MediaIntervalSyntax'],
    ]),
    'unitlessLengthRangeFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (width >= 2) { .wp-block-query.is-unitless-width { color: yellow; } } }',
        ['firefox' => 60]
    ),
    'unitlessLengthIntervalModern' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (2 <= width <= 4) { .wp-block-query.is-unitless-width-window { color: yellow; } } }',
        ['firefox' => 102]
    ),
    'compoundResolutionRange' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (min-resolution: 2dppx) and (max-resolution: 3dppx) { .wp-block-query.is-density-window { color: yellow; } } }',
        ['safari' => 15, 'firefox' => 10]
    ),
    'fractionalResolutionRange' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (0.5dppx <= resolution <= 1.5dppx) { .wp-block-query.is-low-density-window { color: yellow; } } }',
        ['safari' => 15, 'firefox' => 10]
    ),
    'modernFractionalResolutionRangeXUnit' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (0.5dppx <= resolution <= 1.5dppx) { .wp-block-query.is-low-density-window { color: yellow; } } }',
        ['firefox' => 102]
    ),
    'xResolutionLegacyUnit' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (resolution: 1x) { .wp-block-query.is-density-aware { background: red; } } }',
        ['chrome' => 50]
    ),
    'xResolutionPrefixFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (min-resolution: 2x) { .wp-block-query.is-density-aware { color: yellow; } } }',
        ['safari' => 15, 'firefox' => 10]
    ),
    'resolutionEqualityPrefixFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (resolution = 2dppx) { .wp-block-query.is-density-exact { color: yellow; } } }',
        ['safari' => 15, 'firefox' => 10]
    ),
    'resolutionEqualityModernSyntax' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (resolution = 2e0dppx) { .wp-block-query.is-density-modern-exact { color: yellow; } } }',
        [
            'safari' => 15,
            'firefox' => 10,
            'exclude' => ['MediaRangeSyntax'],
        ]
    ),
    'resolutionRangePrefixModernSyntax' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (resolution >= 2dppx) { .wp-block-query.is-density-modern-range { color: yellow; } } }',
        [
            'safari' => 15,
            'firefox' => 10,
            'exclude' => ['MediaRangeSyntax'],
        ]
    ),
    'mixedResolutionEqualityMinFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (resolution = 2dppx) and (min-resolution: 3dppx) { .wp-block-query.is-density-exact-min { color: yellow; } } }',
        ['safari' => 15, 'firefox' => 10]
    ),
    'mixedResolutionEqualityMinModernSyntax' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (resolution = 2dppx) and (min-resolution: 3dppx) { .wp-block-query.is-density-modern-exact-min { color: yellow; } } }',
        [
            'safari' => 15,
            'firefox' => 10,
            'exclude' => ['MediaRangeSyntax'],
        ]
    ),
    'xResolutionIntervalPrefixFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (0.5x <= resolution <= 1.5x) { .wp-block-query.is-low-density-window { color: yellow; } } }',
        ['safari' => 15, 'firefox' => 10]
    ),
    'mixedEnvResolutionPrefixFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (min-resolution: 2dppx) and (min-resolution: env(--wp-density-floor)) { .wp-block-query.is-density-env-window { color: yellow; } } }',
        ['safari' => 15, 'firefox' => 10]
    ),
    'numericCalcRangeFallback' => $prefixer->prefixForTargets(
        <<<'CSS'
@layer theme.blocks {
  @media (-webkit-device-pixel-ratio >= calc(1 + 1)) {
    .wp-block-query.is-density-calc {
      color: yellow;
    }
  }

  @media (1 <= -moz-device-pixel-ratio <= calc(1 + 1)) {
    .wp-block-query.is-density-calc-window {
      color: yellow;
    }
  }
}
CSS,
        ['firefox' => 60]
    ),
    'mathFunctionRangeFallback' => $prefixer->prefixForTargets(
        <<<'CSS'
@layer theme.blocks {
  @media (width > max(10px, 20px)) {
    .wp-block-query.is-math-function-wide {
      color: yellow;
    }
  }

  @media (width >= clamp(10px, 15px, 20px)) {
    .wp-block-query.is-math-function-clamp {
      color: yellow;
    }
  }
}
CSS,
        ['firefox' => 60]
    ),
    'calcMultiplicativeRangeFallback' => $prefixer->prefixForTargets(
        <<<'CSS'
@layer theme.blocks {
  @media (width >= calc(2 * 3px)) {
    .wp-block-query.is-calc-product {
      color: yellow;
    }
  }

  @media (width >= calc(6px / 2)) {
    .wp-block-query.is-calc-quotient {
      color: yellow;
    }
  }

  @media (width > max(1, 2)) {
    .wp-block-query.is-unitless-math {
      color: yellow;
    }
  }
}
CSS,
        ['firefox' => 60]
    ),
    'signFunctionRangeFallback' => $prefixer->prefixForTargets(
        <<<'CSS'
@layer theme.blocks {
  @media (width >= sign(10px)) {
    .wp-block-query.is-sign-wide {
      color: yellow;
    }
  }

  @media (width >= abs(-2)) {
    .wp-block-query.is-unitless-abs {
      color: yellow;
    }
  }

  @media (width >= hypot(3, 4)) {
    .wp-block-query.is-unitless-hypot {
      color: yellow;
    }
  }

  @media (10px <= width <= sign(20px)) {
    .wp-block-query.is-sign-window {
      color: yellow;
    }
  }

  @media (theme-breakpoint >= sign(10rem)) {
    .wp-block-query.is-sign-theme-breakpoint {
      color: yellow;
    }
  }
}
CSS,
        ['firefox' => 60]
    ),
    'negatedRangeGroup' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media not ((100px <= width <= 200px) or (hover)) { .wp-block-query.is-not-compact-hover { color: yellow; } } }',
        ['firefox' => 85]
    ),
    'negatedIntervalWithHover' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (hover) and (not (200px <= width < 500px)) { .wp-block-query.is-not-middle-hover { color: yellow; } } }',
        ['chrome' => 95]
    ),
    'upstreamIntervalPrefixFallbacks' => $prefixer->prefixForTargets(
        <<<'CSS'
@layer theme.blocks {
  @media not (100px <= width <= 200px) {
    .wp-block-query.is-not-compact-range {
      color: yellow;
    }
  }

  @media (hover) and (100px <= width <= 200px) {
    .wp-block-query.is-hover-compact-range {
      color: yellow;
    }
  }

  @media (100px < width < 200px) {
    .wp-block-query.is-open-range {
      color: yellow;
    }
  }

  @media not (100px < width < 200px) {
    .wp-block-query.is-not-open-range {
      color: yellow;
    }
  }

  @media (200px >= width >= 100px) {
    .wp-block-query.is-descending-range {
      color: yellow;
    }
  }

  @media (color > 2) {
    .wp-block-query.is-rich-color {
      color: yellow;
    }
  }

  @media (color < 2) {
    .wp-block-query.is-low-color {
      color: yellow;
    }
  }
}
CSS,
        ['include' => ['MediaRangeSyntax', 'MediaIntervalSyntax']]
    ),
    'scientificRangeFallbacks' => $prefixer->prefixForTargets(
        <<<'CSS'
@layer theme.blocks {
  @media (width >= 1e3px) {
    .wp-block-query.is-scientific-wide {
      color: yellow;
    }
  }

  @media (1e2px <= width <= 2e2px) {
    .wp-block-query.is-scientific-window {
      color: yellow;
    }
  }

  @media (aspect-ratio >= 16e0 / 9e0) {
    .wp-block-query.is-scientific-ratio {
      color: yellow;
    }
  }

  @media (min-resolution: 2e0dppx) {
    .wp-block-query.is-scientific-density {
      color: yellow;
    }
  }

  @media (theme-breakpoint >= 1e2px) {
    .wp-block-query.is-scientific-token {
      color: yellow;
    }
  }
}
CSS,
        ['safari' => 15, 'firefox' => 10]
    ),
    'caseSensitiveCustomRangeFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (Theme-Breakpoint >= 2) and (--WP-Breakpoint >= 3) { .wp-block-query.is-custom-case { color: yellow; } } }',
        ['firefox' => 60]
    ),
    'negatedCaseSensitiveCustomRangeFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media not (Theme-Breakpoint >= 2) { .wp-block-query.is-custom-case-not { color: yellow; } } }',
        ['firefox' => 60]
    ),
    'caseSensitiveCustomRangeModern' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media Speech and (--WP-Breakpoint >= 2) { .wp-block-query.is-custom-speech { color: yellow; } } }',
        [
            'firefox' => 60,
            'exclude' => ['MediaRangeSyntax'],
        ]
    ),
    'negatedCaseSensitiveCustomRangeModern' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media Speech and (not (--WP-Breakpoint >= 3)) { .wp-block-query.is-custom-speech-not { color: yellow; } } }',
        [
            'firefox' => 60,
            'exclude' => ['MediaRangeSyntax'],
        ]
    ),
    'trailingCommaRangeList' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (width >= 240px), { .wp-block-query.is-trailing-comma { color: yellow; } } }',
        ['firefox' => 60]
    ),
    'explicitNotCondition' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media screen and not (color) { .wp-block-query.is-explicit-not-condition { color: yellow; } } }',
        ['firefox' => 60]
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
        '@layer theme.blocks { @media (color >= calc(1 + 1)) { .wp-block-query { color: chartreuse; } } }',
        ['firefox' => 60]
    );
    $actual['invalidIntegerCalcGuard'] = 'missing';
} catch (InvalidArgumentException) {
    $actual['invalidIntegerCalcGuard'] = 'invalid-media-query';
}

try {
    $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (resolution >= calc(1 + 1dppx)) { .wp-block-query { color: chartreuse; } } }',
        ['firefox' => 60]
    );
    $actual['invalidResolutionCalcGuard'] = 'missing';
} catch (InvalidArgumentException) {
    $actual['invalidResolutionCalcGuard'] = 'invalid-media-query';
}

try {
    $prefixer->prefixForTargets(
        '@layer theme.blocks { @media (width >= sign(10%)) { .wp-block-query { color: chartreuse; } } }',
        ['firefox' => 60]
    );
    $actual['invalidSignFunctionGuard'] = 'missing';
} catch (InvalidArgumentException) {
    $actual['invalidSignFunctionGuard'] = 'invalid-media-query';
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

try {
    $prefixer->prefixForTargets(
        '@layer theme.blocks { @media all and all { .wp-block-query { color: chartreuse; } } }',
        ['firefox' => 60]
    );
    $actual['invalidExplicitConditionGuard'] = 'missing';
} catch (InvalidArgumentException) {
    $actual['invalidExplicitConditionGuard'] = 'invalid-media-query';
}

try {
    $prefixer->prefixForTargets(
        '@layer theme.blocks { @media , { .wp-block-query { color: chartreuse; } } }',
        ['firefox' => 60]
    );
    $actual['emptyMediaListGuard'] = 'missing';
} catch (InvalidArgumentException) {
    $actual['emptyMediaListGuard'] = 'invalid-media-query';
}

$expected = [
    'safari15' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query{color:#7fff00}}@media (width:320px){.wp-block-query.is-exact-width{color:#ff0}}@media not screen and not (min-width:240px){.wp-block-query.is-print-narrow{color:#ff0}}@media screen and not (max-width:max(10px,1rem)){.wp-block-query.is-fluid-breakpoint{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (-webkit-min-device-pixel-ratio:2),(color) and (min-resolution:2dppx){.wp-block-query.is-density-aware{color:#ff0}}@media (min-aspect-ratio:16/9) and (not (max-color-index:2)){.wp-block-query.is-wide-color{color:#ff0}}@media (min-theme-breakpoint:2){.wp-block-query.is-custom-breakpoint{color:#ff0}}@media (theme-state:expanded){.wp-block-query.is-expanded-state{color:#ff0}}}',
    'firefox60' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query{color:#7fff00}}@media (width:320px){.wp-block-query.is-exact-width{color:#ff0}}@media not screen and not (min-width:240px){.wp-block-query.is-print-narrow{color:#ff0}}@media screen and not (max-width:max(10px,1rem)){.wp-block-query.is-fluid-breakpoint{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (min-resolution:2dppx){.wp-block-query.is-density-aware{color:#ff0}}@media (min-aspect-ratio:16/9) and (not (max-color-index:2)){.wp-block-query.is-wide-color{color:#ff0}}@media (min-theme-breakpoint:2){.wp-block-query.is-custom-breakpoint{color:#ff0}}@media (theme-state:expanded){.wp-block-query.is-expanded-state{color:#ff0}}}',
    'firefox64' => '@layer theme.blocks{@media (width>=240px){.wp-block-query{color:#7fff00}}@media (width=320px){.wp-block-query.is-exact-width{color:#ff0}}@media not screen and (width<240px){.wp-block-query.is-print-narrow{color:#ff0}}@media screen and (width>max(10px,1rem)){.wp-block-query.is-fluid-breakpoint{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (resolution>=2x){.wp-block-query.is-density-aware{color:#ff0}}@media (aspect-ratio>=16/9) and (color-index>2){.wp-block-query.is-wide-color{color:#ff0}}@media (theme-breakpoint>=2){.wp-block-query.is-custom-breakpoint{color:#ff0}}@media (theme-state=expanded){.wp-block-query.is-expanded-state{color:#ff0}}}',
    'firefox85' => '@layer theme.blocks{@media (width>=240px){.wp-block-query{color:#7fff00}}@media (width=320px){.wp-block-query.is-exact-width{color:#ff0}}@media not screen and (width<240px){.wp-block-query.is-print-narrow{color:#ff0}}@media screen and (width>max(10px,1rem)){.wp-block-query.is-fluid-breakpoint{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (resolution>=2x){.wp-block-query.is-density-aware{color:#ff0}}@media (aspect-ratio>=16/9) and (color-index>2){.wp-block-query.is-wide-color{color:#ff0}}@media (theme-breakpoint>=2){.wp-block-query.is-custom-breakpoint{color:#ff0}}@media (theme-state=expanded){.wp-block-query.is-expanded-state{color:#ff0}}}',
    'chrome95' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query{color:#7fff00}}@media (width:320px){.wp-block-query.is-exact-width{color:#ff0}}@media not screen and not (min-width:240px){.wp-block-query.is-print-narrow{color:#ff0}}@media screen and not (max-width:max(10px,1rem)){.wp-block-query.is-fluid-breakpoint{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (min-resolution:2x){.wp-block-query.is-density-aware{color:#ff0}}@media (min-aspect-ratio:16/9) and (not (max-color-index:2)){.wp-block-query.is-wide-color{color:#ff0}}@media (min-theme-breakpoint:2){.wp-block-query.is-custom-breakpoint{color:#ff0}}@media (theme-state:expanded){.wp-block-query.is-expanded-state{color:#ff0}}}',
    'importRangeLayerTailFallback' => '@import "blocks/query.css" layer(theme.blocks) (min-width:240px);@layer theme.blocks{.wp-block-query.is-imported-range{color:#ff0}}',
    'importIntervalLayerTailFallback' => '@import "blocks/query.css" layer(theme.blocks) (min-width:100px) and (max-width:200px);@layer theme.blocks{.wp-block-query.is-imported-window{color:#ff0}}',
    'importResolutionRangeXUnitTailFallback' => '@import "blocks/density.css" layer(theme.blocks) (min-resolution:2x);@layer theme.blocks{.wp-block-query.is-imported-density{color:#ff0}}',
    'safari163RangeFallback' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query.is-range-boundary{color:#ff0}}}',
    'safari164RangeModern' => '@layer theme.blocks{@media (width>=240px){.wp-block-query.is-range-boundary{color:#ff0}}}',
    'firefox62RangeFallback' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query.is-firefox-range-boundary{color:#ff0}}}',
    'firefox63RangeModern' => '@layer theme.blocks{@media (width>=240px){.wp-block-query.is-firefox-range-boundary{color:#ff0}}}',
    'opera70RangeFallback' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query.is-opera-range-boundary{color:#ff0}}}',
    'opera71RangeModern' => '@layer theme.blocks{@media (width>=240px){.wp-block-query.is-opera-range-boundary{color:#ff0}}}',
    'firefox101IntervalFallback' => '@layer theme.blocks{@media (min-width:100px) and (max-width:200px){.wp-block-query.is-firefox-window-boundary{color:#ff0}}}',
    'firefox102IntervalModern' => '@layer theme.blocks{@media (100px<=width<=200px){.wp-block-query.is-firefox-window-boundary{color:#ff0}}}',
    'opera70IntervalFallback' => '@layer theme.blocks{@media (min-width:100px) and (max-width:200px){.wp-block-query.is-opera-window-boundary{color:#ff0}}}',
    'opera71IntervalModern' => '@layer theme.blocks{@media (100px<=width<=200px){.wp-block-query.is-opera-window-boundary{color:#ff0}}}',
    'chrome28ResolutionPrefixBoundary' => '@layer theme.blocks{@media (-webkit-min-device-pixel-ratio:2),(min-resolution:2dppx){.wp-block-query.is-density-boundary{color:#ff0}}}',
    'firefox30ResolutionNoPrefixBoundary' => '@layer theme.blocks{@media (min-resolution:2dppx){.wp-block-query.is-density-boundary{color:#ff0}}}',
    'forcedRangeFallback' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query{color:#7fff00}}@media (width:320px){.wp-block-query.is-exact-width{color:#ff0}}@media not screen and not (min-width:240px){.wp-block-query.is-print-narrow{color:#ff0}}@media screen and not (max-width:max(10px,1rem)){.wp-block-query.is-fluid-breakpoint{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-style-featured{color:#ff0}}@media (color) and (min-resolution:2dppx){.wp-block-query.is-density-aware{color:#ff0}}@media (min-aspect-ratio:16/9) and (not (max-color-index:2)){.wp-block-query.is-wide-color{color:#ff0}}@media (min-theme-breakpoint:2){.wp-block-query.is-custom-breakpoint{color:#ff0}}@media (theme-state:expanded){.wp-block-query.is-expanded-state{color:#ff0}}}',
    'unitlessLengthRangeFallback' => '@layer theme.blocks{@media (min-width:2px){.wp-block-query.is-unitless-width{color:#ff0}}}',
    'unitlessLengthIntervalModern' => '@layer theme.blocks{@media (2px<=width<=4px){.wp-block-query.is-unitless-width-window{color:#ff0}}}',
    'compoundResolutionRange' => '@layer theme.blocks{@media (-webkit-min-device-pixel-ratio:2) and (-webkit-max-device-pixel-ratio:3),(min--moz-device-pixel-ratio:2) and (max--moz-device-pixel-ratio:3),(min-resolution:2dppx) and (max-resolution:3dppx){.wp-block-query.is-density-window{color:#ff0}}}',
    'fractionalResolutionRange' => '@layer theme.blocks{@media (min-resolution:.5dppx) and (max-resolution:1.5dppx){.wp-block-query.is-low-density-window{color:#ff0}}}',
    'modernFractionalResolutionRangeXUnit' => '@layer theme.blocks{@media (.5x<=resolution<=1.5x){.wp-block-query.is-low-density-window{color:#ff0}}}',
    'xResolutionLegacyUnit' => '@layer theme.blocks{@media (resolution:1dppx){.wp-block-query.is-density-aware{background:red}}}',
    'xResolutionPrefixFallback' => '@layer theme.blocks{@media (-webkit-min-device-pixel-ratio:2),(min--moz-device-pixel-ratio:2),(min-resolution:2dppx){.wp-block-query.is-density-aware{color:#ff0}}}',
    'resolutionEqualityPrefixFallback' => '@layer theme.blocks{@media (-webkit-device-pixel-ratio:2),(-moz-device-pixel-ratio:2),(resolution:2dppx){.wp-block-query.is-density-exact{color:#ff0}}}',
    'resolutionEqualityModernSyntax' => '@layer theme.blocks{@media (-webkit-device-pixel-ratio=2),(-moz-device-pixel-ratio=2),(resolution=2dppx){.wp-block-query.is-density-modern-exact{color:#ff0}}}',
    'resolutionRangePrefixModernSyntax' => '@layer theme.blocks{@media (-webkit-device-pixel-ratio>=2),(-moz-device-pixel-ratio>=2),(resolution>=2dppx){.wp-block-query.is-density-modern-range{color:#ff0}}}',
    'mixedResolutionEqualityMinFallback' => '@layer theme.blocks{@media (-webkit-device-pixel-ratio:2) and (-webkit-min-device-pixel-ratio:3),(-moz-device-pixel-ratio:2) and (min--moz-device-pixel-ratio:3),(resolution:2dppx) and (min-resolution:3dppx){.wp-block-query.is-density-exact-min{color:#ff0}}}',
    'mixedResolutionEqualityMinModernSyntax' => '@layer theme.blocks{@media (-webkit-device-pixel-ratio=2) and (-webkit-device-pixel-ratio>=3),(-moz-device-pixel-ratio=2) and (-moz-device-pixel-ratio>=3),(resolution=2dppx) and (resolution>=3dppx){.wp-block-query.is-density-modern-exact-min{color:#ff0}}}',
    'xResolutionIntervalPrefixFallback' => '@layer theme.blocks{@media (min-resolution:.5dppx) and (max-resolution:1.5dppx){.wp-block-query.is-low-density-window{color:#ff0}}}',
    'mixedEnvResolutionPrefixFallback' => '@layer theme.blocks{@media (-webkit-min-device-pixel-ratio:2) and (min-resolution:env(--wp-density-floor)),(min--moz-device-pixel-ratio:2) and (min-resolution:env(--wp-density-floor)),(min-resolution:2dppx) and (min-resolution:env(--wp-density-floor)){.wp-block-query.is-density-env-window{color:#ff0}}}',
    'numericCalcRangeFallback' => '@layer theme.blocks{@media (-webkit-min-device-pixel-ratio:2){.wp-block-query.is-density-calc{color:#ff0}}@media (min--moz-device-pixel-ratio:1) and (max--moz-device-pixel-ratio:2){.wp-block-query.is-density-calc-window{color:#ff0}}}',
    'mathFunctionRangeFallback' => '@layer theme.blocks{@media not (max-width:20px){.wp-block-query.is-math-function-wide{color:#ff0}}@media (min-width:15px){.wp-block-query.is-math-function-clamp{color:#ff0}}}',
    'calcMultiplicativeRangeFallback' => '@layer theme.blocks{@media (min-width:6px){.wp-block-query.is-calc-product{color:#ff0}}@media (min-width:3px){.wp-block-query.is-calc-quotient{color:#ff0}}@media not (max-width:2){.wp-block-query.is-unitless-math{color:#ff0}}}',
    'signFunctionRangeFallback' => '@layer theme.blocks{@media (min-width:1){.wp-block-query.is-sign-wide{color:#ff0}}@media (min-width:2){.wp-block-query.is-unitless-abs{color:#ff0}}@media (min-width:5){.wp-block-query.is-unitless-hypot{color:#ff0}}@media (min-width:10px) and (max-width:1){.wp-block-query.is-sign-window{color:#ff0}}@media (min-theme-breakpoint:1){.wp-block-query.is-sign-theme-breakpoint{color:#ff0}}}',
    'negatedRangeGroup' => '@layer theme.blocks{@media not (((min-width:100px) and (max-width:200px)) or (hover)){.wp-block-query.is-not-compact-hover{color:#ff0}}}',
    'negatedIntervalWithHover' => '@layer theme.blocks{@media (hover) and (not ((min-width:200px) and (not (min-width:500px)))){.wp-block-query.is-not-middle-hover{color:#ff0}}}',
    'upstreamIntervalPrefixFallbacks' => '@layer theme.blocks{@media not ((min-width:100px) and (max-width:200px)){.wp-block-query.is-not-compact-range{color:#ff0}}@media (hover) and (min-width:100px) and (max-width:200px){.wp-block-query.is-hover-compact-range{color:#ff0}}@media (not (max-width:100px)) and (not (min-width:200px)){.wp-block-query.is-open-range{color:#ff0}}@media not ((not (max-width:100px)) and (not (min-width:200px))){.wp-block-query.is-not-open-range{color:#ff0}}@media (max-width:200px) and (min-width:100px){.wp-block-query.is-descending-range{color:#ff0}}@media not (max-color:2){.wp-block-query.is-rich-color{color:#ff0}}@media not (min-color:2){.wp-block-query.is-low-color{color:#ff0}}}',
    'scientificRangeFallbacks' => '@layer theme.blocks{@media (min-width:1000px){.wp-block-query.is-scientific-wide{color:#ff0}}@media (min-width:100px) and (max-width:200px){.wp-block-query.is-scientific-window{color:#ff0}}@media (min-aspect-ratio:16/9){.wp-block-query.is-scientific-ratio{color:#ff0}}@media (-webkit-min-device-pixel-ratio:2),(min--moz-device-pixel-ratio:2),(min-resolution:2dppx){.wp-block-query.is-scientific-density{color:#ff0}}@media (min-theme-breakpoint:100px){.wp-block-query.is-scientific-token{color:#ff0}}}',
    'caseSensitiveCustomRangeFallback' => '@layer theme.blocks{@media (min-Theme-Breakpoint:2) and (min---WP-Breakpoint:3){.wp-block-query.is-custom-case{color:#ff0}}}',
    'negatedCaseSensitiveCustomRangeFallback' => '@layer theme.blocks{@media not (min-Theme-Breakpoint:2){.wp-block-query.is-custom-case-not{color:#ff0}}}',
    'caseSensitiveCustomRangeModern' => '@layer theme.blocks{@media Speech and (--WP-Breakpoint>=2){.wp-block-query.is-custom-speech{color:#ff0}}}',
    'negatedCaseSensitiveCustomRangeModern' => '@layer theme.blocks{@media Speech and (--WP-Breakpoint<3){.wp-block-query.is-custom-speech-not{color:#ff0}}}',
    'trailingCommaRangeList' => '@layer theme.blocks{@media (min-width:240px){.wp-block-query.is-trailing-comma{color:#ff0}}}',
    'explicitNotCondition' => '@layer theme.blocks{@media screen and not (color){.wp-block-query.is-explicit-not-condition{color:#ff0}}}',
    'invalidRangeGuard' => 'invalid-media-query',
    'invalidIntervalGuard' => 'invalid-media-query',
    'invalidFunctionGuard' => 'invalid-media-query',
    'invalidRangeValueGuard' => 'invalid-media-query',
    'invalidIntegerCalcGuard' => 'invalid-media-query',
    'invalidResolutionCalcGuard' => 'invalid-media-query',
    'invalidSignFunctionGuard' => 'invalid-media-query',
    'missingAndGuard' => 'invalid-media-query',
    'invalidExplicitConditionGuard' => 'invalid-media-query',
    'emptyMediaListGuard' => 'invalid-media-query',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected media range layer prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
