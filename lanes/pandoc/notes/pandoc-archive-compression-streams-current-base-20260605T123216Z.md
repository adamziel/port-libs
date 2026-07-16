# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T123216Z`

Base accepted HEAD: `9dd53993ceefd8947d3f764927ebaa4314a86b98`

## Implementation

- Tightened `TarArchive` path safety for bounded TAR review packets.
- Plain ustar header name and prefix fields now must be valid UTF-8 before
  the archive exposes entry names or file bytes.
- Generated TAR packets now reject invalid UTF-8 entry names through the same
  common path guard.
- The WordPress ZIP/package preflight smoke now reports
  `tarUstarPathUtf8Policy=rejected` alongside the existing PAX and GNU
  long-name path policies.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. PAX and GNU long-name path metadata already failed closed on invalid
UTF-8, but plain ustar path fields could still carry invalid bytes into
WordPress review packet entry names. The bounded native reader now applies one
UTF-8-safe path policy across generated entries, ustar headers, PAX `path`, and
GNU long-name metadata before exposing archive contents.

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
  - Result before edits: `1 test files, 298 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the ustar path expectation:
    `1 test files, 299 assertions, 1 failures`.
  - Failure: `Expected exception RuntimeException was not thrown`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 301 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - Final PHP lint, JSON validation, focused verification, example smoke, and
    `git diff --check -- lanes/pandoc` are recorded in the worker handoff.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `895 -> 896`.
- `benchmarkDenominator.mapped`: `1352 -> 1353`.
- Focused archive coverage: `36 -> 37` PASS cases and `298 -> 301`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=37`,
  `mappedArchiveCompressionStreamCoreCases=37`, and
  `archiveCompressionStreamCoreAssertions=301`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive` parser and WordPress ZIP/package preflight example. Full upstream
Pandoc runner parity remains blocked on hydrating and building the pinned
Haskell test executables; this TAR metadata safety behavior is covered by
focused native PHP tests and does not require Pandoc, Cabal, Haskell runners,
Word, LibreOffice, `tar`, `zip`, `unzip`, `lz4`, `ZipArchive`, external
archive tooling, browser renderers, online sanitizers, or online services.

## Non-Overlap

This does not repeat accepted gzip header/member validation, gzip extra
subfield validation, split-gzip TAR member provenance, raw/zlib DEFLATE
provenance, POSIX TAR file and directory read/write paths, local/global PAX
path and size policy, PAX owner metadata, PAX `linkpath` rejection, GNU
long-name path metadata, GNU long-link rejection, TAR end-marker validation,
TAR drive-letter rejection, base-256 numeric decoding, TAR sparse-file
rejection, independent/dependent LZ4 frame writing, dependent LZ4 frame
decoding, ZIP/OPC package primitives, DOCX/ODT/EPUB readers, doctemplates,
YAML metadata, CSL/BibTeX, table geometry, math/TeX conversion, PDF handoff
planning, legacy DOC/CFB, charset, syntax highlighting, or Markdown/HTML
reader and writer behavior.

## Follow-Up

Keep recursive nested archive discovery, encrypted archive preflight,
filesystem extraction, compressed ZIP dispatch, multi-volume tar/zip handling,
sparse-file reconstruction, hardlink/symlink extraction policy,
dictionary-backed LZ4 frames, non-deflate ZIP compression methods, and full
upstream-runner dependency planning as separate bounded slices unless concrete
Pandoc package fixtures require them.
