# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T144434Z`

Base accepted HEAD: `51459e38f0cb013b3051260a5ce3e3395d649067`

## Implementation

- Added bounded gzip header review labels to `GzipStream::members()`.
- `modifiedAt` remains the raw RFC 1952 MTIME integer, while
  `modifiedAtKnown` marks MTIME `0` as absent timestamp provenance and
  `modifiedAtText` exposes nonzero timestamps as UTC text.
- `extraFlagsMeaning` labels XFL byte `2` as maximum compression, `4` as
  fastest compression, `0` as unspecified, and other values as unknown.
- `operatingSystemName` labels the standard gzip OS byte values, including
  Unix and unknown, for WordPress review packets.
- Carried the same labels through `ArchiveCompressionStream::inspectTarStream*()`
  so TAR preflight callers do not need to bypass the archive inspection API.
- Updated the WordPress ZIP/package preflight smoke to verify reproducible
  gzip-wrapped TAR packets and split-gzip member labels.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. RFC 1952 gzip members carry MTIME, XFL, and OS header bytes; common
reproducible package fixtures set MTIME to zero to mean no timestamp is
available. This slice keeps the existing raw bytes and adds bounded review
labels only. It does not reinterpret TAR entry paths, alter payload decoding,
or add filesystem extraction.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `tar`, external
`gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tooling, browser
renderer, JavaScript, online sanitizer, or online service was executed.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 311 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the focused gzip header-label expectation:
    `1 test files, 313 assertions, 1 failures`.
  - Failure: `modifiedAtKnown` was absent from gzip member metadata.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 329 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - Final PHP lint, JSON validation, focused verification, example smoke, and
    `git diff --check -- lanes/pandoc` are recorded in the worker handoff.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `954 -> 955`.
- `benchmarkDenominator.mapped`: `1409 -> 1410`.
- Focused archive coverage: `38 -> 39` PASS cases and `311 -> 329`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=11`,
  `mappedArchiveCompressionStreamCoreCases=11`, and
  `archiveCompressionStreamCoreAssertions=119`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`GzipStream`, `ArchiveCompressionStream`, `TarArchive`, and WordPress package
preflight example. Full upstream Pandoc runner parity remains blocked on
hydrating and building the pinned Haskell test executables; this gzip header
label behavior is covered by focused native PHP tests and does not require
Pandoc, Cabal, Haskell runners, Word, LibreOffice, `tar`, `zip`, `unzip`,
`lz4`, `ZipArchive`, external archive tooling, browser renderers, online
sanitizers, or online services.

## Non-Overlap

This does not repeat accepted gzip DEFLATE payload validation, gzip extra
subfield validation, raw split-gzip TAR member CRC/XFL/OS byte provenance,
gzip Latin-1 FNAME/FCOMMENT text projection, raw/zlib DEFLATE provenance,
POSIX TAR file and directory read/write paths, local/global PAX path and size
policy, PAX owner metadata, PAX `linkpath` rejection, GNU long-name path
metadata, GNU long-link rejection, TAR end-marker validation, TAR drive-letter
rejection, base-256 numeric decoding, TAR sparse-file rejection,
independent/dependent LZ4 frame writing, dependent LZ4 frame decoding,
ZIP/OPC package primitives, DOCX/ODT/EPUB readers, doctemplates, YAML
metadata, CSL/BibTeX, table geometry, math/TeX conversion, PDF handoff
planning, legacy DOC/CFB, charset, syntax highlighting, or Markdown/HTML
reader and writer behavior.

## Follow-Up

Keep dictionary-backed LZ4 frames, recursive nested archive discovery,
encrypted archive preflight, filesystem extraction, compressed ZIP dispatch,
multi-volume tar/zip handling, sparse-file reconstruction, hardlink/symlink
extraction policy, non-deflate compression methods, and full upstream-runner
dependency planning as separate bounded slices unless concrete Pandoc package
fixtures require them.
