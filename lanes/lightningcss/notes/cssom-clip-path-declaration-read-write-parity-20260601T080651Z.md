# CSSOM Clip Path Declaration Read Write Parity - 2026-06-01T08:06Z

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T080651Z`

Base accepted HEAD: `0b8c08e6264b7332840b1960ce9f5a694bcdbc84`

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Source read: `src/declaration.rs::DeclarationBlock::{get,set,remove}` and `src/properties/masking.rs::ClipPath`.
- Test source read: `src/lib.rs::test_mask` clip-path helper cases for URL token serialization, `inset()` radius compaction, default `circle()`/`ellipse()` radii and positions, polygon fill-rule serialization, and geometry-box ordering/default omission.

## Implemented

- `DeclarationBlock` now canonicalizes `clip-path` and `-webkit-clip-path` declaration values during parse/get/set.
- Added focused read/write assertions for URL normalization, `inset()`, `circle()`, `ellipse()`, `polygon(nonzero/evenodd, ...)`, geometry-box-only values, independent prefixed declarations, custom-property preservation, priority buckets, and direct removal.
- Added `examples/wordpress-clip-path-cssom.php` so WordPress block/theme clip-path CSSOM edits can be smoke-tested without Node/WASM.

## Evidence

- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `1 test files, 1098 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-clip-path-cssom.php --self-test`
  - exited `0` and printed `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6889 assertions, 0 failures`
- `php -l lanes/lightningcss/src/DeclarationBlock.php`
  - `No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php`
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php`
- `php -l lanes/lightningcss/examples/wordpress-clip-path-cssom.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-clip-path-cssom.php`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Dependency Closure

No new support component is needed. This reuses the native PHP declaration parser, CSSOM priority buckets, token splitting helpers, URL normalization, and length/percentage normalization already present in `DeclarationBlock`.

## Non-Overlap

This does not repeat the accepted WebKit `clip-path` target-prefix boundary slice. It only adds CSSOM declaration read/write canonicalization for the same upstream `clip-path` property family, and leaves conservative mapped coverage unchanged because the DeclarationBlock CSSOM cluster is already represented.

## Follow-Up

Remaining CSSOM declaration-value gaps should target a different property family, or extend `clip-path` only if a later slice ports unsupported `path()`/`shape()` basic-shape serialization with pinned upstream evidence.
