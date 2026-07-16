# CSSOM Image Rendering Fallback Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T235054Z`

## Source Truth

- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/svg.rs` defines the typed `ImageRendering` SVG values already covered by the existing CSSOM test.
- `src/prefixes.rs` defines the `ImageRendering` pixelated browser fallback surface used by upstream prefixing: `pixelated`, `-webkit-optimize-contrast`, `-moz-crisp-edges`, and `-o-pixelated`.

## Change

- `DeclarationBlock` now canonicalizes `image-rendering` CSSOM reads and writes for `crisp-edges`, `pixelated`, and the legacy fallback values used by the upstream image-rendering prefix path.
- Extended the existing SVG paint CSSOM test with fallback keyword parse, get, set, important-bucket write, and custom-property preservation assertions.
- Updated `examples/wordpress-svg-paint-cssom.php` so WordPress SVG/image raster-mode CSSOM edits exercise `crisp-edges`, `pixelated`, and `-webkit-optimize-contrast`.

## Evidence

- Before this slice on the current base, `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` passed with `1 test files, 1381 assertions, 0 failures`; the fallback keyword probe preserved authored casing for `CRISP-EDGES`, `Pixelated`, and vendor fallback values.
- After the source change, `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` passed with `1 test files, 1387 assertions, 0 failures`.
- Full lane-focused PHP gate: `php tools/run-tests.php lanes/lightningcss/tests` passed with `14 test files, 9192 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-svg-paint-cssom.php --self-test` printed `OK`.
- `php -l` passed for `DeclarationBlock.php`, `DeclarationBlockTest.php`, and `wordpress-svg-paint-cssom.php`.
- `git diff --check -- lanes/lightningcss` passed.

## Status

- `phpPass` moves `9186 -> 9192` (`+6` focused assertions).
- Conservative mapped coverage remains `2439 / 3532`; this deepens the already represented CSSOM DeclarationBlock cluster rather than claiming a new upstream denominator row.

## Non-Overlap

This does not repeat the accepted SVG `image-rendering` CSSOM enum-value slice or the accepted target-prefixing browser-boundary slice for `image-rendering: pixelated`. The production change is limited to direct CSSOM declaration read/write canonicalization for the fallback keyword values.

## Dependency Closure

No new support component is needed. The slice reuses the native `DeclarationBlock` parser, keyword normalizer, priority buckets, and existing WordPress SVG paint CSSOM example harness.

## Next

Continue current-base production-bearing LightningCSS parity in non-overlapping CSSOM, bundle/import graph, source-map, CSS Modules, media-query, target-prefixing, property/value, or custom-at-rule surfaces. Full upstream Node/WASM runner closure still depends on bounded installation or vendoring of missing runner dependencies.
