# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T043132Z`

Base accepted HEAD: `b36dbe88ba80463d50bb6c0be8e8621b7076aace`

## Implementation

- Added bounded TAR global PAX metadata handoff to `TarArchive`.
- `TarArchive::build()` / `TarArchive::fromEntries()` now accept optional
  `globalPaxHeaders` for archive-level review metadata, write a native global
  PAX header, and validate PAX keys and values before bytes are emitted.
- `TarArchive::fromString()` now preserves parsed global PAX headers through
  `globalPaxHeaders()` while still exposing the merged metadata on following
  entries.
- The WordPress ZIP/package preflight smoke now wraps a TAR review packet with
  global PAX `comment` and `hdrcharset` metadata and verifies the gzip-wrapped
  packet exposes that metadata after native decode.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Global PAX records are a TAR stream mechanism for archive-level metadata
that applies to subsequent entries. The bounded native contract here is to
preserve reviewer provenance such as comments/charset for import queues while
keeping all bytes in memory and failing closed on malformed PAX key/value data.

This does not implement tar sparse-file reconstruction, hardlink/symlink
materialization, recursive nested archive discovery, filesystem extraction,
encrypted archive preflight, compressed ZIP dispatch, multi-volume archive
handling, or non-deflate ZIP compression methods.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 203 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding global-PAX expectations: `1 test files, 203 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\TarArchive::globalPaxHeaders()`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 208 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `621 -> 622`.
- `benchmarkDenominator.mapped`: `1095 -> 1096`.
- `archiveCompressionStreamCoreCases`: `23 -> 24` in focused archive
  coverage.
- `archiveCompressionStreamCoreAssertions`: `203 -> 208` in focused archive
  coverage.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive`, `GzipStream`, `DeflateStream`, `Lz4Frame`, and WordPress package
preflight smoke. Full upstream Pandoc runner parity remains blocked on
hydrating and building the pinned Haskell test executables; this TAR metadata
handoff does not need that runner and does not introduce a new dependency.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip extra subfield
validation, explicit or auto-detected archive dispatch, POSIX TAR file and
directory read/write paths, PAX path/size/owner metadata, GNU long-name
metadata, TAR end-marker validation, TAR drive-letter rejection, base-256
numeric decoding, raw/zlib DEFLATE wrapper validation, independent/skippable/
dependent LZ4 frame decoding, ZIP/OPC package primitives, XML/HTML5 DOM
helpers, DOCX/ODT/EPUB readers, doctemplates, YAML metadata, CSL/BibTeX, table
geometry, math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset,
syntax highlighting, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep tar sparse files, hardlink/symlink extraction policy, recursive nested
archive policy, encrypted archive preflight, filesystem extraction, compressed
ZIP dispatch, multi-volume tar/zip handling, and non-deflate compression
methods as separate bounded slices unless concrete Pandoc package fixtures
require them.
