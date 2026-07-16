# LightningCSS CSSOM Text Decoration Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T155219Z`

## Source truth

- Upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs::DeclarationBlock::{get,set,remove}` defines the generic CSSOM behavior used here: shorthand reads expose longhands, compatible longhand writes update a shorthand in place, shorthand removal drops included longhands, and longhand removal splits the shorthand into surviving longhands.
- `src/properties/text.rs` defines unprefixed `text-decoration` over `text-decoration-line`, `text-decoration-thickness`, `text-decoration-style`, and `text-decoration-color`, with upstream defaults `none`, `auto`, `solid`, and `currentColor`.

## Implementation

- `DeclarationBlock::getProperty()` now reads `text-decoration-*` longhands from a `text-decoration` shorthand and composes `text-decoration` from complete same-priority longhands.
- `DeclarationBlock::setProperty()` now updates compatible `text-decoration` shorthands when setting text-decoration longhands and appends a separate longhand for opposite-priority writes.
- `DeclarationBlock::removeProperty()` now removes `text-decoration` plus direct text-decoration longhands, and splits `text-decoration` into surviving longhands when one component is removed.
- Added `examples/wordpress-text-decoration-cssom.php` for block link underline color/thickness edits without Node.

## Red/green evidence

- Red probe before edit:
  - `getProperty("text-decoration: underline wavy red", "text-decoration-color")` returned `NULL`.
  - `setProperty("text-decoration: underline wavy red", "text-decoration-color", "blue")` appended `text-decoration-color: blue`.
  - `removeProperty("text-decoration: underline wavy red", "text-decoration-color")` left the shorthand intact.
- `php -l lanes/lightningcss/src/DeclarationBlock.php && php -l lanes/lightningcss/tests/DeclarationBlockTest.php && php -l lanes/lightningcss/examples/wordpress-text-decoration-cssom.php` => all no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 301 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2042 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-text-decoration-cssom.php --self-test` => `OK`.
- `git diff --check -- lanes/lightningcss` => no output.

## Status delta

- Focused `DeclarationBlockTest.php` adds 17 text-decoration CSSOM assertions.
- Full LightningCSS PHP evidence moves from `2025` to `2042 pass / 0 fail`.
- Conservative mapped coverage remains `1340 / 3532`; this deepens the already represented upstream `DeclarationBlock` CSSOM cluster rather than adding a new denominator row.

## Non-overlap

This does not repeat accepted CSSOM priority buckets, background, border, border-image, outline, inset, grid, gap, list-style, animation, transition, mask-border, scroll-snap, overflow, or shorthand-group removal slices. It targets the adjacent upstream `text-decoration` shorthand family.

## Dependency closure

No new support component is needed. The slice reuses the existing bounded `DeclarationBlock` parser, top-level token splitter, priority buckets, and shorthand splitting/serialization helpers.

## Next

Remaining CSSOM parity work should move to a different shorthand family such as `text-emphasis` or `columns`, or to prefixed text-decoration behavior, rather than repeating this unprefixed text-decoration slice.
