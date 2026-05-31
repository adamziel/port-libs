# CSSOM List Style Read Write Parity 2026-05-31T13:26Z

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T132656Z`

Upstream source truth:

- `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs::DeclarationBlock::{get,set,remove}` defines the CSSOM behavior used here: `get` extracts longhands from shorthands, `set` updates a compatible shorthand in place before appending, and `remove` splits a containing shorthand into surviving longhands.
- `src/properties/list.rs` defines `list-style` as a shorthand over `list-style-position`, `list-style-image`, and `list-style-type`, in that longhand replacement order.

Implemented behavior:

- `DeclarationBlock::getProperty()` now reads `list-style` from an existing shorthand, reads individual `list-style-*` longhands from a shorthand, and composes a shorthand from all three direct longhands when importance flags match.
- `DeclarationBlock::setProperty()` updates an existing `list-style` shorthand when setting `list-style-type`, `list-style-image`, or `list-style-position` in the same priority bucket, while preserving upstream priority-bucket append behavior for opposite-priority writes.
- `DeclarationBlock::removeProperty()` now removes `list-style` plus its direct longhands, and splits a containing `list-style` shorthand into the surviving upstream-ordered longhands when one component is removed.
- `examples/wordpress-list-style-cssom.php` models WordPress navigation/list marker edits without Node.

Evidence:

- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 147 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1431 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-list-style-cssom.php --self-test` => `OK`.
- Lint: `php -l lanes/lightningcss/src/DeclarationBlock.php`, `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`, and `php -l lanes/lightningcss/examples/wordpress-list-style-cssom.php` all passed.
- Diff hygiene: `git diff --check -- lanes/lightningcss` passed.

Coverage/status:

- Focused DeclarationBlock evidence adds 14 assertions and moves that file to 147 assertions.
- Full LightningCSS PHP evidence moves from `1417` to `1431` assertions.
- Conservative mapped coverage remains `1069 / 3532`; this is additional behavior inside the already represented upstream DeclarationBlock CSSOM cluster.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP declaration parser/serializer and bounded shorthand helpers.

Non-overlap:

- This does not repeat accepted CSSOM priority buckets, background read/write, border longhand splitting, transition shorthand/longhand behavior, or shorthand-group removal. It targets the adjacent `list-style` CSSOM declaration family.
