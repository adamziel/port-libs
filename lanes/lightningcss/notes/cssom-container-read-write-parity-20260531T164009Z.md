# CSSOM Container Read Write Parity 2026-05-31T16:40Z

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T164009Z`

Base accepted HEAD: `6b3dbcd9ba83baf454581e5cfdd21849ee67aa00`

## Source Truth

- Upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs::DeclarationBlock::{get,set,remove}` defines CSSOM behavior: shorthand reads compose from complete same-priority longhands, longhand reads extract from compatible shorthands, longhand writes update compatible existing shorthands, and longhand removal splits a shorthand into surviving longhands.
- `src/properties/contain.rs` defines `Container` as a shorthand over `container-name` and `container-type`; serialization omits `/ normal`.

## Implementation

- `DeclarationBlock::getProperty()` now reads `container-name` and `container-type` from `container`, and composes `container` from complete same-priority longhands.
- `DeclarationBlock::setProperty()` now updates compatible `container` shorthands when setting `container-name` or `container-type`, including default-type omission when setting `container-type: normal`.
- `DeclarationBlock::removeProperty()` now removes the full `container` shorthand group and splits `container` into the surviving longhand when one component is removed.
- Added `examples/wordpress-container-cssom.php` for build-free block container-query CSSOM edits.

## Evidence

- Red-first probe on base:
  `getProperty("container: wp-card / inline-size", "container-name")` returned `null`, `setProperty(..., "container-type", "size")` appended a separate longhand, and `removeProperty(..., "container-name")` left the shorthand unchanged.
- PHP lint:
  `php -l lanes/lightningcss/src/DeclarationBlock.php`
  `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  `php -l lanes/lightningcss/examples/wordpress-container-cssom.php`
  all reported no syntax errors.
- Focused test:
  `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  passed with `1 test files, 404 assertions, 0 failures`.
- Full lane-focused test:
  `php tools/run-tests.php lanes/lightningcss/tests`
  passed with `13 test files, 2330 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-container-cssom.php --self-test`
  printed `OK`.

## Status Delta

- Focused DeclarationBlock evidence adds `17` assertions.
- Full LightningCSS PHP evidence moves from `2313` to `2330 pass / 0 fail`.
- Conservative mapped coverage remains `1446 / 3532`; this deepens the already represented upstream DeclarationBlock CSSOM cluster rather than adding a new denominator row.

## Non-Overlap

- This does not repeat accepted CSSOM font, border-radius, logical-axis, place-alignment, text-decoration, outline, background, border, border-image, transition, animation, list-style, gap, overflow, scroll-snap, mask-border, grid, inset, or priority-bucket behavior.
- The stale 2026-05-25 `CustomMediaTransformer` rework note is unrelated to this current CSSOM slice and predates later accepted custom-media scanner/import-tail integrations.

## Dependency Closure

No new support component is needed. This reuses the native `DeclarationBlock` parser, priority buckets, top-level slash/whitespace scanners, shorthand serializer, and longhand split/update machinery.
