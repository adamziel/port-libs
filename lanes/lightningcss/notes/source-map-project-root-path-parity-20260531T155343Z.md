# Source Map Project Root Path Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T155343Z`
Base: `58c47241a5b6db59dbbfb8ad74725a55a4e899e0`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream behavior:
  - `parcel_sourcemap::utils::make_relative_path` strips `file://`, normalizes relative dot/slash paths, preserves non-file virtual specifiers containing `:`, and relativizes absolute POSIX/Windows paths against the SourceMap project root.
  - `SourceMap::add_source`, `SourceMap::add_sources`, `SourceMap::from_json`, and `SourceMap::add_vlq_map` route sources through that project-root normalization before serializing `sources`.

## Native PHP Delta

- `SourceMap` now accepts an optional project root and normalizes source paths in `addSource()`.
- `SourceMap::fromArray()` and `SourceMap::fromJson()` accept the same project root so raw v3/VLQ imports use the same source path semantics.
- `wordpress-source-map-vlq-offsets.php` now self-tests absolute theme paths, `file://` theme paths, and a virtual generated source in a WordPress-style map without Node or Rust.

## Verification

- Red-first behavior before implementation: `SourceMap::addSource()` preserved absolute and `file://` paths instead of writing upstream-style paths relative to the project root.
- `php -l lanes/lightningcss/src/SourceMap.php && php -l lanes/lightningcss/tests/SourceMapTest.php && php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 103 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 2036 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- PHP pass evidence moves from 2025 to 2036 assertions.
- Conservative mapped coverage moves from 1340 to 1342 of 3532 for project-root source normalization in added sources and raw JSON/VLQ imports.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this extends the lane-local native Source Map v3/VLQ support with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw VLQ generated-only import, line/column offset import, `addSourceMap()` line replacement, empty generated-line spans, `extendWithSourceMap()` input remapping, data URL/source-content defaults, CSS Modules, or CSSOM work. It specifically covers the remaining project-root path normalization behavior used when LightningCSS writes or imports source-map `sources`.
