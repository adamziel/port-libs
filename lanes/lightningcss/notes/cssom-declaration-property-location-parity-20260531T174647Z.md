# CSSOM Declaration Property Location Parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260531T174647Z`

Accepted base: `b1feedb755e93656cf717884940e8c64724c26f1`

Upstream source truth: `parcel-bundler/lightningcss` pinned cache commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

## Behavior

- Ported declaration key/value source-range lookup from upstream `DeclarationBlock::property_location` and `StyleRule::property_location`.
- Added `DeclarationBlock::propertyLocation()` for zero-based declaration indexes with returned `key` and `value` start/end line-column ranges.
- Added `StylesheetParser::propertyLocation()` for zero-based rule paths, including nested at-rules, so stylesheet-level callers can match upstream style-rule property location behavior.
- Added WordPress block-style smoke coverage in `examples/wordpress-cssom-property-location.php` for editor diagnostics that need to highlight the exact declaration key/value span.

## Evidence

- `php -l lanes/lightningcss/src/DeclarationBlock.php`: no syntax errors.
- `php -l lanes/lightningcss/src/StylesheetParser.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/StylesheetParserTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-cssom-property-location.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php lanes/lightningcss/tests/StylesheetParserTest.php`: `2 test files, 548 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 2805 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-cssom-property-location.php --self-test`: `OK`.
- `git diff --check -- lanes/lightningcss`: passed.

## Non-Overlap

This slice does not repeat the accepted caret, container, background, border, mask, flex, grid, or priority-bucket CSSOM get/set/remove clusters. It fills the narrower upstream CSSOM source-location API gap for declarations and leaves mapped denominator coverage unchanged at `1601 / 3532`.

## Dependency Closure

No new support component is needed. The implementation reuses lane-local CSS scanners and source-offset arithmetic; no Node, WASM, Rust runner, external provider, or new shared dependency is required.
