# LightningCSS Media Query Calc Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T175958Z`

Base: `e83ba68ab62e3e93ee2dcf9fc87ea144ffeb366d`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source: `src/lib.rs::test_media` prefix helper for `@media (width > calc(1px + 1rem))` with Chrome 85 targets, plus `src/media_query.rs::write_min_max`.
- Local pinned native-addon oracle confirmed compact output preserves required `calc()` additive spacing:
  - `@layer blocks{@media not (max-width:calc(1px + 1rem)){.foo{color:#ff0}}}`
  - `@layer blocks{@media (not (max-width:100px)) and (not (min-width:calc(100vw - 50px))){.foo{color:#ff0}}}`

## Red-First Evidence

Before this patch, PHP emitted invalid compacted calc additions after the first minification pass:

```text
@layer blocks{@media not (max-width:calc(1px+1rem)){.foo{color:#ff0}}}
```

The pinned upstream native addon emits:

```text
@layer blocks{@media not (max-width:calc(1px + 1rem)){.foo{color:#ff0}}}
```

## Native Delta

- `MediaQueryParser` now restores required binary `+` / `-` spacing in compacted `calc(...)` media feature values before range fallback serialization.
- Same-unit calc folding remains intact, so `calc(200px + 40px)` still becomes `240px`.
- Layered target fallback output now matches upstream for simple `width > calc(...)` and interval ranges whose upper bound is `calc(...)`.
- Added `wordpress-media-calc-range-layer-prefixer.php` to self-check block-theme layered responsive CSS without Node/WASM at runtime.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php && php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-media-calc-range-layer-prefixer.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 579 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 2832 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-calc-range-layer-prefixer.php --self-test`
  - Result: exited `0`.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2825 -> 2832 pass / 0 fail`.
- Conservative mapped coverage remains `1616 / 3532`; this deepens the already represented `src/lib.rs::test_media` media-query range fallback cluster rather than claiming a new denominator row.

## Non-Overlap

This avoids accepted media-query all-type elision, top-level function guards, typed/unknown/equality ranges, parenthesized negation, resolution prefixes and `x` units, include/exclude feature flags, cascade-layer merge/import validation, custom-media scanner behavior, CSSOM, CSS Modules, bundler, source-map, color/font/grid/property-value, and target-prefix slices.

## Dependency Closure

No new support component is needed. The slice reuses the native `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, and lane-local tests/examples. No upstream binary, browser service, parser generator, or external CSS engine is required for runtime behavior.

## Next Task

Continue with non-overlapping LightningCSS media-query parser/minifier, CSSOM, CSS Modules, SourceMap, target-prefix, property-value/font/grid/color, bundler, and custom-at-rule parity. No current blocker was introduced by this slice.
