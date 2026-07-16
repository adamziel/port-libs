# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T224612Z`

Base accepted HEAD: `f0adbe861c08a776428186a38c22984f516d3d42`

## Implementation

- Added `GzipStream::inspect()` for bounded gzip stream provenance.
- Complete gzip members now report `memberCount`, total `uncompressedSize`,
  and `trailingPaddingBytes`.
- NUL-only bytes after at least one complete gzip member are accepted as
  trailer padding for package stream preflight, while any nonzero trailer bytes
  still fail before decoded package bytes are exposed.
- `ArchiveCompressionStream` carries gzip `uncompressedSize` and
  `trailingPaddingBytes` through TAR/ZIP package inspection.
- Updated the WordPress ZIP/package preflight smoke to cover a NUL-padded
  gzip-wrapped TAR packet and nonzero gzip trailer rejection.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. The underlying gzip decoder reaches a complete member before any trailer
bytes; this slice makes the package preflight policy explicit by accepting only
NUL padding as inert transport padding and rejecting nonzero trailer data before
WordPress import review can inspect decoded TAR/ZIP contents.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `tar`, external
`gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tooling, browser
renderer, JavaScript, online sanitizer, online service, or live provider test
was executed.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 443 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the focused gzip padding expectation:
    `1 test files, 443 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\GzipStream::inspect()`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 455 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php -l lanes/pandoc/src/GzipStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - JSON validation for `lanes/pandoc/lane-status.json` and
    `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
  - `git diff --check -- lanes/pandoc`
  - Result: clean.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1102 -> 1103`.
- `benchmarkDenominator.mapped`: `1554 -> 1555`.
- Focused archive coverage: `46 -> 47` PASS cases and `443 -> 455`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record the verified focused file
  totals: `archiveCompressionStreamCoreCases=47`,
  `mappedArchiveCompressionStreamCoreCases=47`, and
  `archiveCompressionStreamCoreAssertions=455`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`GzipStream`, `ArchiveCompressionStream`, `TarArchive`, focused PHP test
harness, and WordPress ZIP/package preflight example. Full upstream Pandoc
runner parity remains blocked on hydrating and building the pinned Haskell test
executables; this gzip package-stream integrity behavior is covered by focused
native PHP tests and does not require Pandoc, Cabal, Haskell runners, Word,
LibreOffice, `tar`, `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external
archive tooling, browser renderers, online sanitizers, or online services.

## Non-Overlap

This does not repeat accepted POSIX TAR file and directory read/write paths,
PAX UTF-8 path validation, PAX size/owner/review metadata policy, duplicate PAX
keyword rejection, GNU long-name integrity, GNU long-link rejection, TAR
end-marker validation, TAR drive-letter rejection, base-256 numeric decoding,
TAR sparse-file rejection, raw/zlib DEFLATE validation, gzip member CRC/header
validation, gzip Latin-1/provenance labels, split-gzip TAR member provenance,
independent/dependent LZ4 block decoding or writing, compressed ZIP dispatch,
generic TAR/ZIP package-kind detection, ZIP/OPC package primitives, DOCX/ODT/
EPUB readers, doctemplates, YAML metadata, CSL/BibTeX, table geometry,
math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset, syntax
highlighting, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep recursive nested archive discovery, encrypted archive preflight,
filesystem extraction, multi-volume tar/zip handling, sparse-file
reconstruction, hardlink/symlink extraction policy, dictionary-backed LZ4
frames, non-deflate ZIP compression methods, and full upstream-runner
dependency planning as separate bounded slices unless concrete Pandoc package
fixtures require them.
