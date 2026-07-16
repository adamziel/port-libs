# LightningCSS Bundle Import Modifier Order Parity 2026-05-31T17:19Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T171945Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/parser.rs` parses `@import` preludes in this order: source, optional `layer` / `layer(...)`, optional `supports(...)`, then media.
  - `src/bundler.rs::load_file()` carries the parsed import layer, supports, and media through the dependency graph before wrapping bundled rules.
  - `src/bundler.rs::inline()` preserves external import rules before bundled imports, so the parsed import modifier/media meaning must survive serialization.

## Native Delta

- `CssBundler::parseImportStatement()` now parses import modifiers in upstream order instead of accepting `layer` and `supports` in any later position.
- A bare `layer` token after `supports(...)` is now treated as the trailing media type, not as an anonymous cascade layer modifier.
- External imports preserve `supports(display:flex) layer` as supports plus media instead of reordering it into `layer supports(...)`.
- `wordpress-bundle-import-graph.php` now smokes the external-import path for a block-theme bundle with `supports(...) layer` before bundled local CSS.

## Evidence

- Baseline before the patch:
  - `@import "b.css" layer(theme.blocks) supports(display: flex) layer` serialized as `@supports (display:flex){@layer{...}}`, losing the named layer and misclassifying the trailing media type.
  - External `@import "https://cdn.example/theme.css" supports(display: flex) layer` serialized as `@import "... " layer supports(display:flex)`.
- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 135 assertions, 0 failures`.
- Full LightningCSS lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2576 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exits 0 and prints `supports-layer-media: preserved`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2574` to `2576` assertions.
- Conservative mapped coverage: `1562 / 3532` to `1564 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the native `CssBundler` import scanner, resolver boundary, media wrapping path, `CssMinifier`, and existing bundle exception model. No Node, Rust, browser service, parser generator, external package resolver, or filesystem crawler is introduced.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default relative resolution, filesystem source-provider reads, escaped specifier decoding, resolver-result shape diagnostics, EOF import handling, import-prelude barriers, external import ordering errors, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, CSS Modules dependency graphs, source-map offsets, CSSOM work, target prefixing, media-range validation, and custom at-rule visitor slices. The stale 2026-05-25 `CustomMediaTransformer` rework note has already been covered by later accepted custom-media scanner/import-tail notes and is not touched here.
