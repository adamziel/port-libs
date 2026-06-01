# CSSOM Filter Read/Write Parity - 2026-06-01T06:15Z

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T061548Z`

## Source Truth

- Upstream `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`: `src/lib.rs::test_filter`.
- Upstream serialization behavior: `src/properties/effects.rs` emits canonical filter functions, strips URL quotes, omits default arguments such as `blur(0px)`, `brightness(100%)`, and `hue-rotate(0)`, and serializes drop-shadow colors such as `yellow` as `#ff0`.

## Implementation

- `DeclarationBlock` now routes CSSOM direct declaration values for `filter`, `-webkit-filter`, `backdrop-filter`, and `-webkit-backdrop-filter` through the existing native minifier extraction path used for transform normalization.
- Custom properties remain authored-value preserving, so `--Block-Filter: Blur(0px)` is not canonicalized as a known filter property.
- Added a WordPress-facing smoke for block/theme style code that reads, rewrites, and removes filter/backdrop-filter declarations without Node or WASM.

## Evidence

- Before patch: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1032 assertions, 0 failures`.
- After patch: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1040 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6411 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-filter-cssom.php --self-test` -> `OK`.

## Non-Overlap

This slice does not change accepted target-prefixing filter/backdrop-filter emission, supports/font target-prefixing, cursor CSSOM, shadow CSSOM, source-map, CSS Modules, bundler, media-query, custom at-rule, or broad property-value minifier surfaces. It is limited to CSSOM declaration read/write canonicalization for filter-family properties.

## Dependency Closure

No new support component is required. The implementation reuses the existing native `CssMinifier` and `DeclarationBlock` parser/serializer path.
