# LightningCSS bundle resolution/import graph parity - 2026-06-01T161420Z

## Slice

- Lane: `lightningcss`
- Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T161420Z`
- Base accepted HEAD: `ec3bcd9ad95b8f5fb0e5f5fb2227076702e7d642`
- Upstream source truth: `parcel-bundler/lightningcss` pinned at `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Source-Truth Evidence

- Inspected pristine upstream `src/bundler.rs` from the pinned commit with `git show`.
- In `load_file`, upstream only suppresses generated source-map source entries when the extracted source map URL starts with the case-sensitive prefix `data`.
- A mixed-case `Data:` source-map URL therefore does not count as an inline input source map upstream; the bundled generated CSS file remains the source-map source. Lowercase `data:` URLs still remap to the inline input map.

## Red-First Probe

- Before the patch, a local `CssBundler::bundleWithSourceMap()` probe with an imported `/*# sourceMappingURL=Data:... */` stylesheet produced source-map sources `["entry.css","blocks/case.scss"]`.
- That showed the PHP port was lowercasing the URL and consuming mixed-case `Data:` as an inline input source map, instead of retaining the generated imported CSS source like upstream.

## Implementation

- Updated `CssBundler::addBundleSource()` to use the upstream case-sensitive `str_starts_with($sourceMapUrl, 'data')` check when deciding whether to consume an inline input source map.
- Added focused bundle/import graph coverage proving mixed-case `Data:` keeps the generated imported CSS source while a sibling lowercase `data:` source map still remaps to its original source.
- Extended the WordPress bundle/import graph example with the same source-map URL edge for block/theme CSS.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` -> no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> 1 test file, 826 assertions, 0 failures
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` -> passed, including `source-map-mixed-case-data-url: generated-source`
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 8641 assertions, 0 failures
- `git diff --check -- lanes/lightningcss` -> passed

## Status Delta

- `phpPass`: 8635 -> 8641
- `phpFail`: 0
- Mapped coverage remains conservative at `2398 / 3532`; this deepens an already represented bundle/import graph source-map parity cluster.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP bundler, source-map parser, resolver, and source-provider paths.

## Non-Overlap / Follow-Up

- Avoided already accepted inline source-map remapping, duplicate source-map fragment offsets, sourceMappingURL directive tokenization, source provider, CSS Modules, media/layer import, and resolver diagnostic clusters.
- Follow-up candidates: continue source-map import graph parity around non-`data` URL schemes, external source-map URL preservation, and generated source entries for malformed but prefix-matching source map URLs.
