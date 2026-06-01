# CSSOM UI Direct Enum Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T033451Z`

## Source Truth

- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` routes CSSOM declaration reads and writes through typed `Property::parse_string(...)` and `DeclarationBlock::{get,set,remove}`, so known property values serialize through their upstream `ToCss` implementations.
- `src/properties/ui.rs` defines `Resize` keywords `none | both | horizontal | vertical | block | inline`, `UserSelect` keywords `auto | text | none | contain | all`, and `Appearance` keywords including `textfield`, `menulist-button`, `searchfield`, and `textarea`.
- `src/properties/mod.rs` exposes prefixed `user-select` and `appearance` properties, while upstream `Appearance` preserves non-standard identifiers.

## Change

- `DeclarationBlock` now canonicalizes known UI direct enum CSSOM values for:
  - `resize`
  - `user-select`, `-webkit-user-select`, `-moz-user-select`, and `-ms-user-select`
  - `appearance`, `-webkit-appearance`, `-moz-appearance`, and `-ms-appearance`
- Non-standard `appearance` identifiers remain case-preserving, matching upstream fallback behavior for unknown identifiers.
- Custom properties remain untouched.
- The WordPress direct enum CSSOM example now covers these UI declarations alongside the previously accepted color-scheme, print-color-adjust, and view-transition direct enum declarations.

## Evidence

- Red-first probe before implementation: `resize: Horizontal`, `user-select: Text`, prefixed `user-select`, and known `appearance` values were returned and written with authored case instead of upstream canonical CSS.
- Baseline focused test before edit:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 935 assertions, 0 failures`
- Focused after edit:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 949 assertions, 0 failures`
- Full lane after edit:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 5807 assertions, 0 failures`
- Example smoke:
  - `php lanes/lightningcss/examples/wordpress-direct-enum-cssom.php --self-test`
  - Result: `OK`
- Syntax checks:
  - `php -l lanes/lightningcss/src/DeclarationBlock.php`
  - `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-direct-enum-cssom.php`
  - Result: all report `No syntax errors detected`
- Diff hygiene:
  - `git diff --check -- lanes/lightningcss`
  - Result: pass

## Status Delta

- Focused `DeclarationBlockTest.php` assertions: `935 -> 949` (`+14`).
- Full LightningCSS PHP assertions: `5793 -> 5807` (`+14`).
- Conservative mapped coverage remains `2320 / 3532`; this deepens the already represented upstream CSSOM `DeclarationBlock` cluster rather than claiming a new denominator row.

## Non-Overlap

This slice does not repeat the accepted CSSOM shorthand declaration families, the earlier direct enum color-scheme/print/view-transition slice, or target-prefixing UI behavior. It is limited to CSSOM read/write canonicalization for UI direct enum longhands and their supported prefixed declarations. The stale May 25 custom-media rework note is unrelated to this CSSOM declaration slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `DeclarationBlock` parser, property-name normalizer, keyword canonicalizer, and direct declaration serializer.

## Next

Continue CSSOM parity on a different non-overlapping typed property family, or move to source-map, bundle/import graph, CSS Modules, media-query, target-prefixing, property-value, or custom at-rule parity.
