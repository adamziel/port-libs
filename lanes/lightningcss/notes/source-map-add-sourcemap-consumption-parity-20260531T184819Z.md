# Source Map add_sourcemap Consumption Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T184819Z`
Base: `0c0eec061390da3a2185ec8623476b5865dd4a49`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream file: `parcel_sourcemap-2.1.1/src/lib.rs::add_sourcemap()`.
- Source-truth behavior: upstream uses `std::mem::take()` for the child map's sources, names, source contents, and mapping lines before remapping into the parent. After a merge, the child map is empty, and replaying it at another line offset is a no-op.

## Native PHP Delta

- `SourceMap::addSourceMap()` now drains the nested child map after remapping its referenced sources, names, source contents, and mapping lines into the parent.
- Reusing a consumed child map no longer replays the same mappings at a later generated-line offset.
- `wordpress-source-map-vlq-offsets.php` now self-tests that a merged block stylesheet source map is consumed and that replaying it does not mutate the final theme map.

## Verification

- Red-first run before implementation: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` failed in `source map consumes nested source maps after upstream add_sourcemap merge` because the child map still exposed `child.css` after merge.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 223 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 3154 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `php -l lanes/lightningcss/src/SourceMap.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php -r 'json_decode(...)'` for `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json`: OK.
- `git diff --check -- lanes/lightningcss`: passed.
- Root harness: not run - isolated micro-slice.

## Status

- Focused SourceMap assertions move from 210 to 223.
- Full LightningCSS PHP evidence moves from 3141 to 3154 assertions with 0 failures.
- Conservative mapped coverage moves from 1696 to 1697 of 3532 for the additional `parcel_sourcemap::SourceMap::add_sourcemap` child-consumption behavior.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, external source-map library, or live service.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 generated-only/name imports, line/column raw-map import offsets, negative raw-VLQ line-offset import, relative VLQ guard failures, byte-stream no-comma parsing, duplicate generated-column offset or lookup behavior, `offsetColumns()`/`offsetLines()`/`addEmptyMap()` basics, empty generated-line spans, `addSourceMap()` line replacement, `extendWithSourceMap()` input remapping, project-root normalization, JSON/data URL defaults, source/name/mapping getters, buffer round trips, bundle SourceMap source collection, CSS Modules, CSSOM, media-query, target-prefixing, or custom-at-rule work. It is limited to upstream's child-map consumption semantics after nested source-map merge.
