# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T221655Z`

Base accepted HEAD: `86cdb88c35cf0d01324357c4d5dcbb59b59e6683`

## Implementation

- Tightened `TarArchive` GNU long-name metadata parsing.
- GNU `L` long-name records now must end with a NUL terminator before the
  resolved package path can be exposed as an entry name.
- Added focused archive-stream coverage for an unterminated GNU long-name
  metadata record.
- Updated the WordPress ZIP/package preflight smoke to report
  `tarGnuLongNameTerminatorPolicy=rejected`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. GNU long-name records carry path metadata that becomes the visible archive
entry name for DOCX/ODT/EPUB and WordPress review packet callers. The bounded
PHP TAR reader now fails closed when that metadata is not NUL terminated,
before exposing package entry names or bytes.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `tar`, external
`gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tooling,
browser renderer, JavaScript, online sanitizer, or online service was
executed.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 441 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the focused expectation and before the production
    guard: `1 test files, 443 assertions, 1 failures`.
  - Failure: `Expected exception RuntimeException was not thrown`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 443 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1091 -> 1092`.
- `benchmarkDenominator.mapped`: `1543 -> 1544`.
- Focused archive coverage: `45 -> 46` PASS cases and `441 -> 443`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record the verified focused file
  totals: `archiveCompressionStreamCoreCases=46`,
  `mappedArchiveCompressionStreamCoreCases=46`, and
  `archiveCompressionStreamCoreAssertions=443`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive`, `ArchiveCompressionStream`, and WordPress ZIP/package preflight
example. Full upstream Pandoc runner parity remains blocked on hydrating and
building the pinned Haskell test executables; this TAR metadata integrity
behavior is covered by focused native PHP tests and does not require Pandoc,
Cabal, Haskell runners, Word, LibreOffice, `tar`, `zip`, `unzip`, `lz4`,
`ZipArchive`, external archive tooling, browser renderers, online sanitizers,
or online services.

## Non-Overlap

This does not repeat accepted POSIX TAR file and directory read/write paths,
PAX UTF-8 path validation, PAX size/owner/review metadata policy, GNU
long-name happy-path metadata, GNU long-name UTF-8 validation, GNU long-link
rejection, TAR end-marker validation, TAR drive-letter rejection, base-256
numeric decoding, TAR sparse-file rejection, raw/zlib DEFLATE validation, gzip
member provenance, independent/dependent LZ4 block decoding or writing,
compressed ZIP dispatch, generic TAR/ZIP package-kind detection, ZIP/OPC
package primitives, DOCX/ODT/EPUB readers, doctemplates, YAML metadata,
CSL/BibTeX, table geometry, math/TeX conversion, PDF handoff planning, legacy
DOC/CFB, charset, syntax highlighting, or Markdown/HTML reader and writer
behavior.

## Follow-Up

Keep recursive nested archive discovery, encrypted archive preflight,
filesystem extraction, multi-volume tar/zip handling, sparse-file
reconstruction, hardlink/symlink extraction policy, dictionary-backed LZ4
frames, non-deflate ZIP compression methods, and full upstream-runner
dependency planning as separate bounded slices unless concrete Pandoc package
fixtures require them.
