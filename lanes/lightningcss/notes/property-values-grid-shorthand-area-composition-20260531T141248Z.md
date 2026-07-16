# LightningCSS Grid Shorthand Area Composition Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T141248Z`

Accepted base: `a187757827b58c999a1fc6cda2f4be5e163b73e9`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '22540,23380p'`.
- Mapped 4 additional focused upstream `src/lib.rs::test_grid` shorthand composition behaviors beyond the accepted direct grid shorthand minifier and grid-template longhand composition clusters.

Native PHP delta:

- `CssMinifier` now composes compatible `grid` and `grid-template` shorthand declarations with later `grid-template-areas`.
- Later `grid-template-rows` or `grid-template-columns` declarations override the shorthand side before composition.
- Missing area rows are synthesized with upstream dot rows when the row track list is longer than the authored area rows.
- Default `auto` row tracks are omitted in the compact area-row shorthand, matching the existing direct shorthand serializer.
- Unsupported `grid` auto-flow shorthands and unsafe `none` row sides bail out to preserve authored declarations.
- `wordpress-grid-template-area-shorthand.php` models query/gallery block grid shorthands that collapse after area declarations without Node.

Evidence:

- Red-first current output preserved separate declarations for the targeted shorthand-plus-area cases, for example `.test-miss-areas-4{grid:30px 60px 100px/1fr 1fr 1fr;grid-template-areas:"a a a""b c c"}`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 822 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1763 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-grid-template-area-shorthand.php --self-test` -> `OK`.
- `php -l lanes/lightningcss/src/CssMinifier.php && php -l lanes/lightningcss/tests/CssMinifierTest.php && php -l lanes/lightningcss/examples/wordpress-grid-template-area-shorthand.php` -> no syntax errors.
- `git diff --check -- lanes/lightningcss` -> passes.

Non-overlap:

- Does not repeat accepted direct `grid`/`grid-template` shorthand value minification, grid track/area/placement longhand minification, CSSOM grid read/write behavior, grid-template longhand composition, Color 4 value minification, font-family/font-shorthand/font-face/font-palette/font-feature slices, flex longhand prefixing, CSS Modules bundle dependency graph behavior, or media range validation/fallback behavior.
- This slice is only declaration-block composition for existing grid/grid-template shorthands followed by later area declarations.

Dependency closure:

- No new support component is needed. This reuses the bounded native `CssMinifier` declaration-entry composer, top-level splitter, grid area normalizer, grid track tokenizer, and grid-template shorthand serializer.

Root harness status: not run - isolated micro-slice.
