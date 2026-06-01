# Property Values Color/Font/Grid Parity - 2026-06-01T16:12:14Z

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T161214Z`
- Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_grid`.
- Cluster: pretty-printer parity for quoted `grid-template` area rows inside ordinary style rules.

## Behavior

Upstream has focused `test_grid` pretty-printer rows for minified `grid-template` declarations with quoted area strings and adjacent bracketed line names. The native PHP formatter previously handled selected at-rules but rejected ordinary style rules, so these rows were not countable through `CssFormatter`.

This slice teaches `CssFormatter` to format top-level style rules and adds focused multiline formatting for `grid-template` values that contain quoted grid-area rows. The formatter keeps existing declaration normalization, splits combined boundary line names such as `[header-bottom main-top]` across adjacent pretty rows, and leaves non-area `grid-template` values on the existing declaration-value path.

## Evidence

- `php -l lanes/lightningcss/src/CssFormatter.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssFormatterTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-grid-template-formatter.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssFormatterTest.php` -> `1 test files, 16 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-grid-template-formatter.php --self-test` -> passed and printed the expected `.wp-block-cover__inner-container` formatted `grid-template`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8637 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Status Delta

- Direct focused formatter coverage adds 2 assertions for the two upstream `src/lib.rs::test_grid` pretty-printer rows.
- `phpPass`: stored lane status `8625 -> 8637` from the verified full focused lane run.
- `phpFail`: `0`.
- Conservative mapped coverage remains `2398 / 3532`; this deepens a represented grid property-value cluster without changing the upstream manifest denominator.

## Non-overlap

This avoids the accepted color, color-mix, relative color, font shorthand/font-face/font-family, grid minifier, grid CSSOM read-write, target-prefixing, media-query, selector, source-map, bundle/import graph, CSS Modules, parser recovery, and custom at-rule clusters. It only maps the ordinary style-rule pretty-printer behavior for quoted `grid-template` area rows from upstream `test_grid`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `CssFormatter`, stylesheet parsing helpers, and declaration-value formatting paths; it does not require Node, WASM, Rust runners, network access, or a new shared dependency.

## Next Task

Continue with non-overlapping property-value parity only where a remaining upstream color/font/grid row is not already represented by focused PHP assertions, or pivot to higher-priority LightningCSS source-map, bundle/import graph, media-query, CSSOM, CSS Modules, target-prefixing, selector, parser recovery, or custom at-rule parity gaps.
