# Property Values Grid Shorthand Values Parity

Slice: `lightningcss-property-values-color-font-grid-parity-20260601T142224Z`

Source truth:

- Upstream `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/lib.rs::test_grid`, standalone `grid` shorthand `minify_test` cases.

Behavior covered:

- Direct `grid` shorthand minification for `none`, quoted area rows with tracks, named line blocks, minmax/fit-content tracks, and slash-separated row/column tracks.
- Row and column `auto-flow` shorthand canonicalization, including `dense auto-flow` to `auto-flow dense`.
- The upstream default-row auto-flow compaction where `grid: auto-flow / 200px` serializes as `grid:none/200px`.
- WordPress smoke coverage for query, group, and cover block layout styles using the same shorthand forms without Node/WASM.

Implementation note:

- No new native support component was needed. The existing PHP grid value minifier already carried the behavior; this slice makes the pinned upstream shorthand-value cluster explicit and countable in the focused PHP suite.

Non-overlap:

- This does not repeat the previous explicit grid track-list slice, grid-template longhand composition, grid-template-area override composition, grid auto-flow CSSOM, grid display prefixing, or target-prefixing slices.

Verification:

- `php -l lanes/lightningcss/tests/CssMinifierTest.php` - pass.
- `php -l lanes/lightningcss/examples/wordpress-grid-shorthand-values.php` - pass.
- `php lanes/lightningcss/examples/wordpress-grid-shorthand-values.php --self-test` - pass.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` - `1 test files, 2043 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 8202 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - pass.

Status delta:

- Focused full-lane PHP evidence moves `8184 -> 8202` assertions from the 18 new upstream `grid` shorthand checks.
- Conservative mapped coverage remains `2393 / 3532`; this deepens an already represented `src/lib.rs::test_grid` cluster rather than claiming new denominator rows.

Dependency closure:

- No new support component or external runtime is required.
