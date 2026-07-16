# Source Map Before-First Lookup Offset Parity - 2026-06-01T11:36Z

Lane: `lightningcss`
Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T113613Z`
Base accepted HEAD: `643db6cd7b3a41ab8e3a67fdda031493c589be65`

## Source Truth

Upstream LightningCSS is pinned at `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`. This slice used the bundled
`parcel_sourcemap` crate as source truth:

- `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
- `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
- `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`

`MappingLine::find_closest_mapping()` sorts mapping lines before lookup and
uses `binary_search_by_key()` on generated columns. When the requested column
falls before the first segment, upstream returns a synthetic generated-column
`0` mapping backed by the first sorted segment's original source/name fields.
Existing PHP coverage already pinned generated-only and after-last fallback
edges; this slice adds the source-backed before-first edge after column offsets
and nested input-map extension.

## Native Delta

- Added `SourceMapTest.php` coverage for raw VLQ `UAECA,UACCC` where
  `findClosestMapping(0, 5)` falls before the first generated segment and
  returns the first source-backed original mapping at generated column `0`.
- Guarded that `offsetColumns(0, 10, 4)` rewrites the VLQ to `cAECA,UACCC`,
  keeps the before-first fallback at generated column `0`, and still resolves
  the exact shifted segment at generated column `14`.
- Guarded `extendWithSourceMap()` through a compiled parent map at original
  column `13`, yielding nested VLQ `ACECC` and preserving source/name remaps.
- Extended the WordPress source-map offsets example self-test with the same
  source-backed before-first fallback and nested extension path.

The native PHP implementation already followed upstream behavior through the
existing `SourceMap` closest-lookup, offset, and extension code. This patch pins
the weakly mapped parity edge with focused tests and a WordPress smoke instead
of changing production source.

## Verification

Baseline before this slice:

- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 835 assertions, 0 failures`

After this slice:

- `php -l lanes/lightningcss/tests/SourceMapTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/SourceMapTest.php`
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 846 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7593 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - passed with no output
- JSON metadata validation for `lane-status.json` and
  `UPSTREAM_TEST_MANIFEST.json`
  - `JSON OK`

## Coverage Delta

Focused `SourceMapTest.php` coverage increased from `835` to `846`
assertions. Full LightningCSS lane evidence increased from `7582` to `7593`
assertions. Conservative mapped coverage remains `2374 / 3532` because this
deepens an already represented source-map VLQ offset cluster.

## Non-Overlap

This slice does not repeat raw VLQ import defaults, generated-only segment
offsets, duplicate-column sorting, after-last fallback, add-sourcemap line
replacement, data URL import, project-root normalization, CSS Modules, bundle
import graph, CSSOM, media-query, property-value, or target-prefix work.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
Source Map v3/Base64 VLQ implementation and does not require Node, Rust, WASM,
browser APIs, network access, live service credentials, or an external package.
