# LightningCSS Media Query Redundant Negation Layer Parity

Micro-slice: `lightningcss-media-query-range-layer-parity-20260531T210348Z`

Accepted base: `7a6ad881ab7ec5dade7133aeca014b7a5e54577c`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine source:
  - `src/media_query.rs::parse_query_condition()` and `parse_paren_block()`, where redundant parenthesized conditions below `not` parse down to the inner media feature.
  - `src/media_query.rs::MediaCondition::to_css()` and `QueryFeature::negate()`, where `not` around a range feature serializes the negated comparison rather than preserving redundant wrappers.

## Native PHP Delta

- `MediaQueryParser` now strips redundant nested parentheses inside a `not (...)` condition until a real top-level `and` / `or` boundary is reached.
- Simple range negation then sees the unwrapped range feature, so `not (((width < 240px)))` minifies to `(width>=240px)` and lowers to `(min-width:240px)` for legacy range targets.
- Boolean operation wrappers such as `not (((color) or (hover)))` still keep the operation wrapper shape.
- `wordpress-media-layer-minifier.php` now exercises deeply parenthesized negated ranges inside a block-theme `@layer`.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php`
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
- `php -l lanes/lightningcss/examples/wordpress-media-layer-minifier.php`
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - `1 test files, 283 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php lanes/lightningcss/tests/CssMinifierTest.php`
  - `3 test files, 2452 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test`
  - Exits 0 and emits the expected layered media CSS.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - First attempt hit unrelated `Disk quota exceeded` failures in `/tmp` while `CssBundlerTest.php` wrote temporary fixtures.
- `TMPDIR=/home/claude/port-libs/.tmux-team/tmp/lightningcss-media-query-210348-tmp php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4313 assertions, 0 failures`

## Status Delta

- Focused PHP assertion delta: `+5` in `MediaQueryParserTest.php`.
- Conservative mapped coverage remains `2100 / 3532`; this deepens the already represented `src/media_query.rs` media range/layer serialization cluster.

## Non-Overlap

- Does not repeat accepted simple negated range normalization, double-negation collapse, condition-function rejection, typed/unknown/equality ranges, media range include/exclude flags, resolution prefixing, media import graph conjunctions, CSSOM, SourceMap, CSS Modules, target-prefixing, or property/value work.
- This slice is only redundant parenthesis stripping below `not` before range negation in layered media query serialization.

## Dependency Closure

- No new support component is needed. The slice reuses the native `MediaQueryParser`, `CssMinifier`, `TransitionPrefixer`, and existing lane-local example path.
- No Node, Rust, WASM, browser engine, external CSS parser, or shared support-library activation is required.

Root harness status: not run - isolated micro-slice.
