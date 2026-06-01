# CSSOM Prefixed 3D Layout Read-Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T151102Z`

## Source Truth

- Upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` uses `DeclarationBlock::{get,set,remove}` through `Property::parse_string(...)` and `to_css_string(...)`.
- `src/properties/mod.rs` maps `transform-style`, `backface-visibility`, and `perspective` with `-webkit-` and `-moz-` vendor spellings.
- `src/properties/transform.rs` defines the canonical values: `flat` / `preserve-3d`, `visible` / `hidden`, and `none` or a CSS length for `perspective`.

## Behavior Added

`DeclarationBlock` now treats the prefixed direct declarations as typed CSSOM properties instead of preserving raw author casing:

- `-webkit-transform-style`
- `-moz-transform-style`
- `-webkit-backface-visibility`
- `-moz-backface-visibility`
- `-webkit-perspective`
- `-moz-perspective`

The slice reuses the existing direct declaration parser, keyword canonicalizer, important-bucket write path, and length serializer. The shared length token serializer now uses the numeric-literal normalizer so upstream-like typed lengths strip redundant leading zeros in prefixed perspective values while custom properties remain untouched.

## Evidence

Red-first probe before the patch preserved raw values for prefixed declarations such as `Preserve-3D`, `Hidden`, and `+0800.00PX`.

Focused assertion delta:

- Before: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1267 assertions, 0 failures`
- After: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1276 assertions, 0 failures`

Verification:

- `php -l lanes/lightningcss/src/DeclarationBlock.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-display-layout-cssom.php` -> no syntax errors
- `php lanes/lightningcss/examples/wordpress-display-layout-cssom.php --self-test` -> `OK`
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8336 assertions, 0 failures`

## Non-Overlap

This patch does not touch target-prefix generation or existing unprefixed transform, transform-origin, perspective-origin, animation, SVG, mask, grid, font, source-map, CSS Modules, bundle/import, media-query, selector, parser-recovery, or custom-at-rule clusters. It is bounded to prefixed direct declaration CSSOM parsing, reading, writing, priority handling, and removal.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local `DeclarationBlock` CSSOM parser/serializer and the existing PHP test harness. Upstream Rust/Node/WASM runners remain outside this isolated micro-slice.

## Next Task

Continue CSSOM parity on unmapped property/value read-write behavior, preferably another upstream-backed declaration family with direct get/set/remove assertions and an example smoke when it has a WordPress-visible stylesheet path.
