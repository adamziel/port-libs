# LightningCSS Media Query Target Boundary Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T024613Z`

Base accepted HEAD: `c1c883c28f62d04121f13200bac2177a47c69bd4`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/compat.rs` marks `MediaRangeSyntax` and `MediaIntervalSyntax` unsupported for Safari and iOS Safari before `16.4`.
- `src/prefixes.rs::Feature::AtResolution` requires WebKit resolution media prefixes for Android `2.3..4.2`, Chrome `4..28`, Safari `4..15.6`, and iOS Safari `4..15.6`.
- The same upstream prefix table requires Mozilla resolution media prefixes for Firefox `3.5..15`.

## Red-First Evidence

Accepted-base probes showed the PHP target table diverged from the pinned upstream boundaries:

- `@layer blocks { @media (width >= 240px) { ... } }` with Safari `16.3` serialized as modern `(width>=240px)` instead of fallback `(min-width:240px)`.
- `@layer blocks { @media (min-resolution: 2dppx) { ... } }` with Chrome `28` emitted only `(min-resolution:2dppx)` instead of a WebKit device-pixel-ratio variant plus the standard query.
- Firefox `3.0` received a Mozilla device-pixel-ratio variant even though upstream starts that prefix range at Firefox `3.5`.

## Native Delta

- `TransitionPrefixer::targetOptions()` now follows the pinned upstream media range compatibility boundary for Safari/iOS Safari through `16.3.255`.
- WebKit resolution media-prefix targeting now includes Chrome and Android ranges from upstream `Feature::AtResolution`.
- Safari/iOS Safari WebKit resolution prefixes now use the upstream `4..15.6` bound.
- Firefox Mozilla resolution prefixes now use the upstream `3.5..15` bound.
- Focused tests cover Safari/iOS `16.3` versus `16.4` range fallback, Chrome `28` versus `29`, Android `4.2` versus `4.3`, Firefox `3.0` versus `3.5`, and Safari `15.6` versus `15.7` resolution boundary behavior inside `@layer` media rules.
- `wordpress-media-range-layer-prefixer.php` now smokes Safari `16.3` range fallback, Safari `16.4` modern range output, Chrome `28` WebKit resolution fallback, and Firefox `3.0` no-prefix resolution output.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `2 test files, 1309 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - exited `0`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5646 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`.
- `git diff --check -- lanes/lightningcss`
  - exited `0`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence moves from `5634` to `5646` assertions with `0` failures.
- Conservative mapped denominator remains `2312 / 3532`; this deepens already represented media-query range/layer and resolution-prefix clusters instead of claiming a new upstream denominator row.

## Dependency Closure

No new support component is needed. The slice reuses native `TransitionPrefixer`, `MediaQueryParser`, focused PHP tests, and the lane-local WordPress media-range layer example. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required.

## Non-Overlap

This avoids accepted x-resolution serialization, resolution equality fallbacks, env() resolution prefixing, scientific media ranges, vendor device-pixel-ratio range lowering, escaped media identifiers, explicit media-type OR guards, custom media/import graph behavior, CSSOM, CSS Modules, SourceMap, color/font/grid/property-value, and custom at-rule visitor slices. The stale May 25 `CustomMediaTransformer` rework note is unrelated to this target-boundary slice.

## Next Task

Continue with non-overlapping LightningCSS media-query parser recovery/serialization, target-prefix browser-boundary cases, CSSOM, CSS Modules, SourceMap, bundler, property-value/font/grid/color, or custom-at-rule parity.
