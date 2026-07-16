# CSSOM SVG Rendering Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T082523Z`

Accepted base: `e307345b68a0844266e5b42b8d4ac54edb9f105d`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` routes CSSOM reads/writes through typed `Property::parse_string(...)` and `DeclarationBlock::{get,set,remove}`, so property-value serialization follows the upstream property printer.
- `src/properties/svg.rs` defines `ColorRendering` and `ImageRendering` enum values `Auto`, `OptimizeSpeed`, and `OptimizeQuality`.
- `node/ast.d.ts` exposes those two SVG rendering value types as `"auto" | "optimizespeed" | "optimizequality"`, matching the upstream lowercase serialized form already used by related SVG rendering declarations.

## Red-First Evidence

Before this patch, a local probe showed the PHP CSSOM path kept authored SVG rendering casing:

```text
color-rendering: optimizeSpeed; image-rendering: optimizeQuality
=> color-rendering optimizeSpeed, image-rendering optimizeQuality
```

The focused PHP test added in this slice pins the upstream lowercase read/write behavior for `color-rendering` and `image-rendering`, while preserving custom-property casing for `--Raster-Mode`.

## Patch

- Added `color-rendering` and `image-rendering` to `DeclarationBlock` SVG rendering keyword canonicalization.
- Added parse/get/set/remove coverage for those two declarations, including priority-bucket ordering with an important `image-rendering`.
- Extended the WordPress SVG CSSOM smoke to read and rewrite icon/raster rendering modes without Node, Rust, or WASM.
- Updated `lane-status.json` from `6942` to `6949` full-lane PHP assertions. Conservative mapped coverage remains `2360 / 3532` because this deepens the existing CSSOM `DeclarationBlock` cluster.

## Verification

```text
php -l lanes/lightningcss/src/DeclarationBlock.php
No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php

php -l lanes/lightningcss/tests/DeclarationBlockTest.php
No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php

php -l lanes/lightningcss/examples/wordpress-svg-paint-cssom.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-svg-paint-cssom.php

php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
1 test files, 1105 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-svg-paint-cssom.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 6949 assertions, 0 failures

git diff --check -- lanes/lightningcss
passes with no output
```

## Non-Overlap

This slice is limited to CSSOM read/write canonicalization for the previously omitted SVG `color-rendering` and `image-rendering` declarations. It does not repeat accepted SVG paint/stroke/dasharray rendering coverage, clip-path CSSOM, text-spacing CSSOM, source-map, CSS Modules, bundle/import graph, media-query, target-prefixing, property-value, or custom-at-rule work.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `DeclarationBlock` parser and direct keyword declaration normalizer.

## Next Task

Continue with a non-overlapping LightningCSS parity slice in CSSOM, source maps, CSS Modules, bundle/import graph, media query, property/value, custom at-rule, or target-prefixing coverage.
