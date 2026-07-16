# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260604T100712Z`

Base accepted HEAD: `89029d158e5bcac0a04bde5f9b56f2c49a0de661`

## Implementation

- Added `GzipStream` as a bounded native PHP gzip member helper for package
  fixture streams.
- `GzipStream::build()` emits gzip members around raw DEFLATE payloads with
  bounded `mtime`, extra flags, operating system, optional extra-field bytes,
  original filename, comment, header CRC, and CRC32/ISIZE trailer fields.
- `GzipStream::members()` parses one or more concatenated gzip members,
  validates reserved flags, compression method, optional header CRC, trailer
  CRC32, trailer uncompressed size, and an optional cumulative uncompressed
  byte limit.
- `GzipStream::decode()` concatenates member payloads for importer handoff
  paths such as gzip-wrapped ZIP/OPC fixtures.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Gzip is a bounded archive stream wrapper needed by package fixtures before
higher-level DOCX/EPUB/ODT readers can inspect ZIP/OPC bytes. The implementation
uses PHP zlib only for raw DEFLATE/inflate and keeps gzip framing, optional
metadata, member boundaries, and integrity validation in native PHP. It does
not call Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, or online services.

## Red/Green Evidence

- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before implementation: `1 test files, 106 assertions, 3 failures`.
  - Failure: the three gzip cases failed with
    `Class "PortLibs\\Pandoc\\GzipStream" not found`.
- After implementation:
  - `php -l lanes/pandoc/src/GzipStream.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 141 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `9 test files, 3015 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed beyond the bounded `GzipStream` helper added
in this slice. It reuses PHP zlib raw DEFLATE support already required by the
ZIP package primitive, and it removes the local archive-compression blocker
previously recorded for `PortLibs\\Pandoc\\GzipStream`.

## Non-Overlap

This does not repeat accepted ZIP central-directory parsing, ZIP package
writing, ZIP extra-field parsing, local-header CRC/size/timestamp checks,
data-descriptor handling, OPC relationships/content-types, DOCX/ODT package
readers, doctemplates, YAML metadata, CSL/citation handling, table geometry,
math/TeX conversion, PDF engine handoff planning, or Markdown/HTML reader and
writer behavior.

## Follow-Up

Keep tar file entries, LZ4 frames, ZIP64, symlink/NTFS extra-field policy,
encrypted ZIP entries, and higher-level package diagnostics as separate bounded
slices unless concrete Pandoc fixtures require them.
