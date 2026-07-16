# LightningCSS Media Query Negated Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T155656Z`

Base: `b396f617ce3725e2a3fde790e5dc3841675ab023`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream case: `src/lib.rs::test_media` no-target `test` helper for `@media not (width < 240px)`, which serializes as the positive range condition `@media (width >= 240px)`.
- Red-first local probe before implementation produced `@layer blocks{@media not (width<240px){...}}` instead of the upstream-compatible `@layer blocks{@media (width>=240px){...}}`.

## Native Delta

- `MediaQueryParser` now inverts simple negated range conditions after boolean-group normalization.
- The inversion is intentionally limited to simple range features. Negated interval ranges such as `not (100px <= width <= 200px)` remain explicit because the accepted legacy-target fallback path owns interval lowering.
- The behavior applies before `CssMinifier` serializes nested `@media` blocks, so `@layer`-wrapped block-theme CSS gets the same no-target media prelude parity.
- `wordpress-media-layer-minifier.php --self-test` now covers a layered wide-query rule using `@media not (width < 960px)`.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php && php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/examples/wordpress-media-layer-minifier.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: `1 test files, 106 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php lanes/lightningcss/tests/CssMinifierTest.php`
  - Result: `3 test files, 1309 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 2036 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test`
  - Result: exited `0`.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

## Status Delta

- PHP lane evidence: `2025 -> 2036 pass / 0 fail`.
- Conservative mapped coverage: `1340 / 3532 -> 1341 / 3532`.

## Non-Overlap

This avoids accepted media range target fallbacks, typed range families, invalid range validation, range include/exclude flags, resolution vendor prefixes and `x` unit serialization, cascade-layer merge behavior, custom-media import-tail scanner behavior, flex/grid/font/color/value slices, CSSOM shorthand work, source-map, bundler, CSS Modules, and custom at-rule visitor slices.

## Dependency Closure

No new support component is needed. The slice reuses the native `MediaQueryParser`, `CssMinifier`, and existing lane-local scanner/minifier paths. No upstream binary, browser service, parser generator, or external CSS engine is required.
