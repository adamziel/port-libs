# CSSOM Gap Read Write Parity 2026-05-31T13:50Z

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T135053Z`

Upstream source truth:

- `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs::DeclarationBlock::{get,set,remove}` defines the CSSOM behavior used here: `get` extracts longhands from shorthands, `set` updates a compatible shorthand in place before appending, and `remove` splits a containing shorthand into surviving longhands.
- `src/properties/align.rs` defines `gap` as a shorthand over `row-gap` and `column-gap`; omitted column values copy the row value, and serialization omits the second value when both components match.

Implemented behavior:

- `DeclarationBlock::getProperty()` now reads `row-gap` and `column-gap` from `gap`, composes `gap` from direct longhands when importance flags match, and preserves upstream priority-bucket ordering.
- `DeclarationBlock::setProperty()` updates an existing `gap` shorthand when setting `row-gap` or `column-gap` in the same priority bucket, including upstream one-value compression when both components match.
- `DeclarationBlock::removeProperty()` now removes `gap` plus direct `row-gap`/`column-gap`, and splits a containing `gap` shorthand into the surviving longhand when one component is removed.
- `examples/wordpress-gap-cssom.php` models WordPress block gap edits without Node.

Evidence:

- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 189 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1591 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-gap-cssom.php --self-test` => `OK`.
- Lint: `php -l lanes/lightningcss/src/DeclarationBlock.php`, `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`, and `php -l lanes/lightningcss/examples/wordpress-gap-cssom.php` all passed.
- JSON validation: `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json` and `lanes/lightningcss/lane-status.json` decoded successfully.

Coverage/status:

- Focused DeclarationBlock evidence adds 15 assertions and moves that file to 189 assertions.
- Full LightningCSS PHP evidence moves from `1576` to `1591` assertions.
- Conservative mapped coverage remains `1141 / 3532`; this is additional behavior inside the already represented upstream DeclarationBlock CSSOM cluster.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP declaration parser/serializer and bounded shorthand helpers.

Non-overlap and rework note:

- This does not repeat accepted CSSOM priority buckets, background, border, inset, grid, transition, list-style, or shorthand-group removal behavior. It targets the adjacent `gap` CSSOM declaration family.
- The only visible LightningCSS rework note is the stale 2026-05-25 CustomMediaTransformer import-tail conflict against base `02383337`; current accepted lane status already records later custom-media integrations, so this slice stayed on the assigned CSSOM behavior.
