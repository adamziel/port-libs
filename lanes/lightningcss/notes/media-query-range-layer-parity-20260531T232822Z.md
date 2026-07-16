# Media Query Range Layer Parity - 2026-05-31T23:28Z

Lane: lightningcss
Micro-slice: lightningcss-media-query-range-layer-parity-20260531T232822Z
Base accepted HEAD: afee0853cdadd52fa12dbc1e24d633ac7329910c

## Source Truth

Pinned upstream source is parcel-bundler/lightningcss at
22bdda3d190f1cd321d98026225cfc964af64ad9. This slice cites
`src/lib.rs::test_media` prefix helper behavior for interval media ranges:

- `not (100px <= width <= 200px)`
- `(hover) and (100px <= width <= 200px)`
- `(100px < width < 200px)`
- `not (100px < width < 200px)`
- `(200px >= width >= 100px)`
- `(color > 2)`
- `(color < 2)`

Upstream lowers these for legacy targets to `min-`/`max-` feature fallback
forms, with non-inclusive bounds represented as negated inclusive max/min
features. The PHP port already had the core lowering path; this handoff adds
focused parser, prefixer, and WordPress-layer smoke evidence for those exact
upstream cases inside cascade layers.

## Implemented Coverage

- `MediaQueryParserTest.php` now checks bare integer color range lowering and
  negated/open interval range fallback serialization.
- `TransitionPrefixerTest.php` now verifies the same upstream interval cases
  when nested under `@layer` and legacy target flags.
- `wordpress-media-range-layer-prefixer.php` now includes a self-test case for
  layered WordPress block CSS using those interval and color range fallbacks.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json` record conservative
  mapped coverage `2198 -> 2205 / 3532`.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - `1 test files, 332 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 734 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - exits 0
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4833 assertions, 0 failures`

Full upstream Rust, Node, and WASM runners were not executed for this isolated
micro-slice.

## Non-Overlap

This avoids the accepted 89903d30c04a batch for CSS Modules state/highlight
composition, CSSOM border-spacing, custom at-rule variable-exit visitors, and
text-decoration longhand target-prefix boundaries. It also avoids the older
accepted media-query conjunction and redundant nested-negation range/layer
clusters by mapping distinct upstream interval range helper cases.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
`MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, and layer minification
paths; the activation gate is the focused PHP parser/prefixer/example evidence
above plus the full LightningCSS lane PHP gate.
