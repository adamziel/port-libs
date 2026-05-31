# Source Map VLQ Vector Guard Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T185336Z`
Base: `0c0eec061390da3a2185ec8623476b5865dd4a49`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- `parcel_sourcemap-2.1.1/src/lib.rs::from_json()` deserializes `sources`, `sourcesContent`, and `names` into Rust `Vec` fields.
- `parcel_sourcemap-2.1.1/src/lib.rs::add_vlq_map()` accepts ordered `Vec<I>` inputs for source, source-content, and name vectors before reading the raw VLQ byte stream.
- Parity target: JSON objects or PHP associative arrays must not be silently coerced into ordered VLQ vectors.

## Native PHP Delta

- `SourceMap::fromJson()` now decodes source-map JSON as an object, preserving the distinction between JSON arrays and JSON objects before vector validation.
- `SourceMap::addVlqMap()` now rejects non-list `sources`, `sourcesContent`, and `names` arrays.
- Shared list guards now protect SourceMap JSON import and buffer list inputs.
- `wordpress-source-map-vlq-offsets.php` self-tests malformed inline/generated source-map vector shapes for WordPress theme/block CSS diagnostics.

## Verification

- Red-first after adding the focused test, before implementation:
  `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  failed on `source map rejects non-list vlq import vectors like upstream` with `Expected exception InvalidArgumentException was not thrown`.
- `php -l lanes/lightningcss/src/SourceMap.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 216 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 3147 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- JSON validation for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 210 to 216.
- Full LightningCSS PHP evidence moves from 3141 to 3147 pass / 0 fail.
- Conservative mapped coverage moves from 1696 to 1697 of 3532 for the additional `parcel_sourcemap` raw-VLQ ordered-vector guard.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice avoids accepted raw Source Map v3 generated-only/name imports, byte-stream no-comma VLQ parsing, line/column raw-map offsets, negative raw-VLQ line-offset import, duplicate generated-column offset/lookup behavior, `offsetColumns()`/`offsetLines()`/`addEmptyMap()`, empty generated-line spans, `addSourceMap()` line replacement, `extendWithSourceMap()` input remapping, project-root normalization, JSON/data URL defaults, source/name/mapping getters, buffer round trips, bundle SourceMap source collection, CSS Modules, CSSOM, media-query, target-prefixing, and custom-at-rule work. The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is unrelated to this current source-map vector guard.
