# CSSOM SVG Stroke Linejoin Miter Clip Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T133420Z`

Accepted base: `f2475a9a46461fb108ebd2437efe777168da2710`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/svg.rs` defines `StrokeLinejoin` enum values `Miter`, `MiterClip`, `Round`, `Bevel`, and `Arcs`.
- Upstream CSSOM declaration reads and writes route known declarations through `Property::parse_string(...)` and the typed declaration printer, so `MiterClip` serializes as `miter-clip`.

## Red-First Evidence

Before this patch, the PHP CSSOM path preserved authored casing for the upstream enum arm:

```text
stroke-linejoin: Miter-Clip
=> stroke-linejoin Miter-Clip
```

The focused PHP test now pins `miter-clip` parse/get/set/remove behavior and verifies custom-property values such as `--Line-Join: Miter-Clip` remain untouched.

## Patch

- Added `miter-clip` to `DeclarationBlock` SVG `stroke-linejoin` keyword canonicalization.
- Added focused parse/get/set/remove coverage for `stroke-linejoin: Miter-Clip`, including priority-bucket serialization.
- Extended the WordPress SVG CSSOM smoke to read and rewrite icon stroke joins without Node, Rust, or WASM.
- Updated `lane-status.json` from `8070` to `8077` full-lane PHP assertions. Conservative mapped coverage remains `2393 / 3532` because this deepens the existing CSSOM `DeclarationBlock` cluster.

## Verification

```text
php -l lanes/lightningcss/src/DeclarationBlock.php
No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php

php -l lanes/lightningcss/tests/DeclarationBlockTest.php
No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php

php -l lanes/lightningcss/examples/wordpress-svg-paint-cssom.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-svg-paint-cssom.php

php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
1 test files, 1233 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-svg-paint-cssom.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 8077 assertions, 0 failures

git diff --check -- lanes/lightningcss
passes with no output
```

## Non-Overlap

This slice is limited to the previously omitted `MiterClip` arm of upstream SVG `stroke-linejoin` CSSOM read/write serialization. It does not repeat accepted SVG paint, SVG `color-rendering` / `image-rendering`, clip-path, transform, text-spacing, flex, grid, source-map, CSS Modules, bundle/import graph, media-query, target-prefixing, property-value, or custom at-rule work.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `DeclarationBlock` declaration parser and direct keyword normalizer.

## Next Task

Continue with a non-overlapping LightningCSS parity slice in CSSOM, source maps, CSS Modules, bundle/import graph, media query, property/value, custom at-rule, or target-prefixing coverage.
