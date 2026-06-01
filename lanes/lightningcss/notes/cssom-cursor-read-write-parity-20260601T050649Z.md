# CSSOM Cursor Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T050649Z`

## Source Truth

- Upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files:
  - `src/properties/ui.rs`: `CursorImage` parses a URL plus optional unitless hotspot numbers, `CursorKeyword` defines the canonical keyword set, and `Cursor::to_css` serializes image entries before the final keyword.
  - `src/lib.rs::test_cursor`: minifier fixtures serialize `cursor: url("test.cur"), ew-resize` as `cursor:url(test.cur),ew-resize`.
  - `node/test/visitor.test.mjs`: visitor fixture output serializes `cursor:url(cursor.png) 4 12, auto`.
- Local native-addon spot oracle, direct `.node` require at the pinned cache, confirmed minified outputs:
  - `.x{cursor:url(drag.cur) 4 12,grab}`
  - `.x{cursor:url(test.cur),ew-resize}`

## Implementation

- `DeclarationBlock` now canonicalizes direct `cursor` declaration values during parse, get, and set:
  - `url(...)` cursor images are normalized through the existing CSS URL serializer.
  - Unitless cursor hotspot coordinates normalize as CSS numbers, e.g. `4.0 12.00` becomes `4 12`.
  - Known cursor keywords normalize case, including `grab`, `zoom-in`, and resize keywords.
- Custom properties remain token-preserving, so `--Block-Cursor: URL("drag.cur") 4.0 12.00, Grab` stays untouched.
- Added `examples/wordpress-cursor-cssom.php` for a WordPress block-editor cursor-image workflow without Node/WASM.

## Evidence

- Red-first focused run before implementation:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Failed because `cursor: URL("drag.cur") 4.0 12.00, Grab` was read back unchanged instead of `url(drag.cur) 4 12, grab`.
- Focused after implementation:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `1 test files, 1003 assertions, 0 failures`
- Full lane:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6116 assertions, 0 failures`
- Example smoke:
  - `php lanes/lightningcss/examples/wordpress-cursor-cssom.php --self-test`
  - `OK`
- PHP lint:
  - `php -l lanes/lightningcss/src/DeclarationBlock.php`
  - `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-cursor-cssom.php`
  - All reported no syntax errors.

## Non-Overlap

This slice only deepens the already represented DeclarationBlock CSSOM cluster for direct cursor declarations. It does not repeat accepted cursor target-prefix browser-boundary work, UI direct enum CSSOM, text/writing CSSOM, box spacing normalization, source-map, CSS Modules, custom at-rule, media-query, bundle/import graph, property-value, or target-prefix slices. The stale May 25 `CustomMediaTransformer` rework note was reviewed and is unrelated.

## Dependency Closure

No new support component is needed. The patch reuses `DeclarationBlock` token splitting, CSS URL normalization, CSS number normalization, priority partitioning, and existing example/test harnesses. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is introduced.

## Next

Continue with non-overlapping CSSOM declaration parity, source maps, CSS Modules, bundle/import graph, media queries, property values, target prefixing, and custom at-rules with full-lane PHP gates.
