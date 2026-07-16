# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T025938Z`

Base accepted HEAD: `a73f3f3a902b40cdaf0ad2e12031eda87dba4604`

## Implementation

- Tightened `TarArchive::fromString()` so bounded TAR review packets must
  contain a real two-block zero end marker before entries are exposed.
- Aligned TAR byte streams that end immediately after the last payload now fail
  with an explicit missing end-marker diagnostic.
- Streams with only one trailing zero record now fail as incomplete TAR end
  markers.
- The WordPress ZIP/package preflight smoke now verifies that TAR review
  packets without the required two zero records are rejected before import.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. TAR package fixtures should terminate with two zero records; accepting an
aligned stream that simply stops after the last payload makes truncated review
packets look complete. The bounded native contract here is: validate the TAR
container end marker first, then expose safe regular file/directory entries to
DOCX/ODT/EPUB or WordPress import handoff code.

It does not implement tar sparse file reconstruction, hardlink/symlink
materialization, filesystem extraction, compressed ZIP dispatch, encrypted
archive decoding, or recursive nested archive policy.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 183 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the missing-end-marker check: `1 test files, 176 assertions, 1 failure`.
  - Failure: `Expected exception RuntimeException was not thrown`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 185 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6107 assertions, 0 failures`; `559` PASS lines.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `557 -> 559` based on the current full focused lane test run.
- `benchmarkDenominator.mapped`: `1037 -> 1038`.
- `archiveCompressionStreamCoreCases`: `10 -> 19`, correcting the stale
  manifest counter to the current focused archive shape after adding this
  named TAR end-marker PASS case.
- `archiveCompressionStreamCoreAssertions`: `101 -> 185`, correcting the stale
  manifest counter to the current focused archive assertion count.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `TarArchive` reader and keeps the integrity policy lane-local. Full
upstream Pandoc runner parity remains blocked on hydrating and building the
pinned Haskell test executables; this archive integrity work does not need that
runner and does not introduce a new dependency.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip extra subfield
validation, explicit or auto-detected archive dispatch, POSIX TAR file and
directory read/write paths, PAX metadata, GNU long-name metadata, raw/zlib
DEFLATE wrapper validation, independent/skippable/dependent LZ4 frame decoding,
ZIP/OPC package primitives, XML/HTML5 DOM helpers, DOCX/ODT/EPUB readers,
doctemplates, YAML metadata, CSL/BibTeX, table geometry, math/TeX conversion,
PDF handoff planning, legacy DOC/CFB, charset, syntax highlighting, or
Markdown/HTML reader and writer behavior.

## Follow-Up

Keep tar sparse files, hardlink/symlink extraction/materialization policy,
encrypted archive preflight, filesystem extraction, compressed ZIP dispatch,
recursive nested archive policy, and non-deflate compression methods as
separate bounded slices unless concrete Pandoc package fixtures require them.
