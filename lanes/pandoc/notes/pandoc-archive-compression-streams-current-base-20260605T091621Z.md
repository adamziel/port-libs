# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T091621Z`

Base accepted HEAD: `4aa0cc3d1c79d46c5770f63de91624ccc6645a18`

## Implementation

- Extended `ArchiveCompressionStream::inspectTarStream*()` gzip member
  inspection to preserve member XFL (`extraFlags`), OS byte, raw extra-field
  payload, parsed extra subfields, CRC32, and header CRC metadata.
- Added a focused split-gzip TAR review-packet test that verifies this
  provenance survives after auto-detection and before WordPress import code
  consumes the unpacked TAR entries.
- Updated the WordPress ZIP/package preflight smoke to assert and print the
  same split-gzip member provenance for review packets.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. RFC 1952 gzip members carry XFL, OS, optional extra subfields, optional
FHCRC, and trailer CRC32/ISIZE metadata. The lower-level native PHP
`GzipStream` parser already validates those fields; this slice keeps that
validated provenance visible through the higher-level archive stream
inspection path used by TAR review packets.

This does not implement dictionary-backed LZ4 frames, nested archive discovery,
encrypted archive preflight, filesystem extraction, compressed ZIP dispatch,
multi-volume archive handling, sparse-file reconstruction, hardlink/symlink
extraction, or non-deflate ZIP compression methods.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 253 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the first gzip inspection expectation:
    `1 test files, 241 assertions, 1 failures`.
  - Failure: `Undefined array key "extraFlags"` from the split-gzip
    inspection member projection.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 272 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php && php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php && php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors in all three changed PHP files.
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `800 -> 801`.
- `benchmarkDenominator.mapped`: `1260 -> 1261`.
- Focused archive coverage: `31 -> 32` PASS cases and `253 -> 272`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=32`,
  `mappedArchiveCompressionStreamCoreCases=32`, and
  `archiveCompressionStreamCoreAssertions=272`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`GzipStream`, `ArchiveCompressionStream`, `TarArchive`, and WordPress package
preflight smoke. Full upstream Pandoc runner parity remains blocked on
hydrating and building the pinned Haskell test executables; this gzip
inspection behavior does not need Pandoc, Cabal, Haskell runners, tar,
zip/unzip, lz4, external archive tools, office tools, renderers, or online
services.

## Non-Overlap

This does not repeat accepted gzip member parsing, gzip extra subfield
validation, explicit or auto-detected archive dispatch, split gzip/LZ4 stream
entry reconstruction, POSIX TAR file and directory read/write paths,
local/global PAX policy, GNU long-name metadata, TAR end-marker validation,
TAR drive-letter rejection, base-256 numeric decoding, TAR sparse-file
rejection, raw/zlib DEFLATE validation, independent/dependent LZ4 frame
writing, dependent LZ4 frame decoding, ZIP/OPC package primitives, DOCX/ODT/
EPUB readers, doctemplates, YAML metadata, CSL/BibTeX, table geometry,
math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset, syntax
highlighting, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep dictionary-backed LZ4 frames, nested archive discovery, encrypted archive
preflight, filesystem extraction, compressed ZIP dispatch, multi-volume tar/zip
handling, sparse-file reconstruction, hardlink/symlink extraction policy, and
non-deflate compression methods as separate bounded slices unless concrete
Pandoc package fixtures require them.
