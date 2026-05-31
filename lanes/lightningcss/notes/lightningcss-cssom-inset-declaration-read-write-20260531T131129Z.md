# LightningCSS CSSOM Inset Declaration Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T131129Z`

Source truth:

- Pinned upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/margin_padding.rs` defines `Inset` through the same `rect_shorthand!` macro family as `Margin` and `Padding`, with physical longhands `Top`, `Right`, `Bottom`, and `Left`.
- `src/declaration.rs` `DeclarationBlock::get`, `set`, and `remove` apply generic shorthand/longhand CSSOM behavior: shorthand values are composed from longhands, compatible longhand writes update a shorthand in place, logical/physical category conflicts append instead of mutating fallback declarations, and longhand removal splits shorthands minus the removed property.

Native PHP change:

- `DeclarationBlock` now treats `inset` as a physical rect shorthand alongside `margin` and `padding`.
- `getProperty()` can compose `inset` from `top/right/bottom/left` and read physical offsets from an `inset` shorthand.
- `setProperty()` updates compatible `inset` shorthands when setting `top/right/bottom/left`, while preserving logical fallback ordering by appending after `inset-inline-*`/`inset-block-*` boundaries.
- `removeProperty()` splits `inset` into surviving physical longhands when removing one side, and removes physical inset declarations while preserving logical declarations when removing `inset`.

WordPress path:

- `examples/wordpress-inset-cssom.php` models editor/migration tooling that reads and edits cover-overlay offset declarations without Node, preserving logical inset token fallbacks for RTL-safe layouts.

Evidence:

- `php -l lanes/lightningcss/src/DeclarationBlock.php`
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
- `php -l lanes/lightningcss/examples/wordpress-inset-cssom.php`
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 120 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1352 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-inset-cssom.php --self-test` -> `OK`
- `git diff --check -- lanes/lightningcss` -> pass

Dependency closure:

- No new support component is needed. This reuses the existing bounded `DeclarationBlock` parser/serializer, priority buckets, and rect shorthand expansion/compression helpers.

Non-overlap:

- This does not repeat accepted background CSSOM shorthand mutation, border longhand removal, priority buckets, grid, flex-flow, or animation-name CSSOM slices. Conservative mapped coverage remains inside the existing DeclarationBlock/CSSOM cluster; public PHP pass evidence increases through focused assertions.
