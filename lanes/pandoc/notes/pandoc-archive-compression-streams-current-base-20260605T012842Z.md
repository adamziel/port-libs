# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T012842Z`

Base accepted HEAD: `9bddfddfa724db6eb1444fd3fa0992c3b60d0943`

## Implementation

- Added `ArchiveCompressionStream` as a bounded native PHP dispatcher for tar
  package/review packets wrapped as plain tar, gzip tar, zlib-wrapped DEFLATE
  tar, raw-DEFLATE tar, or LZ4-framed tar.
- `ArchiveCompressionStream::openTar()` decodes the selected compression
  wrapper, enforces the existing uncompressed byte limits, and then hands the
  result to `TarArchive::fromString()` with an independent unpacked-byte limit.
- `ArchiveCompressionStream::decodeTarBytes()` exposes the decoded tar bytes
  for package preflight paths that need to inspect the stream before opening
  entries.
- Unsupported stream format names and negative limits are rejected before
  bytes are exposed to package readers.
- The WordPress ZIP/package preflight smoke now opens a gzip-wrapped tar review
  packet through the dispatcher without invoking `tar`, `gzip`, `lz4`, `zip`,
  `unzip`, Pandoc, Cabal, Haskell runners, or online services.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Pandoc-side package fixtures and handoff packets need deterministic
archive bytes, not broad filesystem extraction. The slice ports the bounded
stream contract that callers select explicitly: known wrapper in, validated tar
package out. It composes existing native `GzipStream`, `DeflateStream`,
`Lz4Frame`, and `TarArchive` helpers.

It does not implement compression auto-detection, compressed ZIP dispatch,
multi-member tar concatenation policy, tar sparse files, hardlink/symlink
materialization, encrypted archive preflight, or filesystem extraction.

## Evidence

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before implementation: `1 test files, 117 assertions, 0 failures`.
- After implementation:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 143 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5204 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `498 -> 500`.
- `benchmarkDenominator.mapped`: `973 -> 975`.
- `archiveCompressionStreamCoreCases`: `12 -> 14` in current focused tests
  and `10 -> 14` in the previously stale manifest counter.
- `archiveCompressionStreamCoreAssertions`: `117 -> 143` in focused archive
  coverage and `101 -> 143` in the previously stale manifest counter.

## Dependency Closure

No new external support component is needed. This adds a small lane-local
composition helper over existing bounded native PHP archive components. Full
upstream Pandoc runner parity remains blocked on hydrating and building the
pinned Haskell test executables; this archive dispatch work does not need that
runner and does not introduce a new dependency.

## Non-Overlap

This does not repeat accepted gzip member framing, POSIX tar regular
file/directory handling, PAX metadata, GNU long-name metadata, raw/zlib
DEFLATE wrapper validation, independent/skippable/dependent LZ4 frame
decoding, ZIP/OPC package primitives, XML/HTML5 DOM helpers, DOCX/ODT/EPUB
readers, doctemplates, YAML metadata, CSL/BibTeX, table geometry, math/TeX
conversion, PDF handoff planning, legacy DOC/CFB, charset, or Markdown/HTML
reader and writer behavior.

## Follow-Up

Keep compression auto-detection, compressed ZIP dispatch, multi-member tar
concatenation policy, tar sparse files, hardlink/symlink materialization
policy, encrypted archive preflight, and filesystem extraction as separate
bounded slices unless concrete Pandoc package fixtures require them.
