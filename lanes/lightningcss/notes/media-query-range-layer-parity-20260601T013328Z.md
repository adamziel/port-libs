# Media Query Scientific Range Layer Parity 2026-06-01

Slice: `lightningcss-media-query-range-layer-parity-20260601T013328Z`

Base accepted HEAD: `e0cca2a185669ab1c0c1e83b7ad9894e29901028`

Pinned upstream source: `/home/claude/port-libs/.upstream-cache/lightningcss`
at `22bdda3d190f1cd321d98026225cfc964af64ad9`.

## Source Truth

Upstream `src/media_query.rs` parses media range values through
`MediaFeatureValue::parse`: length, number, resolution, and ratio values use
CSS numeric parsers, while integer-only media features use `CSSInteger`.
Native addon probes at the pinned checkout confirmed:

- `(width >= 1e3px)` minifies to `(width>=1000px)`.
- `(width >= 1e-7px)` preserves the small exponent as `(width>=1e-7px)`.
- `(1e2px <= width <= 2e2px)` minifies to `(100px<=width<=200px)`.
- `(aspect-ratio >= 16e0 / 9e0)` minifies to `(aspect-ratio>=16/9)`.
- `(resolution >= 2e0dppx)` is accepted and normalizes through resolution
  prefix fallbacks.
- `(color >= 1e0)` is rejected as an invalid media query because `color` is
  integer-typed upstream.

## Change

`MediaQueryParser` now accepts CSS scientific-notation numbers in media-query
range values for length-like, ratio, resolution, unknown custom, and
device-pixel-ratio feature values. Integer media features keep the existing
integer parser path and still reject exponent syntax. Target lowering and
resolution unit conversion reuse the normalized number serializer, so `@layer`
fallbacks emit ordinary decimal values.

The WordPress media range layer example now includes scientific-notation
breakpoint, interval, aspect-ratio, resolution, and custom token cases.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `2 test files, 1255 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - exits `0`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5415 assertions, 0 failures`
- `php -l` for changed PHP files
  - no syntax errors
- `git diff --check -- lanes/lightningcss`
  - exits `0`

Mapped denominator stays at `2289 / 3532`; this deepens the already represented
media-query range/layer cluster rather than adding a new upstream inventory row.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
media-query parser, transition prefixer, and example harness.

## Non-overlap

This avoids the accepted env() resolution, decimal/fractional media range,
calc media range, escaped media identifier, and repeated nested-negation media
range/layer clusters. The new behavior is specifically CSS scientific notation
inside media-query range values and their layer-preserving target fallbacks.
