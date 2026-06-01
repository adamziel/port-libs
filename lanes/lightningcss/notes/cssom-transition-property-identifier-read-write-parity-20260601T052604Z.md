# CSSOM transition property identifier read/write parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260601T052604Z`

Source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` is the upstream API shape for `DeclarationBlock::get`, `set`, and `remove`.
- `src/declaration.rs` updates shorthand/longhand declarations through `PropertyId`.
- `src/properties/transition.rs` parses transition shorthand property names as `PropertyId` values, so escaped and case-varied known property names serialize canonically while custom-property names preserve case.

Implementation:

- `DeclarationBlock` now canonicalizes transition property identifiers for `transition-property` and `transition` CSSOM read/write paths.
- Known/non-custom CSS property identifiers are decoded and lowercased, including escaped identifiers such as `c\6f lor`.
- Custom properties such as `--Block-Opacity` retain case.
- Existing transition list-length behavior is preserved: mismatched longhand lists still do not synthesize a transition shorthand.

Verification:

- `php -l lanes/lightningcss/src/DeclarationBlock.php`
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
- `php -l lanes/lightningcss/examples/wordpress-transition-cssom.php`
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 1018 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 6213 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-transition-cssom.php --self-test`
  - Result: `OK`
- `git diff --check -- lanes/lightningcss`
  - Result: passed

Coverage/status:

- Focused assertion growth: `+15` in `DeclarationBlockTest.php`.
- `lane-status.json` `phpPass`: `6198 -> 6213`.
- Conservative mapped denominator remains `2359 / 3532`; this deepens the already represented CSSOM DeclarationBlock cluster.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP declaration parsing/tokenization helpers.

Non-overlap:

- Does not touch bundle/import graph, CSS Modules, source maps, custom at-rules, media-query lowering, target-prefixing, or minifier transition composition.
- The old custom-media rework note from 2026-05-25 was inspected and is stale relative to this CSSOM micro-slice; no `CustomMediaTransformer.php` changes were made.
