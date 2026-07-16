# Source Map VLQ Offsets Parity - 2026-06-01T10:20Z

Lane: `lightningcss`
Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T102038Z`
Base accepted HEAD: `7bd413e4c22aac9f2c5a76765dae0d142cb048cb`

## Source Truth

Pinned upstream is `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.

This slice follows the upstream source-map input path through `parcel_sourcemap 2.1.1` and `data-url 0.1.1`:

- `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
- `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`
- `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/data-url-0.1.1/src/lib.rs`
- `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/data-url-0.1.1/src/forgiving_base64.rs`

Upstream `SourceMap::from_data_url()` delegates to `DataUrl::process()` and `decode_to_vec()`. The data URL crate strips raw fragments, ignores raw tab/newline/carriage-return code points in the body, percent-decodes the payload bytes, then applies forgiving base64 decoding that ignores ASCII whitespace including spaces and form feeds.

## Native Delta

- `SourceMap::fromDataUrl()` now decodes data URL bodies before JSON import instead of passing raw escaped payloads into `base64_decode()`.
- Raw fragments after `#` are ignored before payload decode, matching the data-url parser boundary.
- Raw tab/newline/carriage-return trivia is ignored before percent decoding.
- Base64 payloads are normalized for forgiving ASCII whitespace after percent decoding, including percent-encoded form feed (`%0C`).
- The focused PHP test imports a percent-encoded base64 Source Map v3 data URL, verifies the decoded `;CAAA` VLQ segment and source tables, then merges it into a parent map at a generated-line offset.
- Added a WordPress-oriented smoke for encoded theme source-map data URLs emitted by editor or build tooling.

## Verification

- `php -l lanes/lightningcss/src/SourceMap.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/SourceMapTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-source-map-data-url-percent-base64.php` -> no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> 1 test files, 809 assertions, 0 failures
- `php lanes/lightningcss/examples/wordpress-source-map-data-url-percent-base64.php --self-test` -> OK
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 7375 assertions, 0 failures

SourceMap focused assertions moved from 799 to 809. Full lane assertions moved from 7365 to 7375. Conservative mapped coverage remains 2365 / 3532 because this deepens the represented SourceMap data URL and VLQ import cluster.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This is distinct from the accepted source-map JSON/data URL defaults, percent-encoded non-base64 JSON payloads, raw VLQ byte-stream import, duplicate/unsorted generated-column sorting, add_sourcemap replacement/consumption, empty generated-line spans, project-root normalization, and positive/negative offset slices. It specifically covers percent-decoded base64 data URL body handling before VLQ import and nested offset merge.

## Dependency Closure

No new support component is needed. The behavior is implemented in native PHP `SourceMap` parsing and uses existing JSON, base64, and VLQ helpers. No Node, Rust, WASM, filesystem resolver, or external runtime support is required.

## Follow-Up

Continue with non-overlapping SourceMap parity around nested input-map remapping, source-root/path normalization, and generated/original position lookup behavior that is not already covered by the accepted SourceMap cluster.
