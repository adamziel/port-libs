# CSSOM View Transition Custom Ident Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T114449Z`

## Source Truth

- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` routes declaration block reads and writes through typed `Property::parse_string(...)` and `DeclarationBlock::{get,set,remove}`.
- `src/properties/transition.rs` defines `view-transition-name` and `view-transition-group` as keyword-or-`CustomIdent` values.
- `src/values/ident.rs` defines `view-transition-class` via `NoneOrCustomIdentList`, whose list entries are CSS identifier tokens, not raw whitespace fragments.

## Change

- `DeclarationBlock` now parses escaped custom identifier tokens for:
  - `view-transition-name`
  - `view-transition-class`
  - `view-transition-group`
- Hex escapes with terminator whitespace, such as `c\61 rd-enter` and `nav\2d menu`, are decoded and serialized as `card-enter` and `nav-menu`.
- Custom properties stay raw and case-preserving.
- Extended `wordpress-direct-enum-cssom.php` with a block navigation transition workflow that reads and rewrites escaped view-transition identifiers without Node/WASM.

## Evidence

- Baseline focused test before edit:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 1188 assertions, 0 failures`
- Focused after edit:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 1194 assertions, 0 failures`
- Full LightningCSS lane after edit:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 7621 assertions, 0 failures`
- Syntax checks:
  - `php -l lanes/lightningcss/src/DeclarationBlock.php`
  - `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-direct-enum-cssom.php`
  - Result: all report `No syntax errors detected`
- Example smoke:
  - `php lanes/lightningcss/examples/wordpress-direct-enum-cssom.php --self-test`
  - Result: `OK`
- Diff hygiene:
  - `git diff --check -- lanes/lightningcss`
  - Result: pass

## Status Delta

- Focused `DeclarationBlockTest.php`: `1188 -> 1194` assertions (`+6`).
- Full LightningCSS PHP lane: `7615 -> 7621` assertions (`+6`).
- Conservative mapped coverage remains `2374 / 3532`; this deepens the already represented DeclarationBlock CSSOM cluster rather than claiming a new denominator row.

## Non-Overlap

This slice does not repeat accepted CSS Modules escaped view-transition scoping or the deferred duplicate `readCssIdentifierToken` handoff. It reuses the existing DeclarationBlock identifier reader and only wires it into direct CSSOM declaration values for `view-transition-name`, `view-transition-class`, and `view-transition-group`.

## Dependency Closure

No new support component is needed. The change reuses the lane-local `DeclarationBlock` parser/serializer, existing CSS escape reader, and existing CSS identifier serializer.

## Next

Continue with another non-overlapping CSSOM property family, or move to the current-base source-map, bundle/import graph, media-query, CSS Modules, custom-at-rule, property-value, or target-prefix parity queue.
