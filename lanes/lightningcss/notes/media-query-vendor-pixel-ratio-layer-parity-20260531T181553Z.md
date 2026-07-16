# LightningCSS Media Query Vendor Pixel-Ratio Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T181553Z`

Base: `f239ae84229f0ac8ecc07e38ef32523b43f8024f`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source: `src/media_query.rs` `MediaFeatureName::parse`, where known `min-` / `max-` aliases are canonicalized to the underlying feature name, and `QueryFeature::to_css` / `write_min_max`, where unsupported range syntax lowers to legacy min/max feature names.
- Local pinned native-addon probes confirmed no-target minification canonicalizes `(-webkit-min-device-pixel-ratio: 2)` to `(-webkit-device-pixel-ratio>=2)`, while legacy media-range targets lower `(-webkit-device-pixel-ratio >= 2)` to `(-webkit-min-device-pixel-ratio:2)` and intervals to paired min/max legacy features.

## Red-First Evidence

Before the patch, PHP preserved legacy WebKit aliases and failed to lower modern vendor DPR ranges for range-fallback targets:

```text
(-webkit-min-device-pixel-ratio: 2) => (-webkit-min-device-pixel-ratio:2)
@layer blocks{@media (-webkit-device-pixel-ratio>=2){.foo{color:#ff0}}}
```

Upstream-compatible output is:

```text
(-webkit-min-device-pixel-ratio: 2) => (-webkit-device-pixel-ratio>=2)
@layer blocks{@media (-webkit-min-device-pixel-ratio:2){.foo{color:#ff0}}}
```

## Native Delta

- `MediaQueryParser` now canonicalizes known bare legacy `min-` / `max-` feature aliases, including `min-width`, `max-scan`, `-webkit-min-device-pixel-ratio`, and `max--moz-device-pixel-ratio`.
- Legacy `-webkit-min-device-pixel-ratio:` / `-webkit-max-device-pixel-ratio:` declarations now parse through the range path and validate numeric values.
- Target fallback lowering now includes `-webkit-device-pixel-ratio` and `-moz-device-pixel-ratio`, using upstream legacy spellings for simple and interval ranges inside layered `@media` blocks.
- Added `wordpress-media-vendor-ratio-prefixer.php` to self-check build-free block-gallery DPR media CSS for legacy and modern targets.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php && php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-media-vendor-ratio-prefixer.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 610 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 2944 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-vendor-ratio-prefixer.php --self-test`
  - Result: exited `0`.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2923 -> 2944 pass / 0 fail`.
- Conservative mapped coverage remains `1645 / 3532`; this deepens the already represented `src/media_query.rs` / media-query range cluster rather than claiming a new denominator row.

## Non-Overlap

This avoids accepted media calc() fallback spacing, compound resolution prefix rewriting, resolution `x` serialization, all-media elision, negated/parenthesized/equality/typed/unknown media ranges, invalid range validation, cascade-layer merging/import validation, custom-media scanner behavior, CSSOM, CSS Modules, bundler, source-map, color/font/grid/property-value, and custom at-rule visitor slices. The stale 2026-05-25 CustomMedia import-tail rework note is unrelated to this parser/prefixer path and predates accepted CustomMedia scanner integrations.

## Dependency Closure

No new support component is needed. This reuses the native `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, and lane-local tests/examples. No upstream binary, browser service, parser generator, or external CSS engine is required for runtime behavior.

## Next Task

Continue with non-overlapping LightningCSS media-query validation/recovery, property-value/font/grid/color, CSS Modules, SourceMap, target-prefix, bundler, CSSOM, and custom-at-rule parity. No current blocker was introduced by this slice.
