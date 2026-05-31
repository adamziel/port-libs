# CSSOM Scroll Snap Read Write Parity 2026-05-31T14:55Z

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T145525Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/margin_padding.rs` defines `ScrollMargin` and `ScrollPadding` with `rect_shorthand!`, using the same top/right/bottom/left shorthand structure as `Margin`, `Padding`, and `Inset`.
- `src/declaration.rs::DeclarationBlock::get`, `set`, and `remove` drive CSSOM behavior from `PropertyId::longhands()`: shorthand reads compose from longhands, longhand reads extract from shorthands, longhand writes update the latest compatible shorthand in place, shorthand removal drops included longhands, and longhand removal splits shorthands into surviving longhands.

## Native PHP Delta

- `DeclarationBlock` now models `scroll-margin` and `scroll-padding` in the existing rect shorthand CSSOM path.
- Added focused PHP assertions for:
  - reading `scroll-margin` from physical longhands and physical longhands from `scroll-margin`;
  - reading `scroll-padding` longhands from shorthand values and rejecting mixed-importance shorthand composition;
  - setting `scroll-margin-*` and `scroll-padding-*` longhands into compatible shorthands;
  - preserving logical/physical fallback order when a logical scroll snap declaration follows a physical shorthand;
  - splitting `scroll-margin` / `scroll-padding` shorthands when removing a longhand;
  - removing scroll snap shorthand declarations and their direct physical longhands across priority buckets.
- Added `examples/wordpress-scroll-snap-cssom.php` to model block carousel scroll snap spacing edits without Node.

## Verification

- Red-first before implementation:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 211 assertions, 3 failures`
  - Failing cases were the new scroll snap CSSOM read, set, and remove tests.
- After implementation:
  - `php -l lanes/lightningcss/src/DeclarationBlock.php && php -l lanes/lightningcss/tests/DeclarationBlockTest.php && php -l lanes/lightningcss/examples/wordpress-scroll-snap-cssom.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 220 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 1771 assertions, 0 failures`.
  - `php lanes/lightningcss/examples/wordpress-scroll-snap-cssom.php --self-test`
  - Result: `OK`.
  - `git diff --check -- lanes/lightningcss`
  - Result: no whitespace errors.

## Counting And Non-Overlap

- Full LightningCSS PHP evidence moves from `1759` to `1771 pass / 0 fail`.
- Conservative mapped coverage remains `1232 / 3532`; this deepens the already represented upstream DeclarationBlock CSSOM cluster rather than adding a new denominator row.
- This avoids accepted CSSOM priority buckets, background, border, inset, grid, gap, list-style, animation, transition, and shorthand-removal slices. It targets the unclaimed scroll snap rect shorthand families backed by upstream `ScrollMargin` and `ScrollPadding`.

## Dependency Closure

No new support component is needed. This reuses the native PHP declaration parser, priority-bucket partitioning, and existing rect shorthand CSSOM helpers.
