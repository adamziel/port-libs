# CSSOM Animation Read Write Parity 2026-05-31T13:46Z

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T134620Z`

Upstream source truth:

- `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs::DeclarationBlock::{get,set,remove}` defines CSSOM behavior where longhands are extracted from shorthands, compatible same-priority longhand writes update a shorthand in place, and longhand removal splits a containing shorthand into remaining longhands.
- `src/properties/animation.rs` declares `animation` as a list shorthand over `animation-name`, `animation-duration`, `animation-timing-function`, `animation-iteration-count`, `animation-direction`, `animation-play-state`, `animation-delay`, `animation-fill-mode`, and `animation-timeline`.

Implemented behavior:

- `DeclarationBlock::getProperty()` now reads animation duration, timing function, delay, iteration count, direction, fill mode, play state, and timeline from `animation` shorthand layers, including default values and comma-separated layer lists.
- `DeclarationBlock::setProperty()` updates compatible animation shorthands for same-priority longhand writes when the layer counts match, appends the longhand when they do not, and preserves opposite-priority shorthand declarations.
- `DeclarationBlock::removeProperty()` now removes `animation` plus its direct longhands, and splits an `animation` shorthand into upstream-ordered surviving longhands when removing one longhand.
- `examples/wordpress-animation-cssom.php` now self-tests block animation name/duration/fill CSSOM edits without Node.

Evidence:

- Red check before implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` failed the new animation CSSOM assertions with `1 test files, 177 assertions, 3 failures`.
- Green focused check: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 193 assertions, 0 failures`.
- Full lane check: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1595 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-animation-cssom.php --self-test` => `OK`.
- Lint: `php -l lanes/lightningcss/src/DeclarationBlock.php`, `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`, and `php -l lanes/lightningcss/examples/wordpress-animation-cssom.php` all passed.
- Diff hygiene: `git diff --check -- lanes/lightningcss` passed.

Coverage/status:

- Focused DeclarationBlock evidence adds 19 assertions and moves that file to 193 assertions.
- Full LightningCSS PHP evidence moves from `1576` to `1595` assertions.
- Conservative mapped coverage remains `1141 / 3532`; this is additional behavior inside the already represented upstream DeclarationBlock CSSOM cluster.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP declaration parser/serializer, priority-bucket partitioning, and bounded shorthand parsing helpers.

Non-overlap:

- This does not repeat accepted CSSOM priority buckets, background, border, inset, grid, transition, list-style, or shorthand-group removal behavior. It targets the adjacent animation shorthand longhand CSSOM family beyond the previously accepted `animation-name` case.
