# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T050049Z`

Base accepted HEAD: `11a7b6924e8b549c836158a54da8e2a995e7ea6f`

## Implementation

- Tightened `TarArchive::fromString()` so TAR sparse-file metadata fails closed
  before package bytes are exposed.
- Old GNU sparse entries with typeflag `S` now get an explicit unsupported
  sparse-file diagnostic instead of falling through to a generic unsupported
  entry type.
- PAX headers carrying `GNU.sparse.*`, `SCHILY.sparse.*`, or
  `SCHILY.filetype=sparse` now reject the following entry even when the entry
  itself is encoded as a regular file.
- Updated the WordPress ZIP/package preflight smoke to report
  `tarSparsePolicy=rejected`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Hackage `tar` entry docs for `Codec.Archive.Tar.Entry` model normal files,
directories, links, devices, pipes, and `OtherEntryType`, and state that
portable archives should contain only normal files and directories. The local
Pandoc support contract is narrower: expose safe regular files/directories to
DOCX/ODT/EPUB/WordPress package fixtures, and reject sparse-file metadata until
there is a bounded reconstruction contract.

This does not implement sparse-file reconstruction, hardlink/symlink
materialization, recursive nested archive discovery, filesystem extraction,
encrypted archive preflight, compressed ZIP dispatch, multi-volume archive
handling, or non-deflate ZIP compression methods.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 208 assertions, 0 failures`.
- After implementation:
  - `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 211 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `637 -> 638`.
- `benchmarkDenominator.mapped`: `1112 -> 1113`.
- `archiveCompressionStreamCoreCases`: `24 -> 25` in focused archive coverage.
- `archiveCompressionStreamCoreAssertions`: `208 -> 211` in focused archive
  coverage.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive` parser and the WordPress package preflight smoke. Full upstream
Pandoc runner parity remains blocked on hydrating and building the pinned
Haskell test executables; this TAR sparse-policy work does not need that runner
and does not introduce a new dependency.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip extra subfield
validation, explicit or auto-detected archive dispatch, POSIX TAR file and
directory read/write paths, PAX path/size/owner/global metadata, GNU long-name
metadata, TAR end-marker validation, TAR drive-letter rejection, base-256
numeric decoding, raw/zlib DEFLATE wrapper validation, independent/skippable/
dependent LZ4 frame decoding, ZIP/OPC package primitives, XML/HTML5 DOM
helpers, DOCX/ODT/EPUB readers, doctemplates, YAML metadata, CSL/BibTeX, table
geometry, math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset,
syntax highlighting, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep sparse-file reconstruction, hardlink/symlink extraction policy, recursive
nested archive policy, encrypted archive preflight, filesystem extraction,
compressed ZIP dispatch, multi-volume tar/zip handling, and non-deflate
compression methods as separate bounded slices unless concrete Pandoc package
fixtures require them.
