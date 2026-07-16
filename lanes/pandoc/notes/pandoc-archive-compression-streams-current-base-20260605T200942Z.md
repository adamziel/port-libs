# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T200942Z`

Base accepted HEAD: `28a3318b8df99d6bd1d9002362d2936df58d9351`

## Implementation

- Tightened `TarArchive` PAX timestamp parsing so `mtime` values are parsed
  from their integer text component instead of through floating point.
- Local and global PAX `mtime` metadata now reject integer parts too large for
  the current PHP runtime before archive entries or package bytes are exposed.
- Normal fractional PAX timestamps still floor to integer seconds, preserving
  the accepted bounded TAR metadata handoff behavior.
- Updated the WordPress ZIP/package preflight smoke to report
  `tarPaxMtimeOverflowPolicy=rejected`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Prior archive slices added PAX size/owner metadata, duplicate-key
rejection, global per-entry key rejection, base-256 numeric bounds, sparse and
link rejection, gzip/zlib/raw-DEFLATE/LZ4 dispatch, and generic package-kind
detection. This slice closes a distinct metadata safety gap: oversized PAX
timestamps must not silently clamp through PHP float-to-int conversion before a
WordPress review packet sees import metadata.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `tar`, external
`gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tooling, browser
renderer, JavaScript, online sanitizer, or online service was executed.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 437 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the focused PAX `mtime` overflow test:
    `1 test files, 440 assertions, 1 failures`.
  - Failure: `Expected exception RuntimeException was not thrown`, with a PHP
    float-to-int warning from `TarArchive::parsePaxIntegerTimestamp()`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 441 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
  - `git diff --check -- lanes/pandoc`
  - Result: clean.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1061 -> 1062`.
- `benchmarkDenominator.mapped`: `1514 -> 1515`.
- Focused archive coverage: `44 -> 45` PASS cases and `437 -> 441`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record the verified focused file
  totals: `archiveCompressionStreamCoreCases=45`,
  `mappedArchiveCompressionStreamCoreCases=45`, and
  `archiveCompressionStreamCoreAssertions=441`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive` parser, focused PHP test harness, and WordPress ZIP/package
preflight example. Full upstream Pandoc runner parity remains blocked on
hydrating and building the pinned Haskell test executables; this TAR metadata
safety behavior is covered by focused native PHP tests and does not require
Pandoc, Cabal, Haskell runners, Word, LibreOffice, `tar`, `gzip`, `zip`,
`unzip`, `lz4`, `ZipArchive`, external archive tooling, browser renderers,
online sanitizers, or online services.

## Non-Overlap

This does not repeat accepted ZIP/OPC package primitive parsing, compressed ZIP
stream dispatch, TAR stream dispatch, ZIP central-directory signature
provenance, unsupported ZIP compression-method policy, gzip member framing,
gzip Latin-1/provenance labels, split-gzip TAR member provenance, raw/zlib
DEFLATE TAR provenance, POSIX TAR file and directory read/write paths, PAX
path/size/owner/review UTF-8 policy, duplicate PAX keyword rejection, global
per-entry PAX rejection, GNU long-name metadata, TAR sparse/link/device
rejection, LZ4 frame parsing/writing, dependent LZ4 block support, DOCX/ODT/
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
