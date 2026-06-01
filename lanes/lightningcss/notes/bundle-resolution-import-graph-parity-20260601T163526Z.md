# Bundle Import Graph Source Map URL Metadata Parity

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T163526Z`

Base accepted HEAD: `961d532798b4f10d7a9114bf6d87ff0b412e3bc9`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/bundler.rs` flattens every loaded stylesheet's `source_map_urls` into the bundled `StyleSheet` after import graph traversal.
- `src/stylesheet.rs` records `parser.current_source_map_url()` for each parsed source, preserving `None`, external URLs, and inline `data:` URLs as source-index metadata.

## Change

- `CssBundler` now records the parsed source-map URL for each loaded stylesheet and exposes the ordered `sourceMapUrls` list from array-returning bundle APIs.
- Source-map generation still preserves the existing upstream behavior: lowercase `data:` URLs are consumed as input maps and suppress generated source collection, malformed lowercase `data:` maps suppress generated sources, and non-data/mixed-case URLs keep generated CSS sources.
- The WordPress bundle import graph smoke now checks a block stylesheet graph with an external source-map URL, inline `data:` URL, and no directive.

## Red-First Probe

Before the patch, a local `bundleWithSourceMap()` probe over `/theme/entry.css` importing an external-map stylesheet and an inline-data-map stylesheet reported `missing` for `sourceMapUrls`.

## Focused Evidence

- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - Before: `1 test files, 840 assertions, 0 failures`
  - After: `1 test files, 844 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  - Passed; includes `source-map-url-import-graph: preserved`
- `php -l lanes/lightningcss/src/CssBundler.php`
  - Passed
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
  - Passed
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  - Passed
- `git diff --check -- lanes/lightningcss`
  - Passed

## Non-Overlap

This does not repeat accepted inline input source-map remapping, malformed lowercase `data:` suppression, mixed-case `Data:` generated-source collection, directive tokenization, resolver/read diagnostics, CSS Modules source maps, media/layer/supports import graph wrapping, or external import ordering. It covers the upstream bundle metadata list that survives import graph traversal.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local `CssBundler`, existing `SourceMap` data URL parser, CSS minifier, and source-map directive tokenizer; no Rust, Node, WASM, browser, filesystem service, or external source-map loader is introduced.
