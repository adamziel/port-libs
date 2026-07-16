# CSSOM Alignment Declaration Read/Write Parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260601T164136Z`

Base accepted HEAD: `a73a1bf2eb438c0b7d1aaf949b3c1caa3e3707b1`

Source truth:

- Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` routes `DeclarationBlock::get`, `set`, and `remove` through parsed `Property` values and `ToCss` serialization.
- `src/properties/mod.rs` maps `align-content`, `justify-content`, `align-self`, `align-items`, and their WebKit-prefixed forms to typed alignment properties; `justify-self` and `justify-items` are unprefixed.
- `src/properties/align.rs` canonicalizes `first baseline` to `baseline`, `last baseline`, `safe` / `unsafe` positions, content distribution keywords, left/right justification, and legacy `justify-items` ordering.

Implementation:

- `DeclarationBlock` now canonicalizes direct CSSOM alignment declaration values during `parse()`, `getProperty()`, and `setProperty()`.
- The covered properties are `align-content`, `justify-content`, `align-self`, `justify-self`, `align-items`, `justify-items`, `-webkit-align-content`, `-webkit-justify-content`, `-webkit-align-self`, and `-webkit-align-items`.
- WebKit-prefixed alignment longhands remain isolated from the unprefixed CSSOM names, matching the upstream property table rather than aliasing reads across prefixes.
- Custom properties remain raw and case-preserving.
- The WordPress place-alignment example now covers canonical direct alignment writes and legacy WebKit-prefixed alignment declaration reads/writes used by editor CSS.

Evidence:

- Red-first probe before implementation returned raw authored values for direct writes such as `align-content: FIRST Baseline` and prefixed reads/writes such as `-webkit-align-content: SAFE Center`.
- `php -l lanes/lightningcss/src/DeclarationBlock.php`
  - `No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php`
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php`
- `php -l lanes/lightningcss/examples/wordpress-place-alignment-cssom.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-place-alignment-cssom.php`
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `1 test files, 1344 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-place-alignment-cssom.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8796 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

Status delta:

- Full LightningCSS PHP lane assertions move from `8776` to `8796` (`+20`).
- Conservative mapped coverage remains `2398 / 3532`; this deepens the existing DeclarationBlock CSSOM cluster.

Dependency closure:

- No new support component is needed. This reuses the lane-local native PHP DeclarationBlock parser and existing place-alignment component serializer. No Rust, Node, WASM, network, provider, or live-service runner is required.

Non-overlap:

- This does not repeat accepted object-fit/object-position, transform, flex, direct enum, gap, shorthand priority/removal, CSS Modules, source-map, or target-prefixing place-alignment boundary slices.
- It is limited to typed alignment declaration serialization during CSSOM read/write/remove paths.
