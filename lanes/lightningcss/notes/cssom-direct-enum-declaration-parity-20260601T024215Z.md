# CSSOM Direct Enum Declaration Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T024215Z`

## Source Truth

- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` routes CSSOM reads and writes through typed `Property::parse_string(...)` and `DeclarationBlock::{get,set,remove}`, so typed properties serialize through their `ToCss` implementations.
- `src/properties/ui.rs` defines `ColorScheme` parsing with canonical output order `light`, `dark`, then `only`, and defines `PrintColorAdjust` as `economy | exact`.
- `src/properties/transition.rs` defines `view-transition-name` keywords `none | auto`, `view-transition-class` keyword `none`, and `view-transition-group` keywords `normal | contain | nearest`.

## Change

- `DeclarationBlock` now canonicalizes direct CSSOM declaration values for:
  - `color-scheme`
  - `print-color-adjust`, `-webkit-print-color-adjust`, and `-moz-print-color-adjust`
  - `view-transition-name`, `view-transition-class`, and `view-transition-group`
- Custom properties and custom idents remain case-preserving.
- Added `examples/wordpress-direct-enum-cssom.php` for WordPress theme/editor code that reads and updates color-scheme, print adjustment, and view-transition declarations without Node/WASM.

## Evidence

- Red-first probe before implementation: `color-scheme: Dark Light Only`, `print-color-adjust: Exact`, `-webkit-print-color-adjust: Economy`, and view-transition keyword values were returned and written with authored case instead of upstream canonical CSS.
- Baseline focused test before edit:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 908 assertions, 0 failures`
- Focused after edit:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 923 assertions, 0 failures`
- Full lane after edit:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 5536 assertions, 0 failures`
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

- Focused `DeclarationBlockTest.php` assertions: `908 -> 923` (`+15`).
- Full LightningCSS PHP assertions: `5521 -> 5536` (`+15`).
- Conservative mapped coverage remains `2303 / 3532`; this deepens the already represented upstream `DeclarationBlock` CSSOM cluster rather than claiming a new denominator row.

## Non-Overlap

This slice does not repeat accepted CSSOM shorthand work for background, border, overflow, animation, transition, mask, grid, font, container, caret, text decoration, text emphasis, or layout shorthands. It also avoids target-prefixing/minifier color-scheme and print-color-adjust clusters; the change is limited to direct CSSOM declaration read/write canonicalization.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `DeclarationBlock` parser, property-name normalizer, top-level whitespace splitter, and direct declaration serializer.

## Next

Continue CSSOM parity on a different non-overlapping typed property or shorthand family, or move to source-map, bundle/import graph, CSS Modules, media-query, target-prefixing, property-value, or custom at-rule parity.
