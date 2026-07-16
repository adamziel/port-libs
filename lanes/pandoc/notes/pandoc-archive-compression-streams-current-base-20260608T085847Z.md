# Pandoc Archive Compression Streams Current Base - Unsupported BZip2/XZ Policy

Slice: `pandoc-archive-compression-streams-current-base-20260608T085847Z`
Base: `f0ab9f09ee4c07b41223f5f4b712e9f9688694c6`
Date: 2026-06-08 UTC

## Behavior

Added bounded native preflight policy for bzip2 and xz archive-compression streams. `ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy()` now recognizes:

- BZip2 streams from `BZh1` through `BZh9` signatures, including block-size metadata.
- XZ streams from the `fd 37 7a 58 5a 00` signature, including stream flag bytes when present.
- Tar/zip package candidates from `.tar.bz2`, `.tbz2`, `.tbz`, `.zip.bz2`, `.tar.xz`, `.txz`, and `.zip.xz` source names.

The policy is fail-closed: it records candidate kind/format metadata, reports that external decompressors were not run, and keeps package bytes unexposed. It does not implement bzip2/xz decoding and does not invoke external archive tools.

## Evidence

Baseline before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
- Result: `1 test files, 1583 assertions, 0 failures`

Focused verification after implementation:

- `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
- Result: `No syntax errors detected in lanes/pandoc/src/ArchiveCompressionStream.php`
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
- Result: `No syntax errors detected in lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
- `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
- Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
- Result: both lane JSON files valid.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
- Result: `1 test files, 1619 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
- Result: `wordpress-archive-stream-preflight self-test passed`

Delta: +1 PHP PASS case, +36 focused assertions, mapped denominator 2006 -> 2007, archive-compression core cases 11 -> 12, archive-compression focused assertions 120 -> 156.

## Non-Overlap

This does not repeat the accepted gzip provenance, raw/zlib, LZ4 dictionary, split LZ4 range, TAR PAX timestamp/hdrcharset, sparse, multi-volume, nested archive, or archive-bomb policy slices. It is limited to unsupported bzip2/xz stream policy metadata.

## Dependency Closure

No new support component is needed for this slice. It reuses the existing native archive compression preflight and package-kind model. BZip2/XZ decoding remains intentionally unsupported unless a future bounded native decoder is approved with its own activation gate and fixtures.

No Pandoc, Cabal solver/build/test command, Haskell runner, tar, gzip, zip/unzip, lz4, ZipArchive, bzip2, xz, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

Root harness: not run - isolated micro-slice.
