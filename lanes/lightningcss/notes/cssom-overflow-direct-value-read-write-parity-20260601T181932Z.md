# CSSOM Overflow Direct Value Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T181932Z`

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant upstream files:
  - `src/properties/overflow.rs` parses `overflow` as one or two `OverflowKeyword` values and serializes equal axes as a single keyword.
  - `src/properties/mod.rs` maps `overflow-x` and `overflow-y` to the same keyword domain as the `overflow` shorthand.

## Native Delta

- `DeclarationBlock::parse()` now canonicalizes direct `overflow` declaration values through the existing overflow component parser and serializer.
- Direct `overflow-x` and `overflow-y` parse/write paths now lowercase upstream keywords while leaving custom-property values untouched.
- `setProperty()` now serializes direct `overflow` and axis writes using the same keyword canonicalization as CSSOM shorthand reads.
- The WordPress overflow example now covers imported uppercase declarations and direct canonical reset writes for scroll-container tooling.

## Evidence

- Baseline focused test before implementation:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 1366 assertions, 0 failures`.
- Red-first probe before implementation:
  - `php -r 'require "tools/bootstrap.php"; $b=new PortLibs\LightningCSS\DeclarationBlock(); var_export([$b->parse("overflow: HIDDEN Auto; overflow-x: Clip"), $b->setProperty("color: red", "overflow-x", "CLIP"), $b->setProperty("color: red", "overflow", "HIDDEN HIDDEN")]); echo "\n";'`
  - Result preserved authored uppercase tokens for direct `parse()` and `setProperty()` output.
- Syntax:
  - `php -l lanes/lightningcss/src/DeclarationBlock.php`
  - `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-overflow-cssom.php`
  - Result: no syntax errors.
- Focused tests:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 1373 assertions, 0 failures`.
- Example smoke:
  - `php lanes/lightningcss/examples/wordpress-overflow-cssom.php --self-test`
  - Result: `OK`.
- Diff hygiene:
  - `git diff --check -- lanes/lightningcss`
  - Result: passed.

## Coverage Accounting

- Focused assertion delta: `+7` DeclarationBlock assertions.
- LightningCSS lane status moves from `8934` to `8941` pass / `0` fail for the focused delta.
- Conservative mapped coverage remains `2399 / 3532` because this deepens the already represented CSSOM overflow declaration cluster.

## Non-Overlap

This slice avoids the accepted overflow shorthand read/write/remove support from `20260531T151518Z`. It only closes direct declaration value canonicalization for `parse()` and direct `setProperty()` paths for `overflow`, `overflow-x`, and `overflow-y`.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded `DeclarationBlock` declaration parser, top-level whitespace splitter, overflow component parser, priority buckets, and serializer.
