# Media Query Range Layer Parity - 2026-05-31 18:02 UTC

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source:
  - `src/media_query.rs::MediaList::transform_resolution()` clones prefixed query variants.
  - `src/media_query.rs::MediaCondition::transform_resolution()` recursively visits `Not` and `Operation` conditions, so every `resolution` range inside one media query is rewritten for the vendor-prefixed variant.
  - `src/lib.rs::test_media` resolution-prefix helpers cover `min-resolution`, `resolution >`, `dpi`, `dpcm`, media-list, and media-type-prefixed resolution rewrites. This slice deepens that cluster for multiple resolution ranges inside one layered query.

## Native PHP Change

- `TransitionPrefixer::resolutionMediaQueryVariants()` now collects all lowered `min-resolution` / `max-resolution` conditions in a query and rewrites every collected condition for each `-webkit-` and `-moz-` variant.
- Replacements are applied from right to left so captured offsets remain stable while multiple conditions are replaced.
- Added focused layer-scoped coverage for:
  - `(min-resolution: 2dppx) and (max-resolution: 3dppx)` producing both prefixed bounds in each vendor variant.
  - `(resolution > 2dppx) and (resolution < 4dppx)` lowering to parenthesized negated min/max forms and rewriting both bounds in the WebKit variant.
- Updated the WordPress media range/layer smoke with a compound density-window query for block styles.

## Evidence

- Red-first before implementation:
  - `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Failed `transition prefixer maps upstream resolution media prefixes inside layers`: only the first resolution condition was prefixed.
- Focused after implementation:
  - `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 427 assertions, 0 failures`
  - `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `2 test files, 574 assertions, 0 failures`
- Full lane:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 2827 assertions, 0 failures`
- Example:
  - `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Exits 0 and emits 10 checked output lines.
- Syntax:
  - `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`

## Non-Overlap

- Does not repeat accepted media range lowering, typed range validation, all-media elision, x-resolution unit serialization, single-resolution prefixing, or layer import validation.
- This is a bounded recursive-resolution-prefix fix for compound media conditions inside cascade layers.

## Dependency Closure

- No new support component is needed. The slice reuses the native `CssMinifier`, `MediaQueryParser`, `TransitionPrefixer`, target-boundary helpers, and existing top-level CSS scanners.

## Next Task

- Continue non-overlapping media-query work around remaining parser recovery, container/media condition serialization, or target-boundary cases not already covered by range fallback, all-media elision, x-resolution units, or compound resolution-prefix rewrites.
