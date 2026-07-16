# CSSOM Mask Border Read Write Parity 2026-05-31T14:53Z

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T145352Z`

Upstream source truth:

- `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs::test_get` includes `mask-border: linear-gradient(red, green) 25` returning `mask-border-source`.
- `src/declaration.rs::DeclarationBlock::{get,set,remove}` defines the shared CSSOM behavior: longhands are extracted from shorthands, compatible same-priority longhand writes update a shorthand in place, and longhand removal splits a containing shorthand into surviving longhands.
- `src/properties/masking.rs` defines `mask-border` as a shorthand over `mask-border-source`, `mask-border-slice`, `mask-border-width`, `mask-border-outset`, `mask-border-repeat`, and `mask-border-mode`.

Implemented behavior:

- `DeclarationBlock::getProperty()` now reads every supported `mask-border-*` longhand from `mask-border`, including default `none`, `100%`, `1`, `0`, `stretch`, and `alpha` values, and composes `mask-border` from complete same-priority longhands.
- `DeclarationBlock::setProperty()` updates compatible `mask-border` shorthands for longhand writes such as `mask-border-source`, `mask-border-slice`, and `mask-border-mode`, while appending a separate declaration when an opposite-priority shorthand must be preserved.
- `DeclarationBlock::removeProperty()` now removes `mask-border` plus direct longhands, and splits `mask-border` into upstream-ordered surviving longhands when a single longhand is removed.
- `examples/wordpress-mask-border-cssom.php` self-tests block frame mask asset replacement and source removal without Node.

Evidence:

- Focused check: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 225 assertions, 0 failures`.
- Full lane check: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1776 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-mask-border-cssom.php --self-test` => `OK`.
- Lint: `php -l lanes/lightningcss/src/DeclarationBlock.php`, `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`, and `php -l lanes/lightningcss/examples/wordpress-mask-border-cssom.php` all passed.
- JSON validation: `lanes/lightningcss/lane-status.json` decoded successfully.
- Diff hygiene: `git diff --check -- lanes/lightningcss` passed.

Coverage/status:

- Focused DeclarationBlock evidence adds 17 assertions and moves the file from 208 to 225 assertions.
- Full LightningCSS PHP evidence moves from `1759` to `1776` assertions.
- Conservative mapped coverage remains `1232 / 3532`; this is additional behavior inside the already represented upstream DeclarationBlock CSSOM cluster.

Dependency closure:

- No new support component is needed. This reuses the existing native PHP declaration parser/serializer, priority-bucket partitioning, URL/string normalization, and bounded shorthand expansion helpers.

Non-overlap and rework note:

- This does not repeat accepted CSSOM priority buckets, background, border, inset, grid, transition, list-style, gap, animation, or shorthand-group removal behavior. It targets the adjacent `mask-border` CSSOM declaration family.
- The only visible LightningCSS rework note is the stale 2026-05-25 CustomMediaTransformer import-tail conflict against base `02383337`; current accepted lane status already records later custom-media integrations, so this slice stayed on the assigned CSSOM behavior.
