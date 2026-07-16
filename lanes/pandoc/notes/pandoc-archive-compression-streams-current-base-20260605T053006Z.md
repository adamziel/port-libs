# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T053006Z`

Base accepted HEAD: `4d91007bafdf12504e3d93f023ba1b74fc3b19ae`

## Implementation

- Tightened `TarArchive::fromString()` so POSIX USTAR headers with magic
  `ustar\0` must use version bytes `00`.
- Unsupported USTAR versions now fail closed before regular file payload bytes
  are exposed to DOCX/ODT/EPUB or WordPress review-packet handoff code.
- Updated the WordPress ZIP/package preflight smoke to report
  `tarUstarVersionPolicy=rejected`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. POSIX USTAR headers pair the `ustar\0` magic with version `00`; accepting a
different version makes an archive variant look like a normal bounded package
without a parser contract for its metadata semantics.

This does not implement sparse-file reconstruction, hardlink/symlink
materialization, recursive nested archive discovery, filesystem extraction,
encrypted archive preflight, compressed ZIP dispatch, multi-volume archive
handling, or non-deflate ZIP compression methods.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 211 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the USTAR-version expectation: `1 test files, 212 assertions, 1 failures`.
  - Failure: `Expected exception RuntimeException was not thrown`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 212 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `654 -> 655`.
- `benchmarkDenominator.mapped`: `1130 -> 1131`.
- Focused archive coverage: `25 -> 26` PASS cases and `211 -> 212`
  assertions in `ArchiveCompressionStreamTest.php`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive` parser and the WordPress package preflight smoke. Full upstream
Pandoc runner parity remains blocked on hydrating and building the pinned
Haskell test executables; this TAR header validation does not need that runner
and does not introduce a new dependency.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip extra subfield
validation, explicit or auto-detected archive dispatch, POSIX TAR file and
directory read/write paths, PAX path/size/owner/global metadata, GNU long-name
metadata, TAR end-marker validation, TAR drive-letter rejection, base-256
numeric decoding, TAR sparse-file rejection, raw/zlib DEFLATE wrapper
validation, independent/skippable/dependent LZ4 frame decoding, ZIP/OPC
package primitives, XML/HTML5 DOM helpers, DOCX/ODT/EPUB readers,
doctemplates, YAML metadata, CSL/BibTeX, table geometry, math/TeX conversion,
PDF handoff planning, legacy DOC/CFB, charset, syntax highlighting, or
Markdown/HTML reader and writer behavior.

## Follow-Up

Keep recursive nested archive policy, encrypted archive preflight, filesystem
extraction, compressed ZIP dispatch, multi-volume tar/zip handling, sparse-file
reconstruction, hardlink/symlink extraction policy, and non-deflate compression
methods as separate bounded slices unless concrete Pandoc package fixtures
require them.
