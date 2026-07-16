# CSSOM Accent Color Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T064901Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` maps `accent-color` to `AccentColor(ColorOrAuto)`, so CSSOM reads and writes serialize through the typed color-or-auto value.
- `src/lib.rs` `test_ui` covers `accent-color: auto` and `accent-color: yellow`, with `yellow` minifying to `#ff0`.

## Native PHP Delta

- `DeclarationBlock` now normalizes the direct `accent-color` declaration through the existing minified declaration serializer for CSSOM parse/get/set paths.
- `accent-color: auto` is lowercased as the upstream `ColorOrAuto` keyword.
- Color values canonicalize through the native color minifier, so `Yellow` serializes as `#ff0` and `Lime` serializes as `#0f0`.
- Custom properties remain case-preserving and unaffected.
- Added `examples/wordpress-accent-color-cssom.php` for a WordPress block-control accent CSSOM smoke without Node/WASM.

## Evidence

- Red probe before implementation: `DeclarationBlock` preserved authored casing for `accent-color: Yellow` and `accent-color: AUTO` instead of upstream typed serialization.
- Focused test:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 1064 assertions, 0 failures`
- Full lane:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 6507 assertions, 0 failures`
- Example:
  - `php lanes/lightningcss/examples/wordpress-accent-color-cssom.php --self-test`
  - Result: `OK`
- PHP lint:
  - `php -l lanes/lightningcss/src/DeclarationBlock.php` -> pass
  - `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> pass
  - `php -l lanes/lightningcss/examples/wordpress-accent-color-cssom.php` -> pass
- Whitespace:
  - `git diff --check -- lanes/lightningcss` -> pass

## Status Delta

- Full LightningCSS PHP assertions: `6500 -> 6507` (`+7`).
- Conservative mapped denominator remains `2360 / 3532`; this deepens the already represented upstream CSSOM `DeclarationBlock` cluster instead of claiming a new inventory row.

## Non-Overlap

- This slice is limited to direct `accent-color` CSSOM read/write normalization.
- It does not repeat accepted CSSOM shorthand families, filter/backdrop-filter CSSOM parity, caret shorthand parity, source-map VLQ offsets, CSS Modules, bundle/import graph, custom at-rules, media-query lowering, or target-prefixing slices.
- No LightningCSS rework note existed for this lane at the start of the handoff.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `DeclarationBlock` parser, property normalizer, minified declaration serializer, and focused PHP test harness.

## Next Task

Continue CSSOM parity on another non-overlapping typed declaration family, or move to remaining source-map, CSS Modules, bundle/import graph, media-query, target-prefixing, property-value, or custom-at-rule parity.
