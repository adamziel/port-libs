# CSSOM Flex Read Write Parity 2026-05-31T16:42Z

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T164206Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs::test_get`, `test_set`, and `test_remove` define the shared `DeclarationBlock::{get,set,remove}` behavior: shorthands expose longhands, compatible same-priority longhand writes update shorthands, priority buckets are preserved, and longhand removal splits a containing shorthand.
- `src/properties/flex.rs::Flex` is a `define_shorthand!` over `flex-grow`, `flex-shrink`, and `flex-basis`. Its parser handles `none`, grow/shrink numbers, basis values, defaults to `1 1 0%`, and serializes upstream forms such as `none`, `auto`, `1`, `1 1 0`, `1 0 auto`, and `2 10px`.
- `src/lib.rs::test_flex` provides focused serialization examples for composing `flex` from longhands, including `0%` versus unitless/length zero basis behavior and the `flex: 0 0; flex-grow: var(--grow)` preservation boundary.

## Native PHP Delta

- `DeclarationBlock::getProperty()` now reads `flex-grow`, `flex-shrink`, and `flex-basis` from `flex` and `-webkit-flex`, and composes `flex` from complete same-priority longhands.
- `setProperty()` now updates same-priority `flex` and `-webkit-flex` shorthands when setting supported grow/shrink/basis longhands, while nonnumeric custom-property grow/shrink values keep the upstream-style separate longhand boundary.
- `removeProperty()` now removes `flex` shorthand groups and splits flex shorthands into surviving grow/shrink/basis longhands when one longhand is removed.
- Added `examples/wordpress-flex-cssom.php` for block layout flex-item edits without Node/WASM.

## Evidence

- Baseline focused check before this slice: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 387 assertions, 0 failures`.
- Red-first probe before implementation returned `NULL` for `getProperty("flex: none", "flex-grow")`, `NULL` for composing `flex` from grow/shrink/basis longhands, appended `flex-shrink` instead of mutating `flex: auto`, and left `flex: 2 0 10px` intact when removing `flex-grow`.
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 411 assertions, 0 failures`.
- Full lane after implementation: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2337 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-flex-cssom.php --self-test` => `OK`.
- Lint: `php -l lanes/lightningcss/src/DeclarationBlock.php`, `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`, and `php -l lanes/lightningcss/examples/wordpress-flex-cssom.php` all passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused DeclarationBlock evidence adds 24 assertions.
- Full LightningCSS PHP evidence moves from 2313 to 2337 pass / 0 fail.
- Conservative mapped coverage remains `1446 / 3532`; this deepens the already represented upstream DeclarationBlock CSSOM cluster rather than claiming a new denominator row.

## Non-Overlap

- This does not repeat accepted CSSOM font, border-radius, logical-axis, place-alignment, text-decoration, outline, background, border, border-image, transition, animation, list-style, gap, overflow, scroll-snap, mask-border, grid, inset, or flex-flow behavior.
- The only visible LightningCSS rework note is the stale 2026-05-25 `CustomMediaTransformer` import-tail conflict; this slice stayed on the assigned DeclarationBlock CSSOM family.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local declaration parser, priority buckets, top-level token splitting, shorthand update/remove machinery, and CSS serializers.
