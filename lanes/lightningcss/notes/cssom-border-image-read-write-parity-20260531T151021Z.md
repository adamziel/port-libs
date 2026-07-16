# LightningCSS CSSOM Border Image Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T151021Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` declares `border-image` as a shorthand over `border-image-source`, `border-image-slice`, `border-image-width`, `border-image-outset`, and `border-image-repeat`.
- `src/properties/border_image.rs` defines default longhand values: source `none`, slice `100%`, width `1`, outset `0`, repeat `stretch`, plus slice `fill` and repeat compaction.
- `src/lib.rs::test_border_image` verifies upstream shorthand/longhand composition such as longhand source/slice/width/outset/repeat collapsing into `border-image: url("foo.png") 10 40 fill / 10px round`.

## Implementation

- `DeclarationBlock::getProperty()` now reads `border-image-*` longhands from a `border-image` shorthand and composes `border-image` from complete same-priority longhands.
- `DeclarationBlock::setProperty()` now updates compatible `border-image` shorthands for longhand writes and appends a separate longhand when a preserved opposite-priority shorthand must remain.
- `DeclarationBlock::removeProperty()` now removes `border-image` as a shorthand group and splits `border-image` into surviving longhands when a single longhand is removed.
- Added `examples/wordpress-border-image-cssom.php` to model theme frame asset replacement, repeat editing, and frame source removal without Node.

## Verification

- Red check before edit: `getProperty("border-image: url(frame.svg) 25 / 12px round", "border-image-source")` returned `NULL`; setting the source appended a separate `border-image-source` declaration.
- `php -l lanes/lightningcss/src/DeclarationBlock.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-border-image-cssom.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 253 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1845 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-border-image-cssom.php --self-test` => `OK`.
- `php -r '$files=["lanes/lightningcss/lane-status.json","lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'` => both metadata files decode.
- `git diff --check -- lanes/lightningcss` => passed.

## Status Delta

- Full LightningCSS PHP evidence moves from `1829` to `1845 pass / 0 fail` from 16 focused new DeclarationBlock assertions.
- Conservative mapped coverage remains `1258 / 3532`; this is counted inside the existing DeclarationBlock CSSOM cluster rather than as a new upstream denominator row.

## Non-Overlap

- This does not repeat accepted CSSOM priority buckets, background, border, inset, grid, gap, list-style, transition, animation, mask-border, scroll-snap, or shorthand-group removal work.
- It targets the adjacent `border-image` shorthand family, using upstream `border_image.rs` and `src/lib.rs::test_border_image` as source truth.

## Dependency Closure

- No new support component is needed. The slice reuses the existing bounded `DeclarationBlock` parser and CSS token splitting helpers.
