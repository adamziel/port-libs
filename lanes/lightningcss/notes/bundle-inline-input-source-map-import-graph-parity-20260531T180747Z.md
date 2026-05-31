# LightningCSS Bundle Inline Input SourceMap Import Graph Parity 2026-05-31T18:07Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T180747Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/bundler.rs::load_file()` adds the generated CSS file to the output source map only when the parsed stylesheet has no input source map, or the source map URL is not an inline `data:` map.
  - `src/bundler.rs::test_source_map` verifies bundled source-map output replaces an imported generated CSS source with the original sources from its inline input map.

## Native Delta

- `CssBundler::bundleWithSourceMap()` and reader/file-backed source-map collection now scan each loaded local stylesheet for a `/*# sourceMappingURL=data:... */` input map.
- Valid inline source maps are parsed with the existing native `SourceMap::fromDataUrl()` and merged into the bundle source map through `addSourceMap()`.
- Generated imported CSS remains bundled normally, but its generated filename is no longer recorded as a bundle source when a valid inline input map supplies original sources and contents.
- Malformed inline maps fall back to the previous generated-source collection path rather than breaking unrelated bundle resolution.
- The WordPress bundle import graph smoke now covers a generated block stylesheet whose inline map points back to a Sass-like original source.

## Evidence

- Red-first probe before the patch: a bundle with `/theme/blocks/card.css` containing an inline `sourceMappingURL=data:...` produced source-map sources `["entry.css","blocks/card.css"]` and recorded generated CSS content instead of the input-map source.
- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 168 assertions, 0 failures`.
- Full LightningCSS lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2888 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exits 0 and prints `source-map-input: remapped`.
- PHP lint: `php -l lanes/lightningcss/src/CssBundler.php && php -l lanes/lightningcss/tests/CssBundlerTest.php && php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` passed.
- Diff hygiene: `git diff --check -- lanes/lightningcss` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused `CssBundlerTest.php` evidence moved from the accepted `154` assertions for the latest source-map/import graph cluster to `168` assertions.
- Full LightningCSS PHP evidence moves from `2881` to `2888` assertions.
- Conservative mapped coverage moves from `1637 / 3532` to `1638 / 3532`.

## Dependency Closure

No new support component is needed. This reuses native `CssBundler`, `SourceMap`, the existing data-URL/VLQ parser, source-provider reads, path normalization, and minification path. No Node, Rust, WASM, browser service, package resolver, or external source-map package is introduced.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default relative resolution, filesystem source-provider reads, escaped specifier decoding, resolver-result diagnostics, EOF import handling, import-prelude barriers, external import ordering errors, import modifier order parsing, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, CSS Modules dependency graphs, SourceMap raw VLQ/offset/project-root mechanics, CSSOM work, target prefixing, media-range validation, and custom at-rule visitor slices. The stale 2026-05-25 `CustomMediaTransformer` rework note remains historical for this slice because current accepted code already contains later custom-media import-tail coverage; this patch does not touch `CustomMediaTransformer.php`.

## Follow-Up

Remaining bundle/source-map parity work should focus on accurate generated mapping offsets through the final minified bundle printer, external `.map` URL loading policy, or deeper input-map `sourceRoot` handling if a future slice activates that support boundary.
