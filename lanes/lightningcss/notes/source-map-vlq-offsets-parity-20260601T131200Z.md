# Source Map VLQ Offsets Parity 2026-06-01T13:12Z

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T131200Z`

Source truth:
- `parcel_sourcemap-2.1.1/src/lib.rs::SourceMap::offset_lines` inserts empty mapping lines with `splice(line..line, empty lines)` when a positive offset starts inside the existing generated-line table.
- `parcel_sourcemap-2.1.1/src/mapping_line.rs::ensure_sorted` sorts a mapping line only at explicit sort entrypoints such as write and lookup, so a shifted raw VLQ line can retain imported read order until that entrypoint.

Native delta:
- Added `SourceMapTest.php` coverage for inserting two empty generated-line spans at line 1 before an existing unsorted raw VLQ line.
- The test preserves the preceding generated line, verifies the shifted line remains in imported read order before `writeVlq()`, verifies the sorted write string `AAAAA;;;ECCAE,QADAD;AAGAE`, checks closest lookup on the shifted line, and round-trips through the binary buffer path.
- Extended `wordpress-source-map-vlq-offsets.php --self-test` with the same middle-insertion path using WordPress theme source names.

Verification:
- `php -l lanes/lightningcss/tests/SourceMapTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` passed: 1 file / 938 assertions / 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test` passed: OK.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: 13 files / 8001 assertions / 0 failures.

Coverage/status:
- Focused SourceMap coverage increased by 15 assertions over the inherited 923 assertion count.
- Full LightningCSS lane evidence increased from 7986 to 8001 assertions.
- Conservative mapped coverage remains 2392 / 3532 because this deepens the already represented source-map VLQ offset cluster.

Non-overlap:
- This does not repeat the accepted positive line offset at line 0, negative line splice, duplicate generated-column offset, empty child span, generated-only child, or rejected child merge source-map slices. The new edge is positive insertion in the middle of the generated-line table before an unsorted raw VLQ line.

Blocker/follow-up:
- Full upstream Rust/Node/WASM LightningCSS runners were not executed in this isolated PHP lane slice.
- Next source-map work should stay on non-overlapping bundle/import remap, nested add_sourcemap replacement, VLQ offset, or buffer serialization parity edges.

Dependency closure:
- No new support component is needed. The existing native PHP `SourceMap` VLQ decoder, line-offset table handling, writer, lookup, and buffer serializer are reused.
