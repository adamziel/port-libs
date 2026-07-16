# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T102402Z`

Base accepted HEAD: `339f124190d9d276d42f196db494286344048c17`

## Implementation

- Added bounded raw DEFLATE stream inspection in `DeflateStream::inspectRaw()`.
- Extended `ArchiveCompressionStream::inspectTarStream*()` metadata for
  zlib-wrapped and raw-DEFLATE-wrapped TAR review packets:
  - zlib streams now expose verified compressed DEFLATE payload bytes and
    decoded TAR byte size alongside the existing wrapper metadata.
  - raw DEFLATE streams now expose compressed payload bytes and decoded TAR
    byte size through the same archive inspection path.
- Updated the WordPress ZIP/package preflight smoke to assert and print the
  deflate stream provenance before exposing TAR review-packet bytes.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Pandoc package fixtures can be wrapped by bounded zlib/raw DEFLATE streams
before the native PHP TAR reader sees package entries. The support-library
contract now keeps wrapper and payload size provenance visible through the
high-level archive inspection API used by WordPress review packets.

This does not implement nested archive discovery, encrypted archive preflight,
filesystem extraction, compressed ZIP dispatch, multi-volume archive handling,
sparse-file reconstruction, hardlink/symlink extraction, dictionary-backed LZ4
frames, non-deflate ZIP compression methods, or any external archive runner.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 273 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the DEFLATE provenance expectation:
    `1 test files, 278 assertions, 1 failures`.
  - Failure: `compressedPayloadSize` was absent from the zlib archive stream
    inspection metadata.
- After implementation:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/src/DeflateStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 291 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - JSON validation for `lanes/pandoc/lane-status.json` and
    `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `829 -> 830`.
- `benchmarkDenominator.mapped`: `1289 -> 1290`.
- Focused archive coverage: `33 -> 34` PASS cases and `273 -> 291`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=34`,
  `mappedArchiveCompressionStreamCoreCases=34`, and
  `archiveCompressionStreamCoreAssertions=291`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ArchiveCompressionStream`, `DeflateStream`, `TarArchive`, and WordPress ZIP
package preflight example. Full upstream Pandoc runner parity remains blocked
on hydrating and building the pinned Haskell test executables; this archive
stream behavior is covered by focused native PHP tests and does not require
Pandoc, Cabal, Haskell runners, Word, LibreOffice, `tar`, `zip`, `unzip`,
`lz4`, external archive tooling, browser renderers, online sanitizers, or
online services.

## Non-Overlap

This does not repeat accepted gzip header/member validation, gzip extra
subfield validation, split-gzip TAR member provenance, POSIX TAR file and
directory read/write paths, local/global PAX policy, GNU long-name metadata,
TAR end-marker validation, TAR drive-letter rejection, base-256 numeric
decoding, TAR sparse-file rejection, independent/dependent LZ4 frame writing,
dependent LZ4 frame decoding, ZIP/OPC package primitives, DOCX/ODT/EPUB
readers, doctemplates, YAML metadata, CSL/BibTeX, table geometry, math/TeX
conversion, PDF handoff planning, legacy DOC/CFB, charset, syntax
highlighting, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep recursive nested archive discovery, encrypted archive preflight,
filesystem extraction, compressed ZIP dispatch, multi-volume tar/zip handling,
sparse-file reconstruction, hardlink/symlink extraction policy,
dictionary-backed LZ4 frames, and non-deflate compression methods as separate
bounded slices unless concrete Pandoc package fixtures require them.
