# Source Map Lookup Getter Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T165026Z`
Base: `0085a1ea4345161dd1557b6642190f68f0e010d1`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream behavior:
  - `SourceMap::add_sources`, `get_source_index`, `get_source`, and `get_sources` normalize and expose source identifiers through the same project-root path logic as `add_source`.
  - `SourceMap::set_source_content` leaves sparse empty entries available to `get_source_content` after later source-content writes.
  - `SourceMap::add_names`, `get_name_index`, `get_name`, and `get_names` deduplicate and expose source-map names.
  - `SourceMap::get_mappings` exposes source-backed and generated-only mappings after generated column and line offsets.

## Native PHP Delta

- `SourceMap` now exposes native `addSources()`, source lookup/content getters, `addNames()`, name lookup getters, and `getMappings()`.
- The focused test verifies path-normalized source lookup, sparse source content, name deduplication, out-of-range guards, and mapping inspection after `offsetColumns()` and `offsetLines()`.
- `wordpress-source-map-vlq-offsets.php` now self-tests source/name/mapping lookup for theme, block, and generated editor CSS sources without Node or Rust.

## Verification

- Red-first focused run after adding the test failed on missing `SourceMap::addSources()`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 131 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 2331 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- Root harness: not run - isolated micro-slice.

## Status

- PHP pass evidence moves from 2313 to 2331 assertions.
- Conservative mapped coverage moves from 1446 to 1450 of 3532 for four `parcel_sourcemap` lookup/getter/mapping-inspection behaviors.
- Dependency closure: no new support component is needed; this extends the lane-local native Source Map v3/VLQ support and adds no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw VLQ import/remapping, generated-only segments, line/column offset import, `offsetColumns()`/`offsetLines()`/`addEmptyMap()`, empty generated-line spans, `addSourceMap()` line replacement, input source-map remapping, project-root source normalization, or JSON/data-url defaults. The stale 2026-05-25 CustomMedia rework note was inspected and is unrelated to this current source-map slice.
