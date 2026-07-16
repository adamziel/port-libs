# Media Query Typed Range Validation

Slice: `lightningcss-media-query-range-layer-parity-20260531T135533Z`

Base: `7f53fcd353eeefd16948edc334eb7d1204b1ec5b`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream cases: `src/lib.rs::test_media` error cases at lines 9581-9625 for invalid typed media feature ranges and values.
- Mapped cases:
  - `(min-width: hi)`
  - `(width >= hi)`
  - `(width >= 2/1)`
  - `(600px <= min-height)`
  - `(scan >= 1)`
  - `(min-scan: interlace)`
  - `(1px <= width <= bar)`
  - `(1px <= min-width <= 2px)`
  - `(1px <= scan <= 2px)`
  - `(grid: 10)`
  - `(prefers-color-scheme = dark)`

## Native Behavior

- `MediaQueryParser` now validates known media feature value types before normalizing or lowering range syntax.
- Invalid range/equality syntax on ident and boolean media features is rejected instead of serialized.
- Invalid min-/max- legacy aliases on non-rangeable media features are rejected.
- Length, integer, boolean, resolution, ratio, and identifier media values are checked for upstream-aligned broad type shape.
- Styled-jsx placeholder recovery is preserved for existing nesting error-recovery parity.
- The focused stylesheet assertions exercise invalid queries inside `@layer` wrappers so block-theme layer output does not ship invalid responsive CSS.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php`
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: `1 test files, 55 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/NestingTransformerTest.php`
  - Result: `1 test files, 42 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `1 test files, 250 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 1633 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: exited 0 and emitted target fallback output plus `invalid-media-query`.
- `git diff --check -- lanes/lightningcss`
  - Result: passed.

## Coverage Delta

- PHP assertion delta: `+14` (`1619 -> 1633`).
- Conservative mapped coverage delta: `+11` (`1164 / 3532 -> 1175 / 3532`) for upstream `src/lib.rs::test_media` typed invalid media query error cases.

## Non-overlap

This avoids accepted media range target-threshold fallbacks, explicit include/exclude feature flags, resolution media-query prefixes, cascade-layer merge/minifier behavior, custom-media import-tail scanner rework, bundle import-prelude diagnostics, CSS Modules composes delimiter validation, and CSSOM shorthand behavior. Remaining media-query work is broader recovery/diagnostic parity and additional parser edge cases beyond typed invalid range validation.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `CssMinifier`, `MediaQueryParser`, `TransitionPrefixer`, and nesting recovery paths. No upstream binary, browser service, parser generator, or external CSS engine is required.
