# CSSOM Background Position Read/Write Parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260531T184642Z`

Upstream source truth:

- Upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`
- `src/declaration.rs`: `DeclarationBlock::set` updates an existing shorthand through `set_longhand`, and `DeclarationBlock::remove` splits a shorthand into remaining longhands when removing a contained longhand.
- `src/properties/background.rs`: `background-position` is a CSSOM shorthand over `background-position-x` and `background-position-y`.

Implemented behavior:

- `setProperty(..., background-position-x/y, ...)` now updates an existing direct `background-position` declaration in place when the layer counts can be composed.
- `removeProperty(..., background-position-x/y)` now splits a direct `background-position` shorthand into the remaining axis longhand.
- `removeProperty(..., background-position)` now removes direct `background-position`, `background-position-x`, and `background-position-y` declarations in the same priority bucket.
- The existing WordPress background CSSOM smoke now covers hero focal-point set/remove behavior without Node/WASM.

Red-first evidence:

- Before the implementation, a local probe returned `background-position: 20px 10px; background-position-x: left` for setting `background-position-x`, left `background-position: 20px 10px` unchanged when removing `background-position-x`, and left `background-position-x: 30px` when removing `background-position`.

Verification:

- `php -l lanes/lightningcss/src/DeclarationBlock.php` passed.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-background-cssom.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` passed: `1 test files, 567 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 3149 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-background-cssom.php --self-test` passed: `OK`.
- `git diff --check -- lanes/lightningcss` passed.

Status delta:

- Full lane PHP pass count moves from `3141` to `3149`, a `+8` focused assertion delta.
- Manifest mapped coverage remains `1696 / 3532` because this deepens the already mapped DeclarationBlock CSSOM denominator cluster.

Non-overlap:

- This does not repeat accepted background shorthand read/get, background longhand updates inside `background`, mask, border, grid, animation, transition, source-map, CSS Modules, media-query, or target-prefixing clusters.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP `DeclarationBlock` parser/serializer and focused PHP test harness.
