# LightningCSS CSSOM Border Radius Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T160407Z`

## Source truth

- Upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs::test_get`, `test_set`, and `test_remove` define the generic `DeclarationBlock::{get,set,remove}` behavior used here: longhands are extracted from shorthands, compatible same-priority longhand writes update a shorthand in place, and longhand removal splits a containing shorthand into surviving longhands.
- `src/properties/border_radius.rs` and `src/properties/mod.rs` define `border-radius` as a shorthand over the four physical corner longhands with `-webkit-` and `-moz-` prefixed groups, while logical corner longhands remain a separate logical category.

## Implementation

- `DeclarationBlock::getProperty()` now reads physical corner longhands from `border-radius` and composes `border-radius` from complete same-priority corner longhands, including elliptical slash syntax.
- `DeclarationBlock::setProperty()` now updates compatible unprefixed, `-webkit-`, and `-moz-` border-radius shorthands when setting physical corner longhands in the same priority bucket.
- `DeclarationBlock::removeProperty()` now removes border-radius shorthand groups and splits containing shorthands when one physical corner is removed, while preserving logical corner declarations such as `border-start-start-radius`.
- Added `examples/wordpress-border-radius-cssom.php` for block/card corner editing without Node.

## Red/green evidence

- Red probe before edit:
  - `getProperty("border-radius: 10px 20px / 30px 40px", "border-top-left-radius")` returned `NULL`.
  - `getProperty("border-top-left-radius: 10px 20px; ...", "border-radius")` returned `NULL`.
  - `setProperty("border-radius: 10px", "border-top-left-radius", "20px 30px")` appended a separate longhand.
  - `removeProperty("border-radius: 10px 20px / 30px 40px", "border-top-left-radius")` left the shorthand intact.
- Focused baseline before edit: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 284 assertions, 0 failures`.
- Focused after edit: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 302 assertions, 0 failures`.
- Full lane after edit: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2043 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-border-radius-cssom.php --self-test` => `OK`.

## Status delta

- Focused `DeclarationBlockTest.php` assertions move from `284` to `302`, adding 18 border-radius CSSOM assertions.
- Full LightningCSS PHP evidence moves from `2025` to `2043 pass / 0 fail`.
- Conservative mapped coverage remains `1340 / 3532`; this deepens the already represented upstream `DeclarationBlock` CSSOM cluster rather than adding a new denominator row.

## Non-overlap

This does not repeat accepted CSSOM priority buckets, background, border, border-image, outline, inset, grid, gap, overflow, list-style, animation, transition, mask-border, scroll-snap, or shorthand-group removal slices. It targets the adjacent upstream `border-radius` shorthand family, whose prior lane coverage was minifier/prefixer behavior rather than DeclarationBlock CSSOM read/write/remove behavior.

## Dependency closure

No new support component is needed. The slice reuses the existing native DeclarationBlock parser, top-level whitespace/slash splitters, priority-bucket partitioning, shorthand compression, and serializer.

## Next

Remaining CSSOM parity should move to a different non-overlapping shorthand family or deeper upstream CSSOM parser behavior.
