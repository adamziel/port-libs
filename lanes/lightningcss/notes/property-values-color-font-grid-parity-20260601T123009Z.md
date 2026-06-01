# LightningCSS Property Values Color Font Grid Parity - 2026-06-01T12:30:09Z

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T123009Z`
- Accepted base: `bc90f87db2ed4ad7ae3d007cb6eabda51a9348d1`
- Source truth: pinned upstream `parcel-bundler/lightningcss`
  `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_grid`.

## Behavior Ported

Added focused PHP parity coverage for the remaining upstream grid property-value
tail that was not pinned in the lane tests:

- four-column `grid-template-areas` rows such as `". a a ."` compress into a
  `grid-template` shorthand without losing empty-cell placement;
- `grid-template-rows: repeat(2, 1fr)` with explicit areas stays as longhands,
  matching LightningCSS' non-composition behavior for repeated explicit rows;
- `grid: <rows> / auto-flow [auto|dense]` preserves column auto-flow shorthand
  semantics while normalizing area row whitespace.

The existing native PHP `CssMinifier` already matched these upstream cases, so
this slice adds the missing source-truth assertions and a WordPress block-query
example smoke without changing production source.

## Verification

- `php -l lanes/lightningcss/tests/CssMinifierTest.php`
  - passed: no syntax errors detected.
- `php -l lanes/lightningcss/examples/wordpress-grid-column-auto-flow-parity.php`
  - passed: no syntax errors detected.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`
  - passed: `1 test files, 1982 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - passed: `13 test files, 7777 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-grid-column-auto-flow-parity.php --self-test`
  - passed and emitted the expected minified WordPress grid CSS.

- `git diff --check -- lanes/lightningcss`
  - passed.

## Status Delta

- Added 6 focused upstream-backed PHP assertions.
- Updated `lane-status.json` from `7771` to `7777` PHP assertions.
- Conservative mapped upstream coverage remains `2392 / 3532`; no manifest
  denominator row was changed.

## Non-Overlap

This slice avoids the previously accepted 2026-06-01 grid/font/color work:
LCH/OKLCH color-mix polar tails, font target/supports fallback behavior, and
the earlier grid track/placement shorthand rows already covered by
`CssMinifierTest.php`.

## Dependency Closure

No new dependency or support component is needed. The slice reuses the existing
native PHP `CssMinifier` grid declaration machinery; upstream Rust/Node/WASM
runners were not executed for this isolated micro-slice.

## Next

Continue property-value parity with unmapped color/font/grid rows or CSSOM
read/write cases that are not already covered by `CssMinifierTest.php` and
`DeclarationBlockTest.php`.
