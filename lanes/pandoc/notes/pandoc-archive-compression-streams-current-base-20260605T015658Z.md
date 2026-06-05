# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T015658Z`

Base accepted HEAD: `ecec2b9f3020be56e17c6e2e635bed38cedbf419`

## Implementation

- Extended `GzipStream::members()` to parse RFC-style gzip extra-field
  subfields into structured metadata with `identifier`, byte ids, declared
  length, and payload bytes while preserving the raw `extraFieldData`.
- Added duplicate-subfield and truncated-subfield rejection before archive
  bytes are inflated or exposed to package readers.
- Added writer-side validation so generated gzip review packets cannot emit
  malformed extra-field subfields.
- Updated the WordPress ZIP/package preflight smoke to carry a bounded `WP`
  gzip review subfield and verify it in `--self-test`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Gzip FEXTRA metadata is a bounded header-level stream feature; package
fixtures can use it for importer/reviewer metadata without invoking external
archive tools. The slice ports only the subfield contract needed for native
preflight: validate the field structure, expose the metadata, then continue to
the already accepted gzip/tar bytes.

It does not implement compression auto-detection, filesystem extraction,
registry-specific semantics for every possible gzip subfield id, encrypted
archive preflight, tar sparse files, hardlink/symlink materialization, ZIP64
materialization, or dictionary-backed LZ4 frames.

## Evidence

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 143 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding gzip extra subfield expectations: `1 test files, 147 assertions, 2 failures`.
  - Failures: `extraFields` metadata was absent and malformed extra subfields
    were accepted.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 152 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 221 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `518 -> 520`.
- `benchmarkDenominator.mapped`: `993 -> 995`.
- `archiveCompressionStreamCoreCases`: `14 -> 16` in focused archive coverage.
- `archiveCompressionStreamCoreAssertions`: `143 -> 152` in focused archive
  coverage.

## Dependency Closure

No new external support component is needed. This reuses the accepted native
PHP `GzipStream` component and keeps gzip extra-field validation lane-local.
Full upstream Pandoc runner parity remains blocked on hydrating and building
the pinned Haskell test executables; this archive metadata work does not need
that runner and does not introduce a new dependency.

## Non-Overlap

This does not repeat accepted gzip member framing, concatenated member decode,
CRC/ISIZE validation, POSIX tar regular file/directory handling, PAX metadata,
GNU long-name metadata, raw/zlib DEFLATE support, independent/skippable/
dependent LZ4 frame decoding, ZIP/OPC package primitives, XML/HTML5 DOM
helpers, DOCX/ODT/EPUB readers, doctemplates, YAML metadata, CSL/BibTeX,
table geometry, math/TeX conversion, PDF handoff planning, legacy DOC/CFB,
charset, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep gzip subfield-id registry policy beyond bounded structural validation,
tar sparse files, hardlink/symlink extraction policy, filesystem extraction
policy, encrypted archive preflight, ZIP64 materialization policy, and
dictionary-backed LZ4 frames as separate bounded slices unless concrete Pandoc
package fixtures require them.
