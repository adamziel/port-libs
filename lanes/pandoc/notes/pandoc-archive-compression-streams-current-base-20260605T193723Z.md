# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T193723Z`

Base accepted HEAD: `6f05ed9ef56a3e997ebab442f86ef1aa7076de74`

## Implementation

- Added bounded duplicate-key rejection for TAR PAX extended metadata.
- `TarArchive::parsePaxHeaders()` now rejects repeated PAX keywords before
  local or global PAX metadata is applied to an entry.
- Covered ambiguous local `path`, local `size`, and global `comment` records
  so WordPress review queues do not see overwritten provenance for a TAR packet.
- Updated the WordPress ZIP/package preflight smoke to report
  `tarDuplicatePaxKeywordPolicy=rejected`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Prior archive slices added gzip/zlib/raw-DEFLATE/LZ4 stream dispatch,
split-gzip member provenance, TAR PAX path/size/owner metadata, GNU long-name
metadata, sparse/link/device rejection, and generic package-kind detection.
This slice closes a remaining TAR metadata safety gap: repeated PAX records can
otherwise silently overwrite path, size, or reviewer comment metadata before
package bytes are exposed.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `tar`, external
`gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tooling, browser
renderer, JavaScript, online sanitizer, or online service was executed.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 434 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding duplicate-PAX expectations:
    `1 test files, 435 assertions, 1 failures`.
  - Failure: `Expected exception RuntimeException was not thrown`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 437 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1050 -> 1051`.
- `benchmarkDenominator.mapped`: `1503 -> 1504`.
- Focused archive coverage: `43 -> 44` PASS cases and `434 -> 437`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=44`,
  `mappedArchiveCompressionStreamCoreCases=44`, and
  `archiveCompressionStreamCoreAssertions=437`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive` parser, `ArchiveCompressionStream` helpers, focused PHP test
harness, and WordPress ZIP/package preflight example. Full upstream Pandoc
runner parity remains blocked on hydrating and building the pinned Haskell test
executables; this TAR metadata safety behavior is covered by focused native PHP
tests and does not require Pandoc, Cabal, Haskell runners, Word, LibreOffice,
`tar`, `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tooling,
browser renderers, online sanitizers, or online services.

## Non-Overlap

This does not repeat accepted ZIP/OPC package primitive parsing, compressed ZIP
stream dispatch, TAR stream dispatch, ZIP central-directory signature
provenance, unsupported ZIP compression-method policy, gzip member framing,
gzip Latin-1/provenance labels, split-gzip TAR member provenance, raw/zlib
DEFLATE TAR provenance, POSIX TAR file and directory read/write paths, PAX
path/size/owner/review UTF-8 policy, global per-entry PAX rejection, GNU
long-name metadata, TAR sparse/link/device rejection, LZ4 frame
parsing/writing, dependent LZ4 block support, DOCX/ODT/EPUB readers,
doctemplates, YAML metadata, CSL/BibTeX, table geometry, math/TeX conversion,
PDF handoff planning, legacy DOC/CFB, charset, syntax highlighting, or
Markdown/HTML reader and writer behavior.

## Follow-Up

Keep recursive nested archive discovery, encrypted archive preflight,
filesystem extraction, multi-volume tar/zip handling, sparse-file
reconstruction, hardlink/symlink extraction policy, dictionary-backed LZ4
frames, non-deflate ZIP compression methods, and full upstream-runner
dependency planning as separate bounded slices unless concrete Pandoc package
fixtures require them.
