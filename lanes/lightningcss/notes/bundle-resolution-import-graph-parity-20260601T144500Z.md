# Bundle Resolution Import Graph Parity - Repeated Layered Descendants

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T144500Z`

Source truth:
- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Native NAPI probe used `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` with `bundleAsync({ minify: true })`.
- Upstream direct transform probe for `@layer foo{.d{color:purple}}.c{color:blue}@layer foo{.b{color:green}}.a{color:red}` outputs `@layer foo{.d{color:purple}.b{color:green}}.c{color:#00f}.a{color:red}`.
- Upstream bundle probe for an entry importing `card.css` under `layer(foo)`, then `c.css`, then `card.css` again under `layer(foo)`, where both imported files depend on `d.css`, outputs `@layer foo{.d{color:purple}.b{color:green}}.c{color:#00f}.a{color:red}`.

Behavior ported:
- `CssMinifier` now merges repeated top-level named `@layer` blocks back to the layer's first top-level occurrence across intervening ordinary rules or at-rules.
- The merge is scoped to import-safe segments and does not cross top-level `@import`.
- Source-order preservation remains active for layer-statement runs with nested layer bodies, matching the existing upstream nested-layer bundler case.
- `CssBundlerTest` now covers repeated layered imports with shared descendants, so bundle output follows the upstream layer consolidation visible after import graph resolution.
- `wordpress-bundle-import-graph.php` now includes a block-theme smoke where repeated layered block imports share design-token descendants and the intervening gallery CSS remains unlayered.

Red-first evidence:
- Before the patch, the PHP probe for `@layer foo{.d{color:purple}}.c{color:blue}@layer foo{.b{color:green}}.a{color:red}` returned `@layer foo{.d{color:purple}}.c{color:#00f}@layer foo{.b{color:green}}.a{color:red}`.
- Before the patch, the PHP bundle probe for the repeated `layer(foo)` import graph returned split layer blocks around the intervening unlayered import instead of upstream's single first-occurrence layer block.

Verification:
- `php -l lanes/lightningcss/src/CssMinifier.php` - passed.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` - passed.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - passed.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 795 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` - `1 test files, 2049 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - passed, including `repeated-layer-import-descendants: merged`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 8296 assertions, 0 failures`.

Status delta:
- `phpPass` moves `8290 -> 8296`.
- Conservative mapped coverage remains `2393 / 3532`; this deepens represented `src/bundler.rs::test_bundle` and `src/lib.rs::test_layer` behavior rather than claiming a new denominator row.
- Rust/Node/WASM upstream runners were not run as broad suite gates.

Dependency closure:
- No new support component is needed. The slice reuses the native PHP `CssBundler`, `CssMinifier`, layer parser, and import graph tests. No Node/Rust/WASM runtime is introduced.

Non-overlap:
- This does not repeat accepted import source escaping, source-map import graph offsets, resolver result/read diagnostics, external import ordering, media/supports/layer import parsing, CSS Modules dependency graph behavior, custom media sharing, target prefixing, CSSOM, source maps, custom at-rules, or property-value work.
- The patch is limited to upstream layer consolidation behavior as it affects repeated layered import descendants.
