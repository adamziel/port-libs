# Source Map Buffer Round-Trip Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T180641Z`
Base: `e83ba68ab62e3e93ee2dcf9fc87ea144ffeb366d`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream behavior:
  - `parcel_sourcemap::SourceMap::to_buffer()` serializes the source-map inner state, including sources, source contents, names, and mapping lines.
  - `parcel_sourcemap::SourceMap::from_buffer(project_root, ...)` restores that state with a new project root.
  - Offset-created empty generated-line spans must survive the round trip so later VLQ writing and nested-map replacement see the same generated line count.

## Native PHP Delta

- `SourceMap::toBuffer()` writes a lane-local native snapshot containing sources, source contents, names, mappings, and `generatedLineCount`.
- `SourceMap::fromBuffer()` restores that snapshot, validates mapping/index shape, and reinstates project-root source lookup behavior.
- `wordpress-source-map-vlq-offsets.php` now self-tests buffer round-tripping for an offset-created empty generated-line span used by generated theme CSS maps.

## Verification

- Red-first behavior before implementation: the focused test failed on missing `PortLibs\LightningCSS\SourceMap::toBuffer()`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 193 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 2839 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- PHP lint and `git diff --check -- lanes/lightningcss` are part of final verification.
- Root harness: not run - isolated micro-slice.

## Status

- Focused SourceMap assertions move from 179 to 193.
- Full LightningCSS PHP evidence moves from 2825 to 2839 pass / 0 fail.
- Conservative mapped coverage moves from 1616 to 1617 of 3532 for the additional `parcel_sourcemap::SourceMap::to_buffer/from_buffer` state round-trip behavior.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ support with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 generated-only/name imports, line/column raw-map import offsets, negative raw-VLQ line-offset import, relative VLQ guard failures, byte-stream no-comma parsing, `offsetColumns()`/`offsetLines()`/`addEmptyMap()`, empty generated-line span creation, `addSourceMap()` line replacement, `extendWithSourceMap()` input remapping, project-root normalization, JSON/data URL defaults, source/name/mapping getters, public offset overflow guards, CSS Modules, CSSOM, bundler, media-query, or target-prefixing work. It is limited to the upstream buffer snapshot round-trip behavior for source maps after offsets have created internal empty generated lines.
