# Source Map Negative Leading Empty Child Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T080037Z`
Base accepted HEAD: `924608cb5d0660a91dc7f34f65c3d602f24fd8e6`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map mutation to `parcel_sourcemap 2.1.1` from the pinned `Cargo.lock`.
- Local source-truth files inspected:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
- Upstream `SourceMap::offset_lines()` stores inserted empty `MappingLine` spans, and `SourceMap::add_sourcemap()` moves each child mapping line to `line + line_offset`, skipping negative target lines while still replacing surviving empty child lines and later mappings.

## Native PHP Delta

- Added focused `SourceMapTest.php` coverage for a child map whose mapping is shifted behind two leading empty generated lines, round-tripped through the native buffer, and then merged into a parent with `addSourceMap(..., -1)`.
- The test pins exact VLQ output `;MCIEI;ADFFF;AACAC`, cleared parent line 0 lookup, kept child mapping at generated line 1, source/name/content table preservation, child consumption, and parent buffer round-trip behavior.
- Extended `wordpress-source-map-vlq-offsets.php --self-test` with the same generated block/theme source-map path.
- Updated lane-local status and manifest evidence. Conservative mapped coverage remains `2360 / 3532` because this deepens the already represented `parcel_sourcemap` SourceMap offset/add_sourcemap cluster.

## Verification

- Baseline focused source-map evidence before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 678 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 696 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6826 assertions, 0 failures`.
- Final PHP lint, JSON validation, and `git diff --check -- lanes/lightningcss` are recorded in the handoff response.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local native PHP Source Map v3/Base64 VLQ implementation and adds no Node, Rust runtime, WASM, browser service, external source-map package, or live-service dependency.

## Non-Overlap

No current LightningCSS rework note existed for this lane. This slice does not repeat accepted raw Source Map v3 import, byte-stream no-comma parsing, generated-only segments, positive or negative raw-map line/column offsets, all-skipped table preservation, invalid-index guards, duplicate generated-column offsets/search, unsorted raw-line sort entrypoints, middle-line column drains, column-drained child spans, positive leading empty child offset spans, `addSourceMap()` consumption-only coverage, `extendWithSourceMap()`, project-root normalization, JSON/data URL defaults, CSS Modules, CSSOM, media-query, target-prefixing, bundle/import graph, property-value, or custom-at-rule work. It is limited to negative `add_sourcemap` offsets where a leading empty child span is partly skipped, the surviving empty child line clears a parent line, and a later child mapping is retained.
