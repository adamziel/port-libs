# Media Query Escaped Range Layer Parity - 2026-05-31 21:18 UTC

Slice: `lightningcss-media-query-range-layer-parity-20260531T205235Z`

Base: `4cd5c83f2f1b57c5b3e318d737d8c94ee34892b6`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source:
  - `src/media_query.rs::MediaFeatureName::parse` parses media feature names as CSS identifiers, including escape sequences.
  - `src/media_query.rs::MediaFeatureValue::parse_unknown` accepts identifier values for unknown features.
  - `src/media_query.rs::MediaQuery::to_css` and the range fallback helpers serialize decoded media types, feature names, and identifier values.
- Focused upstream native-addon probes at the pinned cache confirmed:
  - `@media (w\69 dth >= 240px)` serializes as `@media (width>=240px)`.
  - `@media (theme\2d breakpoint >= 2)` serializes as `@media (theme-breakpoint>=2)`.
  - Firefox 60 target fallback lowers escaped layered ranges to `screen and (min-width:240px)`, `(min-theme-breakpoint:2)`, and `(theme-state:expanded)`.

## Native PHP Change

- `MediaQueryParser` now decodes CSS identifier escapes in media feature names, explicit media types, and unknown identifier values before serialization.
- Range lowering and target fallback now use the decoded canonical feature name, so escaped `width` and escaped custom breakpoint names produce the same legacy `min-` / `max-` fallbacks as upstream.
- Validation keeps the existing guard behavior but allows safe escaped identifier values in unknown media equality/range features.
- Added a WordPress block-theme smoke covering escaped media type, width range, custom breakpoint range, and custom state equality inside `@layer theme.blocks`.

## Red-First Evidence

- Before implementation, focused probes showed PHP preserved raw escapes in media output:
  - `@media (w\69 dth >= 240px)` became `@media (w\69dth>=240px)`.
  - Layered Firefox 60 fallback preserved escaped custom names instead of emitting decoded `min-theme-breakpoint` / `theme-state`.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - `1 test files, 282 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 633 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-escaped-range-layer.php --self-test`
  - Exited 0 and printed legacy/modern escaped range-layer output.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4278 assertions, 0 failures`
- Syntax:
  - `php -l lanes/lightningcss/src/MediaQueryParser.php`
  - `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
  - `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-media-escaped-range-layer.php`
- Whitespace:
  - `git diff --check -- lanes/lightningcss`

## Coverage Delta

- PHP assertion delta: `+16` (`4262 -> 4278`).
- Conservative mapped coverage: unchanged at `2093 / 3532`; this deepens the existing upstream media-query range/layer cluster rather than adding a new manifest denominator row.

## Non-Overlap

- Does not repeat accepted typed/unknown/equality media ranges, vendor pixel-ratio/resolution prefixing, boolean group serialization, `all` media elision, value-function guards, import graph, source-map, CSS Modules, CSSOM, visitor, or property-value slices.
- The stale 2026-05-25 custom-media rework note targets an old import-tail conflict and does not overlap this current-base media identifier escape behavior.

## Dependency Closure

- No new support component is needed. The slice reuses the native PHP media parser, minifier, and transition prefixer paths.

## Next Task

- Continue non-overlapping media-query parity around parser recovery, container/media condition serialization, or target fallback edges not already covered by range lowering and escaped identifier canonicalization.
