# Bundle Resolution Import Graph Parity

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T150428Z`

Source truth:
- Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `src/bundler.rs` loads each import graph source, suppresses generated bundle sources when an input stylesheet has a data URL source map, and lets the final bundle printer remap child input maps at their emitted locations.
- A native upstream probe using `bundleAsync()` with `@import "screen.css" screen` and `@import "print.css" print`, where both children minify to `.card{color:green}` but each has a distinct inline input map, emitted mappings at generated columns `14` and `46` with source indexes `1` and `2`.

Behavior:
- `CssBundler` now records the source index for each pending inline input source map.
- During inlining, emitted stylesheet source indexes are recorded in final graph order.
- Pending input maps are applied in emitted source-index order, and duplicate generated CSS fragments are searched from a moving cursor before falling back to the old full-bundle search.
- This keeps repeated imported fragments under different wrappers, such as `@media screen` and `@media print`, attached to the correct generated column instead of reusing the first matching fragment.

Red-first evidence:
- Before this patch, a local PHP probe with duplicate `.card{color:green}` imported under `screen` and `print` remapped both child input source maps to generated column `14`.
- Upstream maps the same shape to generated columns `14` and `46`, preserving source indexes `1` then `2`.

Verification:
- `php -l lanes/lightningcss/src/CssBundler.php` => no syntax errors
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` => no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 798 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exit 0, including `source-map-duplicate-fragment-offset: remapped`
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 8332 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` => exit 0

Status delta:
- Full lane assertion count moves from `8327` to `8332`.
- Conservative mapped coverage remains `2393 / 3532` because this deepens the existing bundle/import graph source-map row rather than adding a new manifest denominator row.

Non-overlap:
- This does not repeat the accepted CSSOM list-style/counter read-write parity, color-adjust target-prefix parity, existing single inline input source-map offset cases, malformed source-map suppression, CSS Modules import graph source maps, target-prefixing, custom at-rule, selector, media-query, parser recovery, or property/value slices.

Dependency closure:
- No new support component is needed. The patch reuses `CssBundler`, `CssMinifier`, `SourceMap`, the existing data URL input-map importer, and the lane-local WordPress import-graph smoke.
