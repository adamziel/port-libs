# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T040115Z`

Base accepted HEAD: `02fcdbdf4622561a72beafa8b0451f7fae48dcd2`

## Implementation

- Tightened `TarArchive::fromString()` so local PAX extended metadata must be
  followed by a real archive entry before any payload bytes are exposed.
- Local PAX metadata now fails closed when it dangles at the end marker, is
  overwritten by another local PAX header, or is followed by GNU long-name or
  global PAX metadata instead of an entry.
- TAR path validation now rejects Windows drive-letter paths such as
  `C:packet/document.xml` from ordinary headers, PAX `path` metadata, GNU
  long-name metadata, and writer-side `TarArchive::fromEntries()` input.
- The WordPress ZIP/package preflight smoke now exposes the same
  `tarDanglingPaxPolicy` and `tarDriveLetterPolicy` checks.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Local PAX extended headers describe the immediately following archive
entry; accepting a dangling or overwritten local PAX header makes truncated or
ambiguous review packets look complete. Drive-letter paths are absolute or
drive-relative on Windows import targets, so they are unsafe for bounded
package handoff for the same reason leading slash, backslash, and traversal
paths are unsafe.

The bounded native contract here is: validate TAR metadata binding and path
safety before document package bytes reach DOCX/ODT/EPUB or WordPress import
handoff code.

It does not implement tar sparse-file reconstruction, hardlink or symlink
materialization, recursive nested archive discovery, filesystem extraction,
encrypted archive preflight, compressed ZIP dispatch, multi-volume archive
handling, or non-deflate ZIP compression methods.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 195 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the first two fail-closed cases: `1 test files, 197 assertions, 2 failures`.
  - Failures: expected `RuntimeException` was not thrown for dangling local
    PAX metadata and drive-letter TAR paths.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 203 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
  - `php -l lanes/pandoc/src/TarArchive.php && php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php && php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors in all three changed PHP files.
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `601 -> 603`.
- `benchmarkDenominator.mapped`: `1075 -> 1077`.
- `archiveCompressionStreamCoreCases`: `21 -> 23` in current focused archive
  coverage.
- `archiveCompressionStreamCoreAssertions`: `195 -> 203` in current focused
  archive coverage.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `TarArchive` parser and the WordPress ZIP/package preflight smoke. Full
upstream Pandoc runner parity remains blocked on hydrating and building the
pinned Haskell test executables; this TAR metadata/path safety work does not
need that runner and does not introduce a new dependency.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip extra subfield
validation, explicit or auto-detected archive dispatch, POSIX TAR regular
file/directory handling, PAX size/owner metadata handoff, GNU long-name
metadata handoff, TAR end-marker validation, base-256 numeric decoding,
raw/zlib DEFLATE wrapper validation, independent/skippable/dependent LZ4 frame
decoding, ZIP/OPC package primitives, XML/HTML5 DOM helpers, DOCX/ODT/EPUB
readers, doctemplates, YAML metadata, CSL/BibTeX, table geometry, math/TeX
conversion, PDF handoff planning, legacy DOC/CFB, charset, syntax
highlighting, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep tar sparse files, hardlink/symlink extraction policy, recursive nested
archive policy, encrypted archive preflight, filesystem extraction, compressed
ZIP dispatch, multi-volume tar/zip handling, and non-deflate ZIP compression
methods as separate bounded slices unless concrete Pandoc package fixtures
require them.
