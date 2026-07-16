# Archive Compression Streams Current-Base Slice

Date: 2026-06-08 UTC

Micro-slice: `pandoc-archive-compression-streams-current-base-20260608T214404Z`

Accepted base: `a0d85bbfea71fbea16acdfcda87bce21bb3681b0`

## Behavior

- Added `ArchiveCompressionStream::inspectZipArchiveExtraDataRecordPolicy()` to decode ZIP package bytes from plain ZIP, gzip-wrapped ZIP, zlib ZIP, raw-deflate ZIP, and LZ4 ZIP carriers before delegating to `ZipPackage::archiveExtraDataRecordPreflight()`.
- The wrapper returns the decoded ZIP bytes, decoded package byte size, archive-extra-data record diagnostics, entry metadata, and stream provenance while intentionally omitting package exposure for unsupported archive-extra-data records.
- Extended the WordPress archive-stream preflight example with a gzip-wrapped ZIP containing an archive extra data record before EOCD; the smoke confirms the record is reported and strict `ZipPackage::fromString()` remains blocked.

## Verification

- Baseline before patch: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2744 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2826 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` -> `wordpress-archive-stream-preflight self-test passed`.
- PHP lint:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native `ArchiveCompressionStream` decoders, `ZipPackage::archiveExtraDataRecordPreflight()`, `GzipStream`, PHP deflate wrappers, and `Lz4Frame`. No Pandoc, Cabal, Haskell runner, tar, zip/unzip, `ZipArchive`, LZ4 CLI, Word, LibreOffice, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This slice does not repeat accepted gzip provenance, ZIP descriptor, ZIP64 EOCD, split ZIP, encrypted ZIP, unsupported method, source-name, nested archive, archive-bomb, or LZ4 dictionary behavior. It covers the previously unwrapped stream-level path for ZIP archive extra data records.

## Next

A follow-up archive slice should stay non-overlapping, for example stream-level ZIP64 extra-field review, nested unsupported compression depth diagnostics, or central-directory provenance across compressed carriers.
