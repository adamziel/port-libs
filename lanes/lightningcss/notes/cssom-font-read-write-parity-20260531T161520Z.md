# CSSOM Font Read Write Parity 2026-05-31T16:15Z

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T161520Z`

Base accepted HEAD: `8c7b034bb5fb3d061acb6b56e46103da8721d7a6`

## Source Truth

- Upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs::DeclarationBlock::{get,set,remove}` defines CSSOM behavior: shorthands compose from complete same-priority longhands, longhand reads can extract from shorthands, compatible longhand writes update an existing shorthand, and longhand removal splits a shorthand into surviving longhands.
- `src/properties/font.rs` defines `Font` with `define_shorthand!` over `font-family`, `font-size`, `font-style`, `font-weight`, `font-stretch`, `line-height`, and `font-variant-caps`.

## Implementation

- `DeclarationBlock::getProperty()` now reads `font` longhands from `font` shorthands and composes `font` from complete same-priority longhands.
- `DeclarationBlock::setProperty()` now updates compatible `font` shorthands in place for font longhand writes, while preserving priority-bucket behavior.
- `DeclarationBlock::removeProperty()` now removes the full `font` shorthand group and splits `font` into surviving longhands when one font longhand is removed.
- Added `examples/wordpress-font-cssom.php` to self-check build-free typography CSSOM edits for block themes.

## Evidence

- Red-first probe after adding the focused tests failed before the parser fix: `DeclarationBlockTest.php` reported `3 failures` because unitless `600` was misclassified as `font-size`, which made the family parser consume the remaining font shorthand tokens.
- Baseline focused DeclarationBlock evidence before this slice: `1 test files, 301 assertions, 0 failures`.
- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 320 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 2111 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-font-cssom.php --self-test` -> `OK`.

## Status Delta

- Focused DeclarationBlock evidence adds `19` assertions.
- Full LightningCSS PHP evidence moves from `2092` to `2111 pass / 0 fail`.
- Conservative mapped coverage remains `1349 / 3532`; this is additional behavior inside the already represented upstream DeclarationBlock CSSOM cluster.

## Non-Overlap

- This does not repeat accepted text-decoration, outline, background, border, border-image, transition, animation, list-style, gap, overflow, scroll-snap, mask-border, grid, or inset CSSOM read/write/remove behavior.
- This also avoids the stale 2026-05-25 CustomMediaTransformer rework note; current accepted lane status already includes later custom-media import-tail behavior, and this slice stayed on the assigned CSSOM declaration family.

## Dependency Closure

- No new support component is needed. This reuses the native PHP declaration scanner/serializer, priority buckets, shorthand split/update machinery, and bounded font value parsing helpers.
