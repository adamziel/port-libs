# Source Map JSON Data URL Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T161353Z`
Base: `8c7b034bb5fb3d061acb6b56e46103da8721d7a6`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream behavior:
  - `SourceMap::from_json` fills missing and `null` `sourcesContent` entries with empty strings.
  - `SourceMap::to_data_url` serializes JSON as `data:application/json;charset=utf-8;base64,...`.
  - `SourceMap::from_data_url` imports application/json data URLs, including base64 and percent-encoded payloads.

## Native PHP Delta

- `SourceMap::fromArray()` / `fromJson()` now normalize missing and `null` source contents to empty strings for upstream-compatible JSON import.
- `SourceMap::toDataUrl()` and `SourceMap::fromDataUrl()` add native Source Map v3 data URL export/import without shelling out.
- `SourceMap::setSourceContent()` now fills sparse source-content gaps with empty strings, matching upstream vector behavior.
- `wordpress-source-map-vlq-offsets.php` now self-tests an inline sourceMappingURL data URL for generated theme CSS.

## Verification

- Red-first evidence before implementation: `SourceMap::fromJson()` returned `sourcesContent: [null]` for missing contents, and `SourceMap::fromDataUrl()` did not exist.
- `php -l lanes/lightningcss/src/SourceMap.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 113 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 2102 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- PHP pass evidence moves from 2092 to 2102 assertions.
- Conservative mapped coverage moves from 1349 to 1352 of 3532 for three `parcel_sourcemap` JSON/data-url behaviors.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this extends the lane-local native Source Map v3/VLQ support and adds no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw VLQ import/remapping, line/column offset import, `addSourceMap()` line replacement, empty generated-line spans, `extendWithSourceMap()` input remapping, project-root source normalization, bundler SourceProvider reads, CSS Modules escaped identifiers, custom env/var visitors, text-decoration CSSOM, or unknown media-range layers. The stale 2026-05-25 CustomMedia rework note is unrelated to this current source-map slice and predates the accepted CustomMedia scanner integrations.
