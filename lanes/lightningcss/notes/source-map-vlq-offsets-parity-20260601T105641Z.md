# Source Map Data URL Parser Trivia Parity - 2026-06-01T10:56Z

Lane: `lightningcss`
Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T105641Z`
Base accepted HEAD: `33333a56ebb8828822e56091b018c21a9ae7058c`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS source-map handling delegates to `parcel_sourcemap 2.1.1`.
- Local source-truth files inspected:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/data-url-0.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/data-url-0.1.1/src/forgiving_base64.rs`

`SourceMap::from_data_url()` calls `DataUrl::process()` before JSON and VLQ
import. The upstream data-url parser trims leading/trailing C0/space, ignores
raw tab/newline/carriage-return while recognizing the `data:` scheme and header,
recognizes a spaced `; base64` suffix, strips fragments from the body, percent
decodes the body, and uses forgiving base64 decoding that ignores ASCII
whitespace including form feed.

## Native Delta

- `SourceMap::fromDataUrl()` now normalizes the `data:` scheme and metadata
  with the upstream parser trivia rules before JSON/VLQ import.
- The parser accepts case-insensitive `data:` URLs with leading C0/space and
  tab/newline/carriage-return trivia in the scheme.
- The metadata parser recognizes `application/json ; base64` and similar spaced
  base64 suffixes after URL-style tab/newline/carriage-return filtering.
- Focused coverage imports a Source Map v3 payload with mapping `;CAAA`, then
  merges it into a parent map at line offset 2 to verify the same `A;;;CAAA`
  nested VLQ output.
- The WordPress source-map data URL smoke now covers the same parser-trivia
  path for generated block CSS maps.

## Verification

- Red-first probe before implementation:
  `SourceMap::fromDataUrl(" \tD\na\rtA: application/json; \tbase64,...")`
  rejected with `Source map data URL must use the data: scheme.`
- `php -l lanes/lightningcss/src/SourceMap.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/SourceMapTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-source-map-data-url-percent-base64.php` -> no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> 1 test files, 819 assertions, 0 failures
- `php lanes/lightningcss/examples/wordpress-source-map-data-url-percent-base64.php --self-test` -> OK
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 7478 assertions, 0 failures

Root harness: not run - isolated micro-slice.

## Coverage Delta

Focused SourceMap assertions moved from 809 to 819. Full LightningCSS lane
assertions moved from 7468 to 7478. Conservative mapped coverage remains
2369 / 3532 because this deepens the represented SourceMap data URL and VLQ
import cluster.

## Non-Overlap

This does not repeat accepted raw VLQ import, generated-only segments,
positive/negative generated offsets, duplicate or unsorted generated-column
sorting, add_sourcemap replacement/consumption, empty generated-line spans,
project-root normalization, JSON/data URL defaults, percent-encoded base64 body
handling, CSS Modules, CSSOM, bundle/import graph, media-query, target-prefix,
property-value, or custom-at-rule slices. It is limited to the upstream data URL
parser trivia boundary before Source Map v3/VLQ import and offset merge.

## Dependency Closure

No new support component is needed. The slice reuses native PHP Source Map
v3/Base64 VLQ, JSON, and data URL helpers with no Node, Rust, WASM, browser
service, external source-map package, or live-service dependency.
