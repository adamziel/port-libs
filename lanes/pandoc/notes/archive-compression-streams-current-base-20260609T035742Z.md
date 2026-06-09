# Archive Compression Streams Current-Base Slice

Date: 2026-06-09 UTC

Micro-slice: `pandoc-archive-compression-streams-current-base-20260609T035742Z`

Accepted base: `bc187b52dace5db0ab124375f4ca1c25f2f84168`

## Behavior

- Added `ArchiveCompressionStream::inspectZipLocalHeaderOrderPolicy()` to decode ZIP package bytes from plain ZIP, gzip-wrapped ZIP, zlib ZIP, raw-deflate ZIP, and LZ4 ZIP carriers before applying `ZipPackage::localHeaderOrderPreflight()`.
- The wrapper preserves stream provenance and reports `central-directory-local-header-order-mismatch` with `review-before-conversion` when central-directory order differs from local-header order.
- Extended the WordPress archive-stream preflight example with a gzip-wrapped ODT-like ZIP whose `mimetype` local header appears first while central-directory entries are reordered for review.

## Verification

- Baseline before patch: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 4155 assertions, 0 failures`.
- Red-first focused test after adding coverage before implementation: same command -> failed on missing `ArchiveCompressionStream::inspectZipLocalHeaderOrderPolicy()`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 4262 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` -> `wordpress-archive-stream-preflight self-test passed`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native `ZipPackage::localHeaderOrderPreflight()`, existing bounded `ArchiveCompressionStream` ZIP/gzip/zlib/raw-deflate/LZ4 decoders, `GzipStream`, `DeflateStream`, `Lz4Frame`, and the WordPress archive stream preflight example. No Pandoc, Cabal/Haskell runner, TeX/PDF engine, Word, LibreOffice, zip/unzip, tar, browser renderer, external converter, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This slice does not repeat the accepted local-header span, local-header name/metadata, central-directory inventory/signature, archive-extra-data, descriptor, ZIP64, split archive, encrypted, general-purpose flag, unsupported compression, TAR sparse, multivolume, or LZ4 skippable/source-boundary checks. It only adds the compressed stream wrapper and WordPress smoke for central-directory order versus local-header order.

## Next

Choose a non-overlapping archive stream gap such as nested archive handoff limits, TAR/LZ4 provenance, or ZIP central-directory accounting not already covered by the stream wrappers above.
