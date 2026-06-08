# Pandoc Archive Compression Streams Current Base 2026-06-08T20:09:39Z

Slice: `pandoc-archive-compression-streams-current-base-20260608T200939Z`

Base accepted HEAD: `70d557c28daa508cdd36e70149395d52ed3b6a44`

## Behavior

- Added nested metadata preflight for unsupported bzip2, xz, and zstandard compressed package candidates found inside supported TAR/ZIP package streams.
- `ArchiveCompressionStream::inspectNestedPackageStreamsAuto()` now reports `unsupportedCompressionCount` and emits blocked `unsupported-compression` records instead of skipping these candidates or treating them as generic detection failures.
- `ArchiveCompressionStream::inspectNestedArchiveBombPolicyAuto()` now reports `nestedUnsupportedCompressionCount` and adds `nested-package-unsupported-compression` when nested unsupported compressed packages are present.
- Depth-limit candidate summaries now include unsupported compressed package names such as `*.zip.bz2`, so review tooling can see blocked deeper candidates without increasing recursion depth.
- Unsupported records expose only metadata: candidate kind/format, signature provenance, source-name policy, diagnostics, and `unsupported-compression-stream-blocked`; they do not expose decoded package bytes, `TarArchive`, or `ZipPackage` objects.

## Evidence

- Rework note check: current top-level `port-pandoc-*.needs-lane-rework.md` glob returned no active files before editing.
- Red-first check: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` failed after adding the focused case with `1 test files, 2404 assertions, 1 failures`; the new nested unsupported-compression candidate count was `1` instead of `4`.
- Focused test after implementation: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2446 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` -> `wordpress-archive-stream-preflight self-test passed`.
- PHP lint passed for `ArchiveCompressionStream.php`, `ArchiveCompressionStreamTest.php`, and `wordpress-archive-stream-preflight.php`.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `1795 -> 1796`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2215 -> 2216`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 163`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ArchiveCompressionStream`, `TarArchive`, `ZipPackage`, `GzipStream`, and the existing unsupported-compression policy helper.

Pandoc, Cabal/Haskell runners, tar, zip/unzip, lz4, bzip2, xz, zstd, ZipArchive, Word, LibreOffice, external archive tools, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not repeat gzip member provenance, TAR PAX timestamp/hdrcharset policy, ZIP trailing-deflate integrity, ZIP data-descriptor/ZIP64/split/encryption/compression-method policy, source-name mismatch policy, supplied LZ4 dictionary decoding, decoded package chunk source mapping, nested package discovery for supported package streams, or nested archive-bomb ratio policy. It closes the separate gap called out by the prior note: unsupported compressed nested package candidates are now surfaced as blocked metadata records across nested discovery, depth-limit summaries, and archive-bomb preflight.

## Next

A useful archive follow-up would be a non-overlapping native package-stream gap such as compressed ZIP central-directory encryption metadata or additional bounded package handoff metadata that can be tested without invoking external decompressors, archive tools, or office suites.
