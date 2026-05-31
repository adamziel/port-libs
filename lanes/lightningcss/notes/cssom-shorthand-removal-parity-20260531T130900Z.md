# CSSOM Shorthand Removal Parity 2026-05-31T13:09Z

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T130900Z`

Upstream source truth:

- `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` exercises `DeclarationBlock::remove` through the CSSOM helper.
- `src/declaration.rs::DeclarationBlock::remove` removes an exact property id and, when the requested property is a shorthand, removes its direct longhand property ids from both normal and `!important` declaration buckets. When removing a longhand, it splits a containing shorthand and keeps the other longhands.

Implemented behavior:

- `DeclarationBlock::removeProperty()` now applies upstream shorthand-group removal for the modeled non-background shorthand groups: physical border shorthands, `flex-flow` / `-webkit-flex-flow`, and grid placement/template shorthands.
- Removing `border`, `border-color`, or a physical side shorthand drops direct component longhands such as `border-top-color` and `border-left-width` while preserving unrelated declarations.
- Removing `flex-flow` only drops same-prefix `flex-direction` and `flex-wrap`; removing `-webkit-flex-flow` preserves unprefixed flex declarations.
- Removing `grid-area`, `grid-template`, or `grid` drops their direct grid longhands while preserving unrelated auto-flow or non-grid declarations as upstream does for direct longhand ids.

Evidence:

- Red-first focused run before implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` failed the new shorthand-removal case because `border-top-color` and `border-left-width` survived `removeProperty(..., 'border')`.
- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 116 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1348 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-cssom-shorthand-removal.php --self-test` => `OK`.

Coverage/status:

- PHP evidence moves from `1339` to `1348` assertions.
- Conservative mapped coverage remains `1042 / 3532`; this is additional evidence inside the already represented upstream `DeclarationBlock` CSSOM cluster rather than a new denominator row.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP declaration parser and CSSOM declaration block model.

Non-overlap:

- This does not repeat the accepted CSSOM priority-bucket, background shorthand, border longhand splitting, or grid-template read behavior. It targets the inverse upstream removal path for shorthand property ids and included direct longhands.
