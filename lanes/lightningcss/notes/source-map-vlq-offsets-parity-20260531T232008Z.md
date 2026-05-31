# Source Map JSON Version Ignore Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T232008Z`
Base: `afee0853cdadd52fa12dbc1e24d633ac7329910c`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS `Cargo.lock` uses `parcel_sourcemap` `2.1.1` from crates.io.
- Source truth: `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::from_json()`.
- The upstream `JSONSourceMap` deserializer defines `mappings`, `sources`, `sources_content`, and `names`; it does not deserialize `version`. Serde therefore ignores missing, string, and out-of-range `version` values before `add_vlq_map()` imports the raw VLQ mappings.

## Native PHP Delta

- Removed the PHP-only `version` precheck from `SourceMap::fromJson()` and `SourceMap::fromArray()`.
- Replaced the earlier mistaken version-guard assertion with parity coverage proving missing, string, numeric out-of-range, and normal `version: 3` inputs all import the same `;C` generated-only VLQ mapping.
- Updated `wordpress-source-map-vlq-offsets.php` so the WordPress source-map self-test exercises the ignored-version path.

## Verification

- Red-first PHP probe before implementation: missing, string, and out-of-range version JSON inputs all threw `InvalidArgumentException: Source map version must be an unsigned 8-bit integer.`
- `php -l lanes/lightningcss/src/SourceMap.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 349 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 4829 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SourceMap assertions move from 341 to 349.
- Full LightningCSS PHP evidence moves from 4821 to 4829 assertions / 0 failures.
- Conservative mapped coverage remains 2198 / 3532 because this corrects the already represented JSON version behavior instead of claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the lane-local native Source Map v3/Base64 VLQ parser and adds no Node, Rust, browser service, external source-map package, or live-service dependency.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is unrelated to this source-map slice. This patch does not repeat accepted raw Source Map v3 generated-only/name imports, line/column raw-map offsets, negative line/column offsets, all-skipped raw-VLQ table preservation, duplicate generated-column offset/search behavior, empty-line column no-ops/overflow guards, shifted-column overflow guards, past-EOF line spans, `addSourceMap()` replacement/consumption, input-map extension, project-root normalization, null `sourcesContent` vector strictness, JSON/data URL defaults, buffer round trips, bundler SourceMap collection, CSS Modules, CSSOM, media-query, target-prefixing, property-value, or custom-at-rule work. It is limited to correcting raw JSON/fromArray version-field handling before VLQ import.
