# CSSOM Background Attachment/Origin/Clip Parity

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files: `src/declaration.rs` `DeclarationBlock::{get,set,remove}` and `src/properties/background.rs` `Background::longhands`, `Background::longhand`, `Background::set_longhand`, and background shorthand parsing/printing.
- This deepens the already represented `tests/test_cssom.rs` DeclarationBlock cluster rather than adding a new denominator row.

Behavior added:

- `DeclarationBlock::getProperty()` now exposes `background-attachment`, `background-origin`, and `background-clip` from `background` shorthands, including default `scroll`, `padding-box`, and `border-box` values and `background-clip: text`.
- `DeclarationBlock::setProperty()` now updates those longhands in-place inside an existing `background` shorthand when the layer count matches, while preserving the existing separate-longhand fallback for mismatched layer counts.
- `DeclarationBlock::removeProperty(..., 'background')` now removes those supported longhands along with the `background` shorthand across priority buckets.
- `examples/wordpress-background-cssom.php` now covers fixed/local layered hero backgrounds and text-clipped cover media without Node/WASM.

Evidence:

- Red-first before implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` failed with `1 test files, 390 assertions, 3 failures`.
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` passed with `1 test files, 401 assertions, 0 failures`.
- Full lane-focused verification: `php tools/run-tests.php lanes/lightningcss/tests` passed with `13 test files, 2327 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-background-cssom.php --self-test` exited `0`.
- Syntax checks passed for `DeclarationBlock.php`, `DeclarationBlockTest.php`, and `wordpress-background-cssom.php`.
- `git diff --check -- lanes/lightningcss` passed.
- Dependency closure: no new support component is needed; this reuses the bounded native `DeclarationBlock` parser, priority buckets, and background layer scanner.

Root harness: not run - isolated micro-slice.
