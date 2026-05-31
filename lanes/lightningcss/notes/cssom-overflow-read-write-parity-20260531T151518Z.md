# CSSOM Overflow Declaration Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T151518Z`

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant upstream files:
  - `src/properties/overflow.rs` defines `overflow` as a shorthand for `overflow-x` and `overflow-y`.
  - `src/declaration.rs` implements `DeclarationBlock::get`, `set`, and `remove` behavior for shorthand longhand reads, in-place shorthand updates, and longhand removal by splitting a shorthand into the remaining longhands.

## Native Delta

- Added `DeclarationBlock` CSSOM support for `overflow`, `overflow-x`, and `overflow-y`.
- `getProperty()` now reads longhands from an `overflow` shorthand, composes `overflow` from matching-priority longhands, and preserves the existing important-before-normal bucket ordering.
- `setProperty()` now updates compatible `overflow` shorthands when writing `overflow-x` or `overflow-y`, compressing equal axis values to a one-token shorthand.
- `removeProperty()` now removes `overflow` as a group and splits `overflow` into the surviving axis longhand when one axis is removed.
- Added `examples/wordpress-overflow-cssom.php` for editor/migration tooling that edits scroll container overflow without Node.

## Evidence

- Red-first probe before implementation:
  - `php -r 'require "tools/bootstrap.php"; $b=new PortLibs\LightningCSS\DeclarationBlock(); var_export([$b->getProperty("overflow: hidden auto", "overflow-x"), $b->getProperty("overflow-x: hidden; overflow-y: auto", "overflow")]); echo "\n";'`
  - Result: both CSSOM reads returned `NULL`.
- Syntax:
  - `php -l lanes/lightningcss/src/DeclarationBlock.php`
  - `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-overflow-cssom.php`
- Focused tests:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 252 assertions, 0 failures`.
- Full lane tests:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 1844 assertions, 0 failures`.
- Example smoke:
  - `php lanes/lightningcss/examples/wordpress-overflow-cssom.php --self-test`
  - Result: `OK`.

## Coverage Accounting

- Focused assertion delta: `+15` DeclarationBlock assertions.
- Full LightningCSS lane evidence moves from `1829` to `1844 pass / 0 fail`.
- Conservative mapped coverage remains `1258 / 3532` because this deepens the already represented DeclarationBlock CSSOM cluster.

## Non-Overlap

This slice avoids the accepted CSSOM priority, background, border, inset, grid, gap, list-style, transition, animation, mask-border, scroll-snap, and shorthand-removal clusters. It targets only the remaining `overflow` shorthand/longhand read-write-remove behavior.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded `DeclarationBlock` parser, priority buckets, serializer, and top-level token splitter.
