# Source Map VLQ Offsets Parity - 2026-06-01T11:20Z

Lane: `lightningcss`
Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T112029Z`
Base accepted HEAD: `c572f41ab801d8aa51aba64622e775403921afd5`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map mutation to `parcel_sourcemap 2.1.1`.
- Local source-truth files inspected:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`

Upstream `SourceMap::add_sourcemap()` imports child source/name/sourceContent
tables and takes the child mapping lines before iterating. For each child
mapping line it computes `generated_line = line + line_offset`; if that target
line is negative, the entire child line is skipped before remapping or
validating source/name indexes. This differs from raw VLQ import validation,
where skipped negative-offset lines are still decoded and index-validated.

## Native Delta

- Added focused `SourceMapTest.php` coverage for corrupt child `sourceIndex`
  and corrupt child `nameIndex` mappings on generated line 0 merged with
  `lineOffset = -1`.
- The parent keeps its original `AAAAA` mapping, imports the skipped child's
  source, sourceContent, and name tables, and consumes the child map without
  raising an invalid index error.
- Extended `wordpress-source-map-rejected-child-merge.php --self-test` with a
  skipped corrupt generated child map for block-theme source-map delivery.

## Verification

- Baseline before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 835 assertions, 0 failures`.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` ->
  no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-rejected-child-merge.php` ->
  no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 847 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-rejected-child-merge.php --self-test` ->
  `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` ->
  `13 test files, 7538 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` ->
  passed.

Root harness: not run - isolated micro-slice.

## Coverage Delta

Focused SourceMap assertions moved from 835 to 847. Full LightningCSS lane
assertions moved from 7526 to 7538. Conservative mapped coverage remains
2369 / 3532 because this deepens the represented SourceMap add_sourcemap VLQ
offset cluster instead of adding a new denominator row.

## Non-Overlap

No current LightningCSS rework note existed for this lane. This slice does not
repeat accepted raw VLQ import negative-offset invalid-index validation, table
preservation when every raw VLQ mapping is skipped, same-line rejected child
merge atomicity, partial rejected merge preservation, empty child span
replacement, duplicate/unsorted generated-column sorting, project-root
normalization, data URL parser trivia, CSS Modules, CSSOM, bundle/import graph,
media-query, target-prefix, property-value, or custom-at-rule slices. It is
limited to `add_sourcemap` skipping negative generated child lines before
source/name remap validation.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP Source Map
v3/Base64 VLQ implementation and adds no Node, Rust runtime, WASM, browser
service, external source-map package, or live-service dependency.

## Follow-Up

Continue with non-overlapping SourceMap parity around nested input-map
remapping, original/generated lookup boundaries, and line replacement behavior
that is not already covered by the accepted SourceMap cluster.
