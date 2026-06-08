# Pandoc Archive Compression Streams Current Base 2026-06-08T19:51:26Z

Slice: `pandoc-archive-compression-streams-current-base-20260608T195126Z`

Base accepted HEAD: `9dd50e42d08f90606df6625de393150c7e5b551f`

## Behavior

- Added `ArchiveCompressionStream::inspectNestedArchiveBombPolicyAuto()` for recursive TAR/ZIP package candidates inside bounded compressed package streams.
- The preflight reports root and nested package expansion ratios, stream/package/total threshold diagnostics, depth-limit diagnostics, unreadable nested packages, and metadata-only handoff policy.
- Nested package bytes and package objects are intentionally not exposed in returned records; the handoff remains review metadata only.
- Source truth is the bounded support-library contract for archive/compression helpers needed by package fixtures: detect real nested package containers, inspect expansion policy before conversion handoff, and avoid external archive tools.

## Evidence

- Red-first check: the new focused test failed before implementation with `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectNestedArchiveBombPolicyAuto()`.
- Focused test after implementation: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2239 assertions, 0 failures`.
- Previous focused baseline for this file was `1 test files, 2201 assertions, 0 failures`; this slice adds 38 focused assertions and one PASS case.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` -> `wordpress-archive-stream-preflight self-test passed`.
- PHP lint passed for `ArchiveCompressionStream.php`, `ArchiveCompressionStreamTest.php`, and `wordpress-archive-stream-preflight.php`.

## Status Delta

- `lane-status.json` `phpPass`: `1759 -> 1760`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2176 -> 2177`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 158`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `ArchiveCompressionStream`, `TarArchive`, `ZipPackage`, `GzipStream`, bounded deflate/LZ4 helpers, nested package detection, and expansion-ratio policy helpers.

Pandoc, Cabal/Haskell runners, tar, zip/unzip, lz4, ZipArchive, Word, LibreOffice, external archive tools, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not repeat ZIP data-descriptor provenance, ZIP64 descriptor integrity, split ZIP disk markers, unsupported compression stream policy, source-name mismatch policy, supplied LZ4 dictionary decoding, decoded package chunk source mapping, PAX timestamp/hdrcharset policy, or the existing nested package discovery report. It adds a separate recursive archive-bomb policy preflight for nested compressed TAR/ZIP package candidates.

## Next

A useful archive follow-up would be a non-overlapping native package-stream gap such as nested depth-limit diagnostics across unsupported compression candidates, compressed ZIP central-directory encryption metadata, or another TAR PAX policy edge that can be tested without invoking external archive tools.
