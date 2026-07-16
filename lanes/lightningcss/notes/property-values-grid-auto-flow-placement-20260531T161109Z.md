# LightningCSS Grid Auto-Flow Placement Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T161109Z`

Accepted base: `8c7b034bb5fb3d061acb6b56e46103da8721d7a6`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '23279,23529p'`.
- Mapped 11 focused upstream `src/lib.rs::test_grid` declaration-composition cases beyond the accepted direct grid value and grid-template longhand clusters.

Native PHP delta:

- `CssMinifier` now composes compatible non-default `grid-auto-flow`, `grid-auto-rows`, and `grid-auto-columns` longhand groups into `grid` shorthands when upstream does.
- Non-composable auto-placement groups still compose `grid-template` while preserving upstream auto-track declaration order as rows, columns, then flow.
- A later `grid-template-rows` declaration now rewrites the row side of an existing `grid` shorthand for the targeted upstream case.
- `grid-row-start`/`grid-row-end`, `grid-column-start`/`grid-column-end`, and all four placement longhands now fold to `grid-row`, `grid-column`, or `grid-area` where upstream does.
- `wordpress-grid-value-minifier.php` now models a query/post-template auto-placement layout and featured card placement longhand collapse without Node.

Evidence:

- `php -l lanes/lightningcss/src/CssMinifier.php && php -l lanes/lightningcss/tests/CssMinifierTest.php && php -l lanes/lightningcss/examples/wordpress-grid-value-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 885 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 2103 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-grid-value-minifier.php` -> exits 0 and emits the expected minified block grid CSS.
- `git diff --check -- lanes/lightningcss` -> exits 0.

Non-overlap:

- Does not repeat accepted direct `grid`/`grid-template` shorthand value minification, accepted grid-template area/row/column composition, accepted grid CSSOM read/write behavior, accepted Color 4 color-function and color-mix slices, or accepted font-family/font-shorthand/font-face/font-palette/font-feature slices.
- This slice is only the remaining `src/lib.rs::test_grid` auto-placement composition plus placement-longhand declaration composition cluster.

Dependency closure:

- No new support component is needed. This reuses the bounded native `CssMinifier` declaration-entry composer, top-level splitters, grid line/value minifiers, and grid-template serializer.

Root harness status: not run - isolated micro-slice.
