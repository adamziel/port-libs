# Pandoc Archive Compression Streams Current Base - ZIP64 Extra Fields

- Slice: `pandoc-archive-compression-streams-current-base-20260609T012742Z`
- Accepted base: `942d0b99001290be4ad52e5f31464bd1e4c71c99`
- Scope: native PHP archive/compression stream preflight only.

## Behavior

Added `ArchiveCompressionStream::inspectZip64ExtraFieldPolicy()` so bounded ZIP,
GZIP-wrapped ZIP, zlib-wrapped ZIP, raw-deflate-wrapped ZIP, and LZ4-wrapped ZIP
streams can be decoded and reviewed for ZIP64 extra fields before package
entries are exposed. The stream wrapper reuses the existing
`ZipPackage::zip64ExtraFieldPreflight()` contract and adds carrier provenance
from `ArchiveCompressionStream::streamInspection()`.

The focused fixture covers central-directory ZIP64 sentinels for
uncompressed size, compressed size, local header offset, and disk start plus a
local-header ZIP64 size sentinel entry. It verifies that strict `ZipPackage`
import remains blocked while the review policy reports entry counts, required
fields, ZIP64 values, source wrapper metadata, and bounded-reader support
diagnostics.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 3192 assertions, 0 failures`
- Final: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 3380 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`
- PHP lint:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors.

## Status Delta

- `lane-status.json` `phpPass`: `2037 -> 2038`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2451 -> 2452`
- `archiveCompressionStreamCoreCases`: `11 -> 12`
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`
- `archiveCompressionStreamCoreAssertions`: `120 -> 308`

## Dependency Closure

No new support component is needed. This slice reuses existing native PHP
`ZipPackage` ZIP64 extra-field parsing, `ArchiveCompressionStream` decoding,
and existing gzip/deflate/LZ4 fixture builders. No Pandoc, Cabal/Haskell
runners, tar, gzip, zip/unzip, lz4 CLI, ZipArchive, Word, LibreOffice,
external archive tools, online services, live provider tests, or
live-service provider tests were run.

## Non-Overlap

This does not repeat accepted gzip timestamp/platform provenance, split-gzip
member layout, TAR PAX metadata, ZIP64 EOCD accounting, archive extra-data
record preflight, split ZIP disk markers, or ZIP package core ZIP64 tests. The
new behavior is the compression-stream wrapper for existing ZIP64 extra-field
review across compressed package carriers.

## Follow-Up

Next archive-compression work should stay non-overlapping: compressed
central-directory provenance, unsupported compression depth diagnostics, or
ZIP local-header flag/name mismatch policy across stream wrappers.
