# CSSOM SVG Paint and Rendering Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T035151Z`

Accepted base: `bf75a27f708d456a2f08c9c540bce1189ab451a6`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` routes CSSOM declaration reads and writes through typed `Property::parse_string(...)` and `DeclarationBlock::{get,set,remove}`, so known property values serialize through upstream property printers.
- `src/properties/svg.rs` defines typed SVG paint, marker, stroke dash array, rendering enum, stroke linecap/linejoin, and numeric stroke declarations.
- `src/lib.rs::test_mask` directly asserts upstream parse parity for `text-rendering: geometricPrecision`, `shape-rendering: geometricPrecision`, and `color-interpolation: sRGB`.
- A local pinned NAPI oracle check against the upstream cache confirmed CSS serialization such as:
  - `fill: url("#icon") currentColor` -> `fill:url(#icon) currentColor`
  - `stroke: rgba(255,0,0,.4)` -> `stroke:#f006`
  - `stroke-dasharray: 0px, 2px 4px` -> `stroke-dasharray:0 2 4`
  - `text-rendering: geometricPrecision` -> `text-rendering:geometricprecision`

## Red-First Evidence

After adding the focused PHP assertions but before implementation, the CSSOM test failed because SVG declarations were stored as opaque authored values:

```text
php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
FAIL declaration block canonicalizes upstream svg paint and rendering cssom read write
Expected fill => url(#wp-gradient) currentColor, stroke => #f006, stroke-dasharray => 0 2 4, text-rendering => geometricprecision
Actual fill => url("#wp-gradient") currentColor, stroke => rgba(255,0,0,.4), stroke-dasharray => 0px, 2px 4px, text-rendering => geometricPrecision
```

## Patch

- `DeclarationBlock` now canonicalizes SVG CSSOM declaration values for:
  - `fill` and `stroke` paint values, including URL token normalization and fallback color shortening.
  - `marker`, `marker-start`, `marker-mid`, and `marker-end` URL/`none` values.
  - `stroke-dasharray` comma/space lists with upstream unitless `px` serialization.
  - `stroke-width`, `stroke-dashoffset`, and `stroke-miterlimit` numeric serialization.
  - `fill-rule`, `clip-rule`, `stroke-linecap`, `stroke-linejoin`, `color-interpolation`, `color-interpolation-filters`, `shape-rendering`, and `text-rendering` keyword canonicalization.
- Added focused parse/get/set/remove coverage in `DeclarationBlockTest.php`.
- Added `wordpress-svg-paint-cssom.php` to smoke SVG icon paint CSSOM reads, editor overrides, dash-array writes, and fill removal without Node/WASM.
- Updated `lane-status.json` from `5855` to `5868` full-lane PHP assertions. Conservative mapped coverage remains `2320 / 3532` because this deepens the represented upstream CSSOM `DeclarationBlock` cluster.

## Verification

```text
php -l lanes/lightningcss/src/DeclarationBlock.php
No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php

php -l lanes/lightningcss/tests/DeclarationBlockTest.php
No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php

php -l lanes/lightningcss/examples/wordpress-svg-paint-cssom.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-svg-paint-cssom.php

php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
1 test files, 970 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-svg-paint-cssom.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 5868 assertions, 0 failures

git diff --check -- lanes/lightningcss
passes with no output
```

## Non-Overlap

This slice is limited to CSSOM DeclarationBlock read/write serialization for SVG paint/rendering declarations. It does not repeat accepted CSSOM shadow, transform, UI direct enum, alpha opacity, border, mask, animation, transition, grid, background, source-map, bundle/import graph, CSS Modules, media-query, target-prefixing, property-value, or custom at-rule slices.

The stale 2026-05-25 CustomMedia import-tail rework note was inspected and is unrelated to this CSSOM micro-slice; no CustomMedia code was touched.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP `DeclarationBlock` parser, top-level token/comment splitters, URL serializer, numeric normalizer, and existing color-shortening helper. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is introduced for runtime behavior.

## Next Task

Continue CSSOM parity on a different non-overlapping typed declaration family, or move to the next supervisor-priority LightningCSS gap in source maps, CSS Modules, bundle/import graph, media queries, target prefixing, property/value parity, or custom at-rules.
