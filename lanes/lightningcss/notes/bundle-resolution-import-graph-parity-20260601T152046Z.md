# Bundle Resolution Import Graph Parity - License Offset Input Maps

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T152046Z`

Source truth:
- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `src/bundler.rs::bundle()` collects imported stylesheet license comments into the final stylesheet after import graph ordering.
- Upstream `src/bundler.rs::test_source_map()` proves bundled imported files with inline input source maps are remapped into the final output map, and `src/bundler.rs::test_license_comments()` plus `src/stylesheet.rs` printer output prove preserved license comments are emitted before normal rules with a newline.

Behavior covered:
- Added focused `CssBundlerTest` coverage for an entry importing a license-bearing CSS file before a generated CSS file with an inline input source map.
- The assertion pins final bundled CSS with the hoisted license comment, source replacement from `blocks/generated-card.css` to `blocks/generated-card.scss`, and VLQ remapping to generated line `1`, column `17`.
- Added the same block-theme path to `wordpress-bundle-import-graph.php` for generated block CSS after a preserved package license comment.

Current-base probe:
- Before editing, a PHP probe on this accepted worktree returned `/*! keep */\n.base{color:#00f}.card{color:green}.entry{color:red}` with mappings `;iBEAA`, generated line `1`, generated column `17`, and source index `2`.
- No production code change was needed; the patch makes this upstream-backed edge countable and guarded in the focused PHP lane.

Verification:
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 804 assertions, 0 failures`.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - passed.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - passed.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - passed, including `source-map-input-license-offset: remapped`.

Status delta:
- `CssBundlerTest.php` moved `795 -> 804` focused assertions.
- Projected lane `phpPass` moves `8399 -> 8408`; `phpFail` remains `0`.
- Conservative mapped coverage remains `2393 / 3532`; this deepens represented upstream bundler/source-map/license-comment behavior rather than claiming a new denominator row.
- Rust/Node/WASM upstream runners were not run as broad suite gates.

Dependency closure:
- No new support component is needed. The slice reuses native PHP `CssBundler`, `SourceMap`, existing inline input source-map remapping, and the block-theme bundle smoke.

Non-overlap:
- This does not repeat accepted one-line earlier-import source-map offsets, quoted fragment source-map matching, malformed inline map suppression, sourceMappingURL tokenization, resolver/read diagnostics, external import ordering, layered/media/supports import parsing, CSS Modules dependency graph behavior, or target/CSSOM/property-value work.
- The patch is limited to source-map generated line/column offsets after preserved license comments in bundle/import graph output.
