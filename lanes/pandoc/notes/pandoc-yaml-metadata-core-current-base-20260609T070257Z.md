# Pandoc YAML Metadata Core Current Base 20260609T070257Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-yaml-metadata-core-current-base-20260609T070257Z`
- Accepted base: `53cc273b044292e061f08ae6f6fdabc37210dcb0`
- Scope: native YAML/front-matter metadata parsing under `lanes/pandoc/**`

## Behavior

`MarkdownReader` now records `yaml-explicit-key-collection` provenance for explicit sequence and mapping keys in YAML metadata. The record keeps the normalized metadata path plus source shape, collection kind, flow/block style, member count, syntax family (`block`, `block-null`, `flow`, `flow-null`, or `set`), source line count, and source-line range.

The metadata values remain unchanged. The added provenance is for reviewer/import audit handoff only and does not expose private helper keys inside `meta`.

## Focused Evidence

- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4909 assertions, 0 failures`
  - Added PASS: `records pandoc yaml explicit collection key provenance for review handoff`
  - Added focused assertions: `89`
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`

## Non-Overlap

This does not repeat accepted YAML scalar explicit-key provenance, comments, anchors, aliases, merge keys, block scalars, flow collections, set values, typed scalars, or explicit collection tags. The new behavior is specifically collection provenance for complex explicit YAML keys after normalization.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP `MarkdownReader` YAML/front-matter parser, metadata provenance arrays, Pandoc-like AST attributes, and WordPress handoff example. Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF engines, browser renderers, external converters, online services, live provider tests, and live-service provider tests were not run.

## Next Task

A follow-up can preserve these explicit collection-key provenance records when writing YAML metadata, or add duplicate normalized collection-key diagnostics for review queues.
