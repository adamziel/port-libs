# Bundle Resolution Import Graph Parity 2026-06-01T13:10:26Z

## Scope

Ported a bounded LightningCSS bundle/import graph source-map parity edge:
inline input source maps from imported stylesheets are applied after final
bundle printing, so their generated mappings are offset by CSS emitted from
earlier imports.

The covered graph shape is:

- entry stylesheet imports a normal stylesheet first;
- a later imported stylesheet contains an inline `data:` source map;
- final minified bundle emits the first import before the inline-mapped import;
- the inline input map's generated column points at the later generated block,
  not column zero of the full bundle.

## Upstream Source Truth

Pinned upstream commit:
`parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.

Relevant source:

- `src/bundler.rs::bundle()` loads and orders the dependency graph before final
  stylesheet printing.
- `src/bundler.rs::load_file()` preserves input source-map URL state for the
  loaded stylesheet; upstream source-map remapping is handled by the printer
  against the generated bundle position.
- `node/test/bundle.test.mjs` includes bundle source-map graph coverage; this
  slice narrows that behavior to imported inline input maps after earlier
  generated imports.

## Changes

- Added `SourceMap::appendSourceMapWithGeneratedOffset()` for appending an
  input map with generated line/column offsets while remapping source and name
  tables.
- Changed `CssBundler` to defer parsed inline `data:` input source maps until
  after final minification, locate each generated stylesheet fragment, and
  append the input map at that generated offset.
- Added `css bundler offsets upstream inline source maps after earlier bundled
  imports` to `lanes/lightningcss/tests/CssBundlerTest.php`.
- Extended `lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  with the `source-map-input-offset: remapped` smoke marker.
- Updated `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` for current
  `13 files / 7976 assertions / 0 failures` PHP evidence and conservative
  mapped coverage `2393 / 3532`.

## Verification

- Pre-edit focused baseline:
  `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  -> `1 test files, 771 assertions, 0 failures`.
- Focused after:
  `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  -> `1 test files, 778 assertions, 0 failures`.
- Source-map focused:
  `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  -> `1 test files, 909 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  -> passed, including `source-map-input-offset: remapped`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  -> `13 test files, 7976 assertions, 0 failures`.
- PHP lint:
  `php -l` on changed PHP files -> no syntax errors.
- Whitespace:
  `git diff --check -- lanes/lightningcss` -> passed.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
`CssBundler`, `CssMinifier`, and `SourceMap` implementations.

## Non-Overlap

This does not repeat preserved unknown import source collection, malformed
inline source-map suppression, CSS Modules source-map remapping, resolver
diagnostics, media/import grammar work, or source-map generated-only VLQ
parsing. It adds the generated-position offset for a valid imported inline
input map after earlier bundle output.

## Next

Adjacent source-map work should cover ambiguous duplicate generated fragments
and external non-data `sourceMappingURL` loading if those are needed for
broader upstream bundle source-map parity.
