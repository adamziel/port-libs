# Property Values Color/Font/Grid Parity - 2026-06-01T16:52:21Z

Slice: `lightningcss-property-values-color-font-grid-parity-20260601T165221Z`

## Source Truth

- Upstream pinned LightningCSS checkout:
  `/home/claude/port-libs/.upstream-cache/lightningcss` at
  `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Focused upstream rows: `src/lib.rs::test_grid` non-minified `test(...)`
  pretty-printer cases where `grid-template-areas`, `grid-template-rows`, and
  `grid-template-columns` compose into `grid-template` output.

## Red-First Probe

Before this patch, the native formatter preserved the longhands instead of
matching upstream's composed `grid-template`:

```sh
php -r 'require "tools/bootstrap.php"; $formatter = new PortLibs\LightningCSS\CssFormatter(); echo $formatter->format(".foo{grid-template-rows:auto 1fr;grid-template-columns:auto 1fr auto;grid-template-areas:none}");'
```

Observed output:

```css
.foo {
  grid-template-rows: auto 1fr;
  grid-template-columns: auto 1fr auto;
  grid-template-areas: none;
}
```

## Changes

- Added conservative grid-template longhand composition in `CssFormatter`.
- Covered five upstream-aligned formatter rows:
  - `grid-template-areas: none` plus row/column tracks composes to
    `grid-template: rows / columns`;
  - named area rows with boundary line names preserve LightningCSS formatting;
  - leading `auto` row tracks are omitted in composed area rows;
  - missing area rows are filled with `.` cells when extra row tracks exist;
  - all-`none` template longhands compose to `grid-template: none`.
- Updated `wordpress-grid-template-formatter.php` to exercise block layout
  longhand composition without Node/Rust/WASM.
- Updated lane status and manifest evidence. `phpPass` moves `8792 -> 8797`;
  conservative mapped coverage remains `2398 / 3532` because this deepens the
  already represented `src/lib.rs::test_grid` property-value cluster.

## Verification

- `php -l lanes/lightningcss/src/CssFormatter.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssFormatterTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-grid-template-formatter.php`
  -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssFormatterTest.php` ->
  `1 test files, 25 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-grid-template-formatter.php` ->
  exited `0` and printed expected formatted WordPress grid CSS.
- `php tools/run-tests.php lanes/lightningcss/tests` ->
  `13 test files, 8797 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted grid minifier shorthand/value coverage, grid
CSSOM read/write coverage, font-family/font shorthand formatter work, color
and color-mix minifier/fallback rows, CSS Modules, bundle/import graph,
source-map, media-query, target-prefixing, custom at-rule, selector, or parser
recovery slices. It only closes `CssFormatter` pretty output for upstream
`test_grid` template longhand composition.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`CssFormatter` declaration parser, token splitter, and focused PHP test
harness. It does not require Node, Rust, WASM, network access, or external
provider credentials.

## Follow-Up

Continue LightningCSS parity on non-overlapping property-value, CSSOM,
source-map, bundle/import graph, CSS Modules, media-query, target-prefix,
custom-at-rule, selector, and parser recovery gaps with upstream-backed PHP
assertions.
