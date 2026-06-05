# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T033258Z`

Base accepted HEAD: `f5ca135693a249a186cc84ad0e99fc396ff4b3bc`

## Implementation

- Extended `TarArchive::fromString()` to decode bounded positive base-256 TAR
  numeric fields for package review packets.
- Base-256 decoding now applies to TAR `size`, `mtime`, `mode`, `uid`, and
  `gid` header fields while keeping the checksum field on the existing octal
  validation path.
- Negative base-256 fields and values too large for the current PHP runtime are
  rejected before entry bytes are exposed.
- The WordPress ZIP/package preflight smoke now includes a base-256 TAR review
  packet and verifies the decoded owner, mode, modified time, size, and document
  bytes.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Modern GNU/POSIX-style tar writers can encode numeric metadata as
base-256 when octal fields are too small or when preserving large owner ids and
timestamps. The bounded native contract here is: accept only non-negative
base-256 metadata that fits in PHP integers, preserve regular file bytes, and
fail closed for negative or overflowing fields before WordPress import handoff
code sees payload bytes.

It does not implement tar sparse-file reconstruction, hardlink or symlink
materialization, recursive nested archive discovery, filesystem extraction,
encrypted archive preflight, compressed ZIP dispatch, multi-volume archive
handling, or non-deflate ZIP compression methods.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 185 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding base-256 coverage: `1 test files, 187 assertions, 1 failures`.
  - Failure: `TAR entry size is not a supported octal TAR field`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 195 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6437 assertions, 0 failures`; `579` PASS lines.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `578 -> 579`.
- `benchmarkDenominator.mapped`: `1056 -> 1058`.
- `archiveCompressionStreamCoreCases`: `19 -> 21` in current focused archive
  coverage.
- `archiveCompressionStreamCoreAssertions`: `185 -> 195` in current focused
  archive coverage.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `TarArchive` parser and the WordPress ZIP/package preflight smoke. Full
upstream Pandoc runner parity remains blocked on hydrating and building the
pinned Haskell test executables; this archive metadata work does not need that
runner and does not introduce a new dependency.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip extra subfield
validation, explicit or auto-detected archive dispatch, POSIX TAR regular
file/directory handling, PAX metadata, GNU long-name metadata, TAR end-marker
validation, raw/zlib DEFLATE wrapper validation, independent/skippable/
dependent LZ4 frame decoding, ZIP/OPC package primitives, XML/HTML5 DOM
helpers, DOCX/ODT/EPUB readers, doctemplates, YAML metadata, CSL/BibTeX, table
geometry, math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset,
syntax highlighting, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep tar sparse files, hardlink/symlink extraction policy, recursive nested
archive policy, encrypted archive preflight, filesystem extraction, compressed
ZIP dispatch, multi-volume tar/zip handling, and non-deflate ZIP compression
methods as separate bounded slices unless concrete Pandoc package fixtures
require them.
