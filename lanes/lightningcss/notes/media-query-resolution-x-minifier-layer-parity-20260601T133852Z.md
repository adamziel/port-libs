# Media Query Resolution X-Unit Minifier Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T133852Z`

Base accepted HEAD: `f2475a9a46461fb108ebd2437efe777168da2710`

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Oracle used: upstream native addon
  `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`
- Source references: upstream `src/media_query.rs` media query printer and `src/lib.rs::test_media`.

The upstream minifier serializes default resolution media feature values with
the shorter `x` unit:

- `@media (resolution >= 2dppx)` -> `@media (resolution>=2x)`
- `@media (min-resolution: 2dppx)` -> `@media (resolution>=2x)`
- `@media (2dppx <= resolution <= 3dppx)` -> `@media (2x<=resolution<=3x)`
- `@media (resolution = 2dppx)` -> `@media (resolution=2x)`
- `@import "blocks/density.css" layer(theme.blocks) (min-resolution:2dppx);` -> `@import "blocks/density.css" layer(theme.blocks) (resolution>=2x);`

## Implementation

`CssMinifier` now normalizes media query output through
`MediaQueryParser::useXResolutionUnitList()` for both top-level `@media`
preludes and `@import` media tails. This keeps the default minifier aligned
with upstream x-unit serialization while preserving target-prefixer behavior
that can still lower unsupported resolution ranges back to `dppx` fallbacks for
legacy browser targets.

The focused PHP coverage exercises top-level ranges, min/max aliases, interval
ranges, open intervals, equality, negated ranges, layered `@media`, and
layered `@import` media tails.

## Verification

- `php -l lanes/lightningcss/src/CssMinifier.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php` -> no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php` -> `1 test files, 636 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 1302 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 2015 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 778 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test` -> passed
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8079 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` -> passed

Status delta: `phpPass` moves `8070 -> 8079`. Mapped coverage remains
`2393 / 3532` because this deepens the existing media-query range/layer
coverage cluster instead of adding a new upstream inventory row.

## Non-Overlap

This does not repeat accepted media range target fallback, typed range
fallback, import media range tail parsing, invalid percentage recovery,
resolution target-prefix fallback, or target-specific `dppx` fallback work.
The new behavior is bounded to default minifier serialization of resolution
feature ranges and import media tails with upstream `x` units.

## Dependency Closure

No new support component is needed. The implementation reuses the existing
native PHP media query parser and minifier pipeline.
