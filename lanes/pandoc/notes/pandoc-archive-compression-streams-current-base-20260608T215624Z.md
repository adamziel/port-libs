# Pandoc Archive Compression Streams - GZIP Member Count Policy

Slice: `pandoc-archive-compression-streams-current-base-20260608T215624Z`

Base accepted HEAD: `d291953d10cb3a81d9c31878d6d7b3226cc33af0`

## Behavior

- Added `ArchiveCompressionStream::inspectGzipMemberCountPolicy()` for
  gzip-wrapped TAR and ZIP package streams.
- The policy reports total member count, configured threshold, over-limit
  count, first over-limit member index, trailing padding bytes, and
  per-member byte offsets and source-name/comment metadata.
- Members past the threshold are marked `review-before-conversion` with the
  `gzip-member-over-limit` diagnostic; the aggregate result reports
  `gzip-member-count-exceeds-threshold`.
- The result is metadata-only and does not expose decoded package bytes,
  `TarArchive`, `ZipPackage`, `archive`, `package`, `tarBytes`, `zipBytes`, or
  per-member `data`.
- Updated the WordPress archive-stream preflight example so upload review
  packets can flag excessive concatenated gzip members before conversion.

## Source Truth

Pandoc conversion support needs bounded native package-stream preflight before
DOCX/ODT/EPUB/TAR/ZIP handoff. RFC 1952 permits concatenated gzip members; the
slice keeps that decode path intact while adding a review threshold for package
streams split across too many gzip members. This does not shell out to Pandoc,
tar, gzip, zip/unzip, lz4, ZipArchive, Word, LibreOffice, Haskell runners, or
online services.

## Verification

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2744 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2744 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectGzipMemberCountPolicy()`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2774 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
- PHP lint:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php` passed.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed.
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php` passed.
- JSON validation:
  - `lanes/pandoc/lane-status.json` valid.
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` valid.

`git diff --check -- lanes/pandoc` passed after this note was added.
Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1895 -> 1896`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2317 -> 2318`.
- Archive compression mapped core cases: `11 -> 12`.
- Archive compression focused assertion counter: `120 -> 150`.
- Focused `ArchiveCompressionStreamTest.php` assertion count: `2744 -> 2774`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`ArchiveCompressionStream`, `GzipStream`, `TarArchive`, the focused archive
test, and the existing WordPress archive-stream preflight example.

## Non-Overlap

This does not repeat accepted gzip member provenance, gzip byte-layout offsets,
decoded source chunk mapping, gzip member package-boundary policy, gzip
source-name policy, gzip FTEXT binary-payload policy, ZIP descriptor/ZIP64/
split/encryption/general-purpose/compression-method policies, unsupported
BZip2/XZ/Zstandard blocking, archive-bomb ratios, nested package discovery,
PAX timestamp/hdrcharset/duplicate-key handling, sparse/multi-volume/
incremental/link/special-file TAR policies, zlib/LZ4 dictionary streams, split
LZ4 frame provenance, or supplied LZ4 dictionary decode. The patch is limited
to a metadata-only concatenated-gzip member-count threshold before conversion
handoff.

## Follow-Up

Useful follow-ups remain TAR checksum provenance across package fixtures, gzip
per-member byte-limit diagnostics, LZ4 skippable-frame metadata, and stricter
LZ4 block-size boundaries.
