# Pandoc Archive Compression Streams Current Base 2026-06-08T21:12:32Z

Lane: `pandoc`
Slice: `pandoc-archive-compression-streams-current-base-20260608T211232Z`
Base accepted HEAD: `cf5d96b39689c05bf093c6f49ee34f98d39a195f`

## Behavior

Added bounded ZIP local-entry layout provenance for archive review packets:

- `ArchiveCompressionStream::inspectZipStream()` and ZIP package auto/dictionary inspection paths now include `entryLayouts`.
- Each layout reports the central-directory index, local-header order, compression method, flags, CRC, compressed/uncompressed sizes, local header offsets, compressed data offsets, optional data descriptor offsets, record size, and next offset.
- ZIP local-entry records now map back to decoded source segments, so split gzip/LZ4/plain package streams can show which source member or frame supplied each byte range.
- The WordPress archive preflight example now self-tests a split gzip-wrapped ZIP package and verifies the `word/document.xml` local record spans both source members.

## Source Truth And Scope

This stays inside the `pandoc-archive-compression-streams-*` support-library row. Pandoc package readers need reviewable package byte provenance for DOCX/ODT/EPUB handoff fixtures before conversion, especially when a ZIP package is delivered through an outer compression stream. This ports the bounded PHP format contract only: local-header layout and source provenance metadata, not filesystem extraction or external archive validation.

No Pandoc, Cabal solver/build/test command, Haskell runner, `tar`, external `gzip`, `zip`, `unzip`, `lz4`, ZipArchive, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Verification

- Rework notes: no matching `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` note existed.
- Baseline focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2546 assertions, 0 failures`.
- Red-first focused command after adding the ZIP local-entry layout expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2550 assertions, 1 failures`; missing `entryLayouts` triggered the new failure.
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2575 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test grew from `2546` to `2575` assertions.
- `lane-status.json` `phpPass`: `1854 -> 1855`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2282 -> 2283`.
- Archive compression manifest counters: `11 -> 12` mapped support cases and `120 -> 149` focused support assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ArchiveCompressionStream`, `ZipPackage::localHeaderPreflight()`, `GzipStream`, focused archive tests, and the existing WordPress archive stream preflight example.

## Non-Overlap

This does not repeat accepted gzip member framing/provenance, gzip CRC/header validation, decoded package chunk preflight, TAR entry layout/source segments, TAR PAX path/size/owner/atime/ctime/creation/hdrcharset metadata, duplicate PAX keyword policy, TAR sparse/multi-volume/incremental/link/special-file policies, TAR path safety, raw/zlib deflate provenance, zlib/LZ4 dictionary policy/decode, split LZ4 frame range provenance, archive-bomb policy, unsupported bzip2/xz/zstandard diagnostics, ZIP encryption policy, ZIP64 EOCD policy, ZIP split-archive policy, ZIP data descriptor integrity, unsupported ZIP compression method policy, ZIP central-directory signature, or ZIP package primitives.

## Follow-Up

Keep reader-specific archive-bomb threshold wiring, encrypted archive diagnostics where missing, filesystem extraction policy, non-deflate ZIP method metadata, sparse reconstruction review metadata, external archive-tool validation, and full upstream Pandoc runner parity as separate bounded slices unless concrete package fixtures require them.
