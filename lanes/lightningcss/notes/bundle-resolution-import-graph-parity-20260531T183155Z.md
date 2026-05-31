# LightningCSS Bundle Resolution Import Graph Parity 2026-05-31T18:31Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T183155Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream read: `src/bundler.rs::test_source_map` verifies a bundled import graph whose imported generated stylesheet has an inline input source map. The upstream final bundle map contains the entry source, the mapped original Sass source, and stdin, but omits the unmapped Sass variables partial from the imported input map.
- Targeted implementation read: `src/bundler.rs::load_file()` only adds the generated CSS source to the output source map when the parsed stylesheet has no inline data input map; otherwise the input map is merged into the bundle map.

## Native Delta

- `SourceMap::addSourceMap()` now copies child source and name records lazily as child mappings reference them, instead of copying every source/name declared by the nested map.
- `CssBundler::bundleWithSourceMap()` keeps the existing inline input map import path, but final bundle source maps now omit unused original sources from imported generated stylesheets.
- `wordpress-bundle-import-graph.php` now smokes a generated block stylesheet whose inline input map includes an unused token partial and asserts that only the mapped block source remains in the final bundle source list.

## Evidence

- Red-first probe before the patch: an ad hoc bundle with `/compiled.css` carrying an inline input map with sources `["unused.scss","used.scss"]` and mappings `ACAA` produced final sources `["entry.css","unused.scss","used.scss"]`.
- `php -l lanes/lightningcss/src/SourceMap.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 175 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` => `1 test files, 201 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 3064 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exits 0 and prints `source-map-input-unused: pruned`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence moves from `3060` to `3064` assertions.
- Conservative mapped coverage remains `1684 / 3532`; this deepens the already mapped bundled inline input-source-map/import-graph cluster rather than adding a new denominator row.

## Dependency Closure

No new support component is needed. This reuses native `CssBundler`, `SourceMap`, data-URL/VLQ parsing, import graph source-provider reads, path normalization, and minification. No Node, Rust, WASM, external source-map package, browser service, or filesystem crawler is introduced.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default relative resolution, filesystem source-provider reads, escaped specifier decoding, escaped URL delimiter scanning, resolver-result diagnostics, EOF import handling, import-prelude barriers, external import ordering errors, import modifier order parsing, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, CSS Modules dependency graphs, SourceMap raw VLQ/offset/project-root mechanics, source-map buffer round trips, CSSOM work, target prefixing, media-range validation, and custom at-rule visitor slices. The stale 2026-05-25 `CustomMediaTransformer` rework note remains historical and unrelated to this source-map merge path.
