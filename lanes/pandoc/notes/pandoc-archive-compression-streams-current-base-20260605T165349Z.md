# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T165349Z`

Base accepted HEAD: `017e60c2d3368584565a1ace8949235ce293214b`

## Implementation

- Added bounded ZIP package stream dispatch to `ArchiveCompressionStream`.
- New ZIP formats cover plain ZIP bytes plus gzip, zlib-wrapped DEFLATE,
  raw-DEFLATE, and LZ4-framed ZIP package bytes.
- Added explicit and auto-detected `decodeZipBytes`, `openZip`, and
  `inspectZipStream` helpers that hand decoded bytes to the existing native
  `ZipPackage` reader.
- ZIP stream inspection reports decoded package byte size, package entry names,
  entry uncompressed byte total, and existing gzip/deflate/LZ4 wrapper
  provenance.
- Updated the WordPress ZIP/package preflight smoke so a gzip-wrapped DOCX/OPC
  package fixture is decoded through the dispatcher rather than directly
  through `GzipStream`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Prior archive slices covered gzip/zlib/raw-deflate/LZ4 TAR packet
dispatch and left compressed ZIP dispatch as a separate follow-up. DOCX, EPUB,
and ODT package fixtures are ZIP/OPC containers, so bounded wrapper dispatch is
needed before WordPress import review can inspect package bytes without
shelling out to archive tools.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `tar`, external
`gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tooling, browser
renderer, JavaScript, online sanitizer, or online service was executed.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 335 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding compressed-ZIP dispatcher expectations:
    `1 test files, 335 assertions, 2 failures`.
  - Failure: `ArchiveCompressionStream::FORMAT_ZIP` and the ZIP stream
    dispatcher methods were missing.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 417 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
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

- `phpPass`: `1005 -> 1007`.
- `benchmarkDenominator.mapped`: `1460 -> 1461`.
- Focused archive coverage: `40 -> 42` PASS cases and `335 -> 417`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=42`,
  `mappedArchiveCompressionStreamCoreCases=42`, and
  `archiveCompressionStreamCoreAssertions=417`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ArchiveCompressionStream`, `GzipStream`, `DeflateStream`, `Lz4Frame`,
`ZipPackage`, and WordPress ZIP/package preflight example. Full upstream
Pandoc runner parity remains blocked on hydrating and building the pinned
Haskell test executables; this package-stream dispatch behavior is covered by
focused native PHP tests and does not require Pandoc, Cabal, Haskell runners,
Word, LibreOffice, `tar`, `zip`, `unzip`, `lz4`, `ZipArchive`, external
archive tooling, browser renderers, online sanitizers, or online services.

## Non-Overlap

This does not repeat accepted ZIP/OPC package primitive parsing, ZIP central
directory signature provenance, unsupported ZIP compression-method policy,
gzip member framing, gzip Latin-1/provenance labels, split-gzip TAR member
provenance, raw/zlib DEFLATE TAR provenance, POSIX TAR file and directory
read/write paths, PAX path/size/owner/review metadata policy, GNU long-name
metadata, TAR sparse/link/device rejection, LZ4 frame parsing/writing,
dependent LZ4 block support, DOCX/ODT/EPUB readers, doctemplates, YAML
metadata, CSL/BibTeX, table geometry, math/TeX conversion, PDF handoff
planning, legacy DOC/CFB, charset, syntax highlighting, or Markdown/HTML
reader and writer behavior.

## Follow-Up

Keep recursive nested archive discovery, encrypted archive preflight,
filesystem extraction, multi-volume tar/zip handling, sparse-file
reconstruction, hardlink/symlink extraction policy, dictionary-backed LZ4
frames, non-deflate ZIP compression methods, and full upstream-runner
dependency planning as separate bounded slices unless concrete Pandoc package
fixtures require them.
