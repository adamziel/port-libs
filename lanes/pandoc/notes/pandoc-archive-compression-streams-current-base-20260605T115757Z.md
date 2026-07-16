# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T115757Z`

Base accepted HEAD: `72b10b26c9b892a4b2bc30e8501676c9ce4c2557`

## Implementation

- Tightened `TarArchive` owner metadata handling for bounded review packets.
- Ustar `uname`/`gname` fields and PAX `uname`/`gname` records now must be
  valid UTF-8 before entries are exposed to package readers.
- Generated TAR review packets also reject invalid UTF-8 `userName` and
  `groupName` values before writing fixture bytes.
- The WordPress ZIP/package preflight smoke now reports
  `tarOwnerUtf8Policy=rejected`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. TAR owner/group names are metadata that can be surfaced in WordPress
review packets alongside PAX path, size, mtime, uid, and gid metadata. The
bounded native reader now keeps those text fields JSON/review-safe and fails
closed on invalid bytes before exposing package contents.

This does not implement recursive nested archive discovery, encrypted archive
preflight, filesystem extraction, compressed ZIP dispatch, multi-volume
tar/zip handling, sparse-file reconstruction, hardlink/symlink extraction,
dictionary-backed LZ4 frames, non-deflate ZIP compression methods, or any
external archive runner.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 292 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the owner-metadata expectation:
    `1 test files, 293 assertions, 1 failures`.
  - Failure: `Expected exception RuntimeException was not thrown`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 298 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - Final PHP lint, JSON validation, focused verification, example smoke, and
    `git diff --check -- lanes/pandoc` are recorded in the worker handoff.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `881 -> 882`.
- `benchmarkDenominator.mapped`: `1339 -> 1340`.
- Focused archive coverage: `35 -> 36` PASS cases and `292 -> 298`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=36`,
  `mappedArchiveCompressionStreamCoreCases=36`, and
  `archiveCompressionStreamCoreAssertions=298`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive` parser and WordPress ZIP/package preflight example. Full upstream
Pandoc runner parity remains blocked on hydrating and building the pinned
Haskell test executables; this TAR metadata safety behavior is covered by
focused native PHP tests and does not require Pandoc, Cabal, Haskell runners,
Word, LibreOffice, `tar`, `zip`, `unzip`, `lz4`, external archive tooling,
browser renderers, online sanitizers, or online services.

## Non-Overlap

This does not repeat accepted gzip header/member validation, gzip extra
subfield validation, split-gzip TAR member provenance, raw/zlib DEFLATE
provenance, POSIX TAR file and directory read/write paths, local/global PAX
path and size policy, PAX `linkpath` rejection, GNU long-name path metadata,
GNU long-link rejection, TAR end-marker validation, TAR drive-letter rejection,
base-256 numeric decoding, TAR sparse-file rejection, independent/dependent LZ4
frame writing, dependent LZ4 frame decoding, ZIP/OPC package primitives,
DOCX/ODT/EPUB readers, doctemplates, YAML metadata, CSL/BibTeX, table geometry,
math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset, syntax
highlighting, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep recursive nested archive discovery, encrypted archive preflight,
filesystem extraction, compressed ZIP dispatch, multi-volume tar/zip handling,
sparse-file reconstruction, hardlink/symlink extraction policy,
dictionary-backed LZ4 frames, and non-deflate compression methods as separate
bounded slices unless concrete Pandoc package fixtures require them.
