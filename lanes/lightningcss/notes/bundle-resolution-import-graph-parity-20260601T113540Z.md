# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01 11:35 UTC

## Slice

Ported the upstream bundle source-map policy for malformed inline `data:` input maps. When a bundled stylesheet contains a `sourceMappingURL=data:...` comment, upstream suppresses generated source collection for that stylesheet even if the data URL cannot be decoded as a valid Source Map v3 payload.

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source: `src/bundler.rs::load_file()` only adds the generated stylesheet source when `stylesheet.source_map_url(0)` is absent or does not start with `data`.
- Local native binding reproduction with `bundleAsync({ sourceMap: true })`:
  - Imported `/bad.css` with `/*# sourceMappingURL=data:application/json;base64,not-json */` emitted bundled CSS but omitted `bad.css` from `map.sources`.
  - Entry-only CSS with the same malformed inline data map emitted `"sources":[]` and `"sourcesContent":[]`.

## Implementation

- `CssBundler::addBundleSource()` now returns after any `data:` source-map URL, regardless of whether `SourceMap::fromDataUrl()` succeeds.
- Valid inline input maps still merge through `SourceMap::addSourceMap()`.
- Non-`data:` source-map URLs still fall back to generated CSS source collection, matching upstream's external map loading boundary.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 718 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  - passed, including `source-map-input-malformed: suppressed`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7589 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - passed

## Status Delta

- Full lane focused evidence moves from `7582` to `7589` passing assertions.
- `lane-status.json` keeps `phpFail` at `0`.
- Conservative mapped upstream inventory remains `2374 / 3532`; this deepens the already represented bundle/import graph source-map cluster instead of adding a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP `CssBundler`, `SourceMap`, inline source-map scanner, resolver/reader source-provider path, and import graph traversal. No Node, Rust, WASM runtime, package resolver, or external source-map loader is introduced.

## Non-Overlap

This does not repeat accepted valid inline input-map remapping, unused source pruning, source-map markers inside strings, rejected child merge behavior, CSS Modules dependency graph diagnostics, malformed import token diagnostics, external `.map` loading boundaries, media-query, CSSOM, property/value, target-prefix, or custom at-rule slices.

## Follow-Up

Remaining bundle source-map parity can target generated mapping offsets through final bundle printing. This slice only fixes source table collection policy for malformed inline `data:` input maps.
