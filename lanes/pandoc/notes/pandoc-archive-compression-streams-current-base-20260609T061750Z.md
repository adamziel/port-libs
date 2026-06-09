# Pandoc Archive Compression Streams - GZIP Trailer Integrity

Micro-slice: `pandoc-archive-compression-streams-current-base-20260609T061750Z`

Base: `e156ca4a3faced08196d21d6a223f2a4fd3e6a8e`

## Implementation

- Added `GzipStream::trailerIntegrityPreflight()` for metadata-only inspection of gzip member trailers.
- Added `ArchiveCompressionStream::inspectGzipTrailerIntegrityPolicy()` for gzip-wrapped TAR/ZIP package streams.
- The preflight reports stored CRC32, computed CRC32, stored ISIZE, decoded byte size, decoded offsets, member offsets, and per-member diagnostics.
- Corrupt gzip members are marked `review-before-conversion` without returning decoded package bytes; strict `GzipStream::decode()` and package opening still reject the corrupt stream.
- Added `wordpress-gzip-trailer-integrity-preflight.php` as the WordPress import smoke.

## Verification

Baseline before edit:

`php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`

Result: `1 test files, 5266 assertions, 0 failures`.

After implementation:

`php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`

Result: `1 test files, 5312 assertions, 0 failures`.

Added: `1` focused PASS line and `46` focused assertions.

Syntax checks:

- `php -l lanes/pandoc/src/GzipStream.php`
- `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
- `php -l lanes/pandoc/examples/wordpress-gzip-trailer-integrity-preflight.php`

All reported no syntax errors.

Example smoke:

`php lanes/pandoc/examples/wordpress-gzip-trailer-integrity-preflight.php --self-test`

Result: `gzip trailer integrity preflight self-test passed`.

## Dependency Closure

No new support component is needed. This reuses native PHP gzip header parsing, raw DEFLATE inflation, CRC32/ISIZE trailer parsing, archive stream policy wrappers, and the existing PHP test harness.

Full upstream Pandoc runner parity remains gated on a hydrated pinned Pandoc checkout and Haskell test executables. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `tar`, `gzip`, `lz4`, `zip`/`unzip`, `ZipArchive`, browser renderer, TeX/PDF engine, external validator, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted gzip member metadata, timestamp/platform labels, text-hint policy, member count/byte limits, member package-boundary policy, decoded chunk mapping, gzip TAR record-boundary policy, LZ4 frame/content/block/source-boundary policies, tar PAX/link/special/sparse/multi-volume/incremental policies, ZIP package policies, zlib preset dictionary handling, or deflate wrapper provenance. It owns only gzip trailer CRC32/ISIZE integrity review before package handoff.

## Follow-Up

Keep future archive/compression work bounded to one support-library gap, such as a distinct stream-integrity metadata policy or package source-boundary diagnostic that is not already covered by this gzip trailer slice.
