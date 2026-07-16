# Pandoc Archive Compression Streams Current Base Duplicate

Slice: `pandoc-archive-compression-streams-current-base-duplicate-20260609T062114Z`

Base accepted HEAD: `92533a92bda5fcca4bf5b10d8bb594be7e689c42`

## Implementation

- Added `TarArchive::duplicateEntryNamePreflight()` to report exact duplicate
  TAR member names without exposing payload bytes or constructing a rejected
  archive.
- Added `ArchiveCompressionStream::inspectTarDuplicateEntryNamePolicy()` for
  duplicate TAR member-name preflight across plain TAR, gzip-wrapped TAR,
  zlib-wrapped TAR, raw-deflate TAR, and LZ4-framed TAR streams.
- Extended `wordpress-archive-stream-preflight.php --self-test` with a
  gzip-wrapped TAR fixture where a local PAX `path` alias resolves to an
  existing header name, keeping the duplicate archive metadata-only for
  WordPress import review.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. TAR member names are the archive inventory for review packets and package
fixtures; exact duplicate names are ambiguous because later entries can spoof
or shadow earlier review material. This slice ports the importer-facing
compressed-stream policy handoff only. It does not implement external tar
behavior, TAR repair, sparse extraction, encrypted archives, or a generic
archive ecosystem.

## Evidence

- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before implementation: `1 test files, 5416 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectTarDuplicateEntryNamePolicy()`.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after implementation: `1 test files, 5568 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2438 -> 2439`.
- `benchmarkDenominator.mapped`: `2826 -> 2827`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 272`.
- Focused assertion growth: `+152` in
  `ArchiveCompressionStreamTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP TAR
checksum/PAX/GNU name resolution, duplicate-entry rejection, gzip/zlib/
raw-deflate/LZ4 stream decoding, and archive stream inspection support. Full
upstream Pandoc runner parity remains a separate upstream-runner dependency
task requiring a hydrated Pandoc checkout and Haskell test executables.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip member package
boundaries, source-name policies, ZIP duplicate central-directory entry-name
preflight, ZIP central-directory signature provenance, split ZIP disk markers,
ZIP extra field policies, ZIP encryption policy, ZIP comments/attributes, TAR
duplicate PAX keyword policy, TAR case-insensitive name collisions, TAR sparse
map policy, LZ4 content-size/source-boundary policies, OPC relationships,
DOCX/ODT/EPUB readers, doctemplates, YAML metadata, CSL/BibTeX, table
geometry, math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset,
or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep compressed ZIP raw-name collision surfacing, ZIP duplicate local-header
offset stream policy, and stricter archive stream ambiguity diagnostics as
separate bounded slices.
