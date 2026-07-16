# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T112639Z`

Base accepted HEAD: `f65eba7003570c9efbe63dbdacffb94594eddf89`

## Implementation

- Added explicit GNU long-link TAR metadata rejection in `TarArchive`.
- GNU long-link typeflag `K` now fails closed with a link-metadata diagnostic
  before normal entry path resolution, so `././@LongLink` cannot be surfaced as
  an importable package entry or affect the following regular file.
- Updated the WordPress ZIP/package preflight smoke to report
  `tarGnuLongLinkPolicy=rejected`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. GNU long-link records are link-target metadata, parallel to PAX
`linkpath`; the bounded native TAR reader intentionally supports regular files
and directories only for review packets. Since hardlink and symlink
materialization remains out of scope, long-link metadata must be rejected before
WordPress import code can address package bytes.

This does not implement hardlink/symlink extraction, GNU long-link target
materialization, sparse-file reconstruction, recursive nested archive
discovery, encrypted archive preflight, filesystem extraction, compressed ZIP
dispatch, multi-volume archive handling, dictionary-backed LZ4 frames,
non-deflate ZIP compression methods, or any external archive runner.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 291 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the GNU long-link expectation:
    `1 test files, 292 assertions, 1 failures`.
  - Failure: expected `TAR GNU long-link metadata is not supported by the
    pandoc archive reader`; actual diagnostic was
    `Unsafe TAR entry name: ././@LongLink`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 292 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - Final PHP lint, JSON validation, focused verification, example smoke, and
    `git diff --check -- lanes/pandoc` are recorded in the worker handoff.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `864 -> 865`.
- `benchmarkDenominator.mapped`: `1322 -> 1323`.
- Focused archive coverage: `34 -> 35` PASS cases and `291 -> 292`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=35`,
  `mappedArchiveCompressionStreamCoreCases=35`, and
  `archiveCompressionStreamCoreAssertions=292`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive` parser and WordPress ZIP package preflight example. Full upstream
Pandoc runner parity remains blocked on hydrating and building the pinned
Haskell test executables; this TAR metadata safety behavior is covered by
focused native PHP tests and does not require Pandoc, Cabal, Haskell runners,
Word, LibreOffice, `tar`, `zip`, `unzip`, `lz4`, external archive tooling,
browser renderers, online sanitizers, or online services.

## Non-Overlap

This does not repeat accepted gzip header/member validation, gzip extra
subfield validation, split-gzip TAR member provenance, raw/zlib DEFLATE
provenance, POSIX TAR file and directory read/write paths, local/global PAX
policy, PAX `linkpath` rejection, GNU long-name path metadata, TAR end-marker
validation, TAR drive-letter rejection, base-256 numeric decoding, TAR
sparse-file rejection, independent/dependent LZ4 frame writing, dependent LZ4
frame decoding, ZIP/OPC package primitives, DOCX/ODT/EPUB readers,
doctemplates, YAML metadata, CSL/BibTeX, table geometry, math/TeX conversion,
PDF handoff planning, legacy DOC/CFB, charset, syntax highlighting, or
Markdown/HTML reader and writer behavior.

## Follow-Up

Keep recursive nested archive discovery, encrypted archive preflight,
filesystem extraction, compressed ZIP dispatch, multi-volume tar/zip handling,
sparse-file reconstruction, hardlink/symlink extraction policy,
dictionary-backed LZ4 frames, and non-deflate compression methods as separate
bounded slices unless concrete Pandoc package fixtures require them.
