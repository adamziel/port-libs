# CSSOM Perspective Origin Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T100548Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` declares `perspective-origin` as `PerspectiveOrigin(Position, VendorPrefix) / WebKit / Moz`.
- This uses the same upstream `Position` value serialization family already represented for `transform-origin`: directional keywords serialize to numeric/percentage positions, vendor-prefixed declarations keep their own property ids, and custom properties remain case-preserving raw values.

## Change

- `DeclarationBlock` now canonicalizes `perspective-origin`, `-webkit-perspective-origin`, and `-moz-perspective-origin` during parse/get/set.
- Added focused DeclarationBlock coverage for parse, getProperty, setProperty, removeProperty, priority-bucket ordering, prefixed declarations, and custom-property preservation.
- Added `examples/wordpress-perspective-origin-cssom.php` for WordPress block/card 3D CSSOM edits without Node/WASM.

## Evidence

- Red-first probe before patch:
  - `parse("perspective-origin: LEFT top; -webkit-perspective-origin: right bottom !important; -moz-perspective-origin: center 0px; --Perspective-Origin: LEFT top")` kept raw authored values.
  - `setProperty("perspective-origin: LEFT top; color: red", "perspective-origin", "bottom")` returned `perspective-origin: bottom; color: red`.
- Baseline focused test before patch: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 1162 assertions, 0 failures`.
- Focused after patch: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 1170 assertions, 0 failures`.
- Full lane after patch: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 7316 assertions, 0 failures`.
- WordPress smoke: `php lanes/lightningcss/examples/wordpress-perspective-origin-cssom.php --self-test` => `OK`.

## Coverage Delta

- Focused DeclarationBlock coverage adds 8 assertions.
- Conservative mapped coverage remains `2365 / 3532`; this deepens the already represented CSSOM DeclarationBlock cluster rather than adding a new denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP declaration parser, CSSOM priority-bucket handling, property-name normalization, and existing origin-position serializer.

## Non-Overlap

This does not repeat the accepted `transform-origin` CSSOM slice, text-decoration skip-ink/emphasis-position, animation-composition, SVG/clip-path/mask/grid/font/container/border/background/transition CSSOM, source-map, CSS Modules, media-query, custom-at-rule, target-prefixing, or property-value minifier slices. The patch is limited to direct `perspective-origin` declaration read/write canonicalization and its WebKit/Moz prefixed variants.
