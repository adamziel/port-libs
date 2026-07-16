# Source Map Null sourcesContent Vector Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T222709Z`
Base: `6cff27008844e2e4a3255962746465ff174dc963`

## Source Truth

- Pinned LightningCSS source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS `Cargo.lock` uses `parcel_sourcemap` `2.1.1`.
- Targeted upstream file: `parcel_sourcemap-2.1.1/src/lib.rs::from_json()`.
- Upstream deserializes `sources_content` as a Rust `Vec<Option<Cow<str>>>` with `#[serde(default)]`. That means a missing `sourcesContent` field defaults to an empty vector, and existing accepted PHP coverage keeps per-entry `null` as empty source content, but a present JSON `sourcesContent: null` is not a vector and is rejected before `add_vlq_map()` imports VLQ mappings.

## Native PHP Delta

- `SourceMap::fromJson()` and `SourceMap::fromArray()` now distinguish a missing `sourcesContent` field from a present `null` vector.
- `SourceMapTest.php` adds focused JSON and array guards for present null `sourcesContent` vectors.
- `wordpress-source-map-vlq-offsets.php` self-tests the malformed generated block/theme source-map guard.

## Verification

- Red-first probe before implementation: `SourceMap::fromJson('{"version":3,"mappings":"A","sources":[],"sourcesContent":null,"names":[]}')` and the equivalent `fromArray()` call both returned `OK`.
- `php -l lanes/lightningcss/src/SourceMap.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 335 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 4651 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 333 to 335.
- Full LightningCSS PHP evidence moves from 4649 to 4651 assertions / 0 failures.
- Conservative mapped coverage moves from 2167 to 2168 of 3532 for the additional `parcel_sourcemap::SourceMap::from_json` vector strictness behavior before VLQ import.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ parser with no Node, Rust, browser service, external source-map library, or live service.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 generated-only/name imports, positive or negative raw-VLQ line/column offsets, all-skipped raw-VLQ table preservation, duplicate generated-column offset/search behavior, relative VLQ overflow guards, missing `sources`/`names` vectors, null `sourcesContent` entries, JSON version guards, `offsetColumns()`/`offsetLines()`/`addEmptyMap()` spans, `addSourceMap()` line replacement/consumption, `extendWithSourceMap()` input remapping, project-root normalization, JSON/data URL defaults, source/name/mapping getters, buffer round trips, bundle SourceMap collection, CSS Modules, CSSOM, media-query, target-prefixing, or custom-at-rule work. The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is unrelated to this source-map slice.
