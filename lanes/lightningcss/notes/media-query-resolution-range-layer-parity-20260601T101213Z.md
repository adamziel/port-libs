# LightningCSS media query resolution range layer parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T101213Z`
Base: `c6749612dc0422457ced2be6c92f03cc5e7fb148`

## Upstream source truth

Pinned upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Relevant upstream behavior:

- `src/media_query.rs::MediaCondition::get_necessary_prefixes()` requests resolution prefixes for standard `MediaFeature::Range` resolution conditions.
- `src/media_query.rs::MediaCondition::transform_resolution()` clones a whole condition into one WebKit branch, one Mozilla branch, and one original branch.
- `src/media_query.rs::QueryFeature::to_css()` lowers interval syntax only when media-range fallback is requested; interval resolution syntax is not prefixed after lowering.

Native addon probes against the pinned build:

- `@media (resolution = 2dppx) and (min-resolution: 3dppx)` with Safari 15 and Firefox 10 serializes three branches only:
  `(-webkit-device-pixel-ratio:2) and (-webkit-min-device-pixel-ratio:3)`,
  `(-moz-device-pixel-ratio:2) and (min--moz-device-pixel-ratio:3)`, and
  `(resolution:2dppx) and (min-resolution:3dppx)`.
- The same query with `exclude: MediaRangeSyntax` preserves modern range syntax in the three branches:
  `-webkit-device-pixel-ratio=2` plus `>=3`,
  `-moz-device-pixel-ratio=2` plus `>=3`, and
  original `resolution=2dppx` plus `>=3`.
- Interval syntax `0.5dppx <= resolution <= 1.5dppx` lowers to unprefixed
  `(min-resolution:.5dppx) and (max-resolution:1.5dppx)`.
- Unitless math functions preserve unitless origin through range fallback:
  `width > max(1, 2)` lowers to `not (max-width:2)`, while literal
  `width > 2` still lowers to `not (max-width:2px)`.

## Local change

Before this patch, the PHP prefixer prefixed resolution equality ranges, lowered media range syntax, and then prefixed min/max resolution conditions in a second pass. Mixed equality plus min/max conditions produced a nine-branch cross product instead of LightningCSS's one WebKit, one Mozilla, and one original branch.

The patch changes `TransitionPrefixer` to run one resolution-prefix pass before range lowering and to replace equality and min/max resolution conditions together in each cloned branch. That also preserves the upstream behavior where interval resolution syntax lowers to unprefixed min/max resolution conditions.

The patch also fixes the adjacent media range fallback marker for unitless math functions, so `max(1, 2)` keeps unitless output through the target-prefix pre-minification path.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php && php -l lanes/lightningcss/src/TransitionPrefixer.php && php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Passed, no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Passed: `2 test files, 1737 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - Passed.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Passed: `13 test files, 7336 assertions, 0 failures`.

## Non-overlap

This patch avoids source-map, bundle/import graph, CSS Modules, CSSOM read/write, custom at-rule visitor, selector, property/value, and non-media target-prefixing surfaces. It is limited to media query range/layer resolution prefix parity and the directly coupled media range math-function fallback marker.

## Dependency closure

No new runtime support component is needed. The implementation reuses the native PHP `TransitionPrefixer`, `MediaQueryParser`, and `CssMinifier`; the upstream Node native addon was used only as an oracle probe in this isolated worktree.
