# LightningCSS CSSOM Outline Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T153108Z`

## Source truth

- Upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs::test_get`, `test_set`, and `test_remove` define the generic `DeclarationBlock::{get,set,remove}` behavior used here: shorthand reads expose longhands, compatible longhand writes update a shorthand in place, shorthand removal drops included longhands, and longhand removal splits the shorthand into surviving longhands.
- `src/properties/outline.rs` defines `outline` as a shorthand over `outline-width`, `outline-style`, and `outline-color` using the same `GenericBorder` serialization model as border, with `outline-style:auto` as the outline-specific style value.

## Implementation

- `DeclarationBlock::getProperty()` now reads `outline-width`, `outline-style`, and `outline-color` from `outline`, and composes `outline` from complete same-priority outline longhands.
- `DeclarationBlock::setProperty()` now updates compatible `outline` shorthands when setting outline longhands and appends a separate longhand for opposite-priority writes.
- `DeclarationBlock::removeProperty()` now removes `outline` plus direct outline longhands, and splits `outline` into surviving longhands when one component is removed.
- Added `examples/wordpress-outline-cssom.php` for block focus-ring color/style edits without Node.

## Red/green evidence

- Red probe before edit:
  - `getProperty("outline: 2px solid red", "outline-color")` returned `NULL`.
  - `getProperty("outline-width: 2px; outline-style: solid; outline-color: red", "outline")` returned `NULL`.
  - `setProperty("outline: 2px solid red", "outline-color", "blue")` appended `outline-color: blue`.
  - `removeProperty("outline: 2px solid red", "outline-color")` left `outline` intact.
- `php -l lanes/lightningcss/src/DeclarationBlock.php && php -l lanes/lightningcss/tests/DeclarationBlockTest.php && php -l lanes/lightningcss/examples/wordpress-outline-cssom.php` => all no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "JSON OK\n";'` => `JSON OK`.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 269 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1934 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-outline-cssom.php --self-test` => `OK`.
- `git diff --check -- lanes/lightningcss` => no output.

## Status delta

- Focused `DeclarationBlockTest.php` assertions move from `253` to `269`, adding 16 outline CSSOM assertions.
- Full LightningCSS PHP evidence moves from `1918` to `1934 pass / 0 fail`.
- Conservative mapped coverage remains `1311 / 3532`; this deepens the already represented upstream `DeclarationBlock` CSSOM cluster rather than adding a new denominator row.

## Non-overlap

This does not repeat accepted CSSOM priority buckets, background, border, border-image, inset, grid, gap, list-style, animation, transition, mask-border, scroll-snap, or shorthand-group removal slices. It targets the adjacent upstream `outline` shorthand family.

## Dependency closure

No new support component is needed. The slice reuses the existing bounded DeclarationBlock parser, top-level token splitter, priority buckets, and shorthand splitting/serialization helpers.

## Next

Remaining CSSOM parity work should move to a different shorthand family rather than repeating outline.
