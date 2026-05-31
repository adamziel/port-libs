# LightningCSS Grid Longhand Composition Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T135552Z`

Accepted base: `7f53fcd353eeefd16948edc334eb7d1204b1ec5b`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '22689,23277p'`.
- Mapped 8 focused upstream `src/lib.rs::test_grid` longhand composition behaviors beyond the accepted direct grid/grid-template value minifier cluster.

Native PHP delta:

- `CssMinifier` now composes compatible `grid-template-areas`, `grid-template-rows`, and `grid-template-columns` declarations into `grid-template`.
- Missing area rows are synthesized with upstream dot rows when there are more row tracks than authored area rows.
- `grid-template-areas:none` with row/column tracks collapses to `grid-template:rows/columns`, and all-none rows/columns collapse to `grid-template:none`.
- Initial `grid-auto-flow:row`, `grid-auto-rows:auto`, and `grid-auto-columns:auto` promote the composed template into a `grid` shorthand.
- Compact row track tokenization handles minified adjacency such as `[header-top]auto[header-bottom main-top]1fr[main-bottom]`.
- Area composition bails out for repeat-based column tracks where upstream does not expose a safe explicit area/track shorthand.
- `wordpress-grid-value-minifier.php` now includes an archive query layout whose grid template longhands collapse to a compact `grid` shorthand without Node.

Evidence:

- `php -l lanes/lightningcss/src/CssMinifier.php && php -l lanes/lightningcss/tests/CssMinifierTest.php && php -l lanes/lightningcss/examples/wordpress-grid-value-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 810 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1627 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-grid-value-minifier.php` -> exits 0 and emits expected minified block grid CSS.

Non-overlap:

- Does not repeat accepted direct `grid`/`grid-template` shorthand value minification, grid CSSOM read/write behavior, Color 4 value minification, font-family/font-shorthand/font-face/font-palette/font-feature slices, display flex prefixing, CSS Modules bundle dependency graph behavior, or media range feature-flag behavior.
- This slice is only declaration-block composition for remaining compatible grid template longhand clusters.

Dependency closure:

- No new support component is needed. This reuses the bounded native `CssMinifier` declaration-entry composer, top-level CSS scanner, grid area normalizer, and grid value minifier helpers.

Root harness status: not run - isolated micro-slice.
