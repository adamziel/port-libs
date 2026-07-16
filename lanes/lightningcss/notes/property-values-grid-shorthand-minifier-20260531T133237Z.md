# LightningCSS Grid Shorthand Value Minifier Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T133237Z`

Accepted base: `39b47e3d7563ca406403433b251e48bb5e25f850`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '22912,23028p'`.
- Mapped 14 additional upstream `src/lib.rs::test_grid` `minify_test` helpers beyond the accepted grid track/area/placement cluster.

Native PHP delta:

- `CssMinifier` now compacts row string and track-size boundaries in direct `grid-template` and `grid` shorthands, including `"a" 100px "b" 1fr` and `[line] "a" 100px [line2]`.
- Direct `grid` shorthand values now normalize dense `auto-flow` ordering and serialize the upstream default row auto-flow case `grid: auto-flow / 200px` as `grid:none/200px`.
- `wordpress-grid-value-minifier.php` now models editorial block grid shorthands and a simple default-row auto-flow normalization path without Node.

Evidence:

- `php -l lanes/lightningcss/src/CssMinifier.php lanes/lightningcss/tests/CssMinifierTest.php lanes/lightningcss/examples/wordpress-grid-value-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 802 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1539 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-grid-value-minifier.php` -> exits 0 and emits the expected minified block grid CSS.
- `git diff --check -- lanes/lightningcss` -> passes.

Non-overlap:

- Does not repeat accepted grid-template-columns line-name/repeat, grid-auto-rows, grid-template-areas, grid-auto-flow longhand, grid-row/grid-column/grid-area placement, CSSOM grid shorthand, or advanced Color 4/font slices.
- This slice is only direct value minifier parity for remaining `grid` and `grid-template` shorthand cases from `src/lib.rs::test_grid`.

Dependency closure:

- No new support component is needed. This reuses the bounded native `CssMinifier` declaration-value scanner, top-level splitter, grid line-name merger, and grid numeric-dimension minifier.
