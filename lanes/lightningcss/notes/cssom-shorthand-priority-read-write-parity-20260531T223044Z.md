# CSSOM Shorthand Priority Read/Write Parity

- Slice: `lightningcss-cssom-declaration-read-write-parity-20260531T223044Z`
- Upstream source: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-truth files: `tests/test_cssom.rs` for DeclarationBlock get/set/remove behavior and `src/declaration.rs` / `src/macros.rs` for the priority-bucket rule in `DeclarationBlock::get()` and generated `Shorthand::from_longhands()`.

## Behavior

Upstream stores normal declarations and `!important` declarations in separate buckets. For shorthand reads, generated `from_longhands()` rejects composition when declarations contributing to the requested shorthand come from mixed priority buckets. Longhand reads still return the higher-priority latest longhand.

This slice ports that rule for native PHP CSSOM reads in the focused DeclarationBlock groups used by the upstream CSSOM helper cluster:

- physical box shorthands such as `margin`;
- logical box axis shorthands such as `margin-inline`;
- `flex-flow`;
- `flex`.

The write path is covered by setting normal and important longhands, then reading the affected shorthand. The serialized write keeps both buckets, while the shorthand read returns `null`, matching upstream's mixed-priority composition guard.

## Evidence

- Red-first probe before the fix:
  - `getProperty("margin: 1px; margin-top/right/bottom/left: 2px !important", "margin")` returned `2px !important`.
  - `getProperty("flex-flow: row wrap; flex-direction: column !important; flex-wrap: nowrap !important", "flex-flow")` returned `column nowrap !important`.
- Focused test: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 824 assertions, 0 failures`.
- Full lane test: `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 4675 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-cssom-priority-buckets.php --self-test`
  - Result: `OK`.

## Non-overlap

This deepens the existing CSSOM DeclarationBlock cluster. It does not touch bundle/import graph, CSS Modules, source-map, media-query, target-prefixing, custom at-rule, or property-value minifier behavior. Conservative mapped coverage remains unchanged because the CSSOM helper denominator is already represented.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `DeclarationBlock` parser/serializer and focused PHP tests only; upstream Rust/Node/WASM runners were not executed.
