# Source Map JSON Version Guard Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T213209Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS uses `parcel_sourcemap` `2.1.1` in the pinned `Cargo.lock`.
- Upstream source truth: `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`.
- Relevant upstream behavior: `SourceMap::from_json()` deserializes a required `version: u8` field before `add_vlq_map()` consumes mappings.

## Implementation

- `SourceMap::fromJson()` and `SourceMap::fromArray()` now reject missing, non-integer, negative, and out-of-range source-map `version` values before reading mappings, sources, names, or VLQ vectors.
- The valid raw JSON/array path still accepts `version: 3` and preserves existing VLQ import behavior.
- `wordpress-source-map-vlq-offsets.php` now self-tests the invalid JSON/array version guard for generated block/theme source-map ingestion.

## Verification

- Red-first probe before implementation: `SourceMap::fromJson('{"mappings":";C","sources":[],"names":[]}')` and `SourceMap::fromJson('{"version":"3","mappings":";C","sources":[],"names":[]}')` both returned `OK ;C`.
- Baseline before implementation: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 test files, 305 assertions, 0 failures.
- `php -l lanes/lightningcss/src/SourceMap.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 test files, 313 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php lanes/lightningcss/tests/CssBundlerTest.php`: 2 test files, 644 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 test files, 4461 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status Delta

- Focused SourceMap assertions move from 305 to 313.
- Full LightningCSS lane assertions move from 4453 to 4461.
- Conservative mapped coverage moves from 2145 to 2146 of 3532 for the `parcel_sourcemap` raw JSON version-u8 guard before VLQ import.

## Dependency Closure

No new support component is needed. This reuses the lane-local native Source Map v3/Base64 VLQ parser and adds no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw VLQ import/remapping, generated-only segments, line/column import offsets, source/name/content remapping, invalid source/name index guards, byte-stream no-comma parsing, relative VLQ coordinate guards, missing/null mappings strictness, negative generated-line offsets, duplicate generated-column lookup, `offsetColumns()`/`offsetLines()`/`addEmptyMap()`, empty generated-line spans, `addSourceMap()` line replacement, `extendWithSourceMap()` input remapping, project-root normalization, JSON/data URL defaults, source/name/mapping getters, buffer round trips, bundler SourceMap collection, CSS Modules, CSSOM, media-query, target-prefixing, property-value, or custom-at-rule work. The stale 2026-05-25 CustomMedia rework note in the main handoff directory was inspected and is unrelated to this source-map slice.
