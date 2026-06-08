# Pandoc Archive Compression Streams Current Base

## Scope

Implemented a bounded native TAR filesystem attribute policy preflight for archive-compression handoff:

- `TarArchive::filesystemAttributePolicyPreflight()` scans TAR headers and PAX owner/mode metadata without exposing payload bytes.
- `ArchiveCompressionStream::inspectTarFilesystemAttributePolicy()` applies the preflight to raw, gzip, or LZ4 TAR streams.
- The preflight reports executable, setuid, setgid, sticky, world-writable, and non-root owner metadata as review-only records so WordPress package imports do not apply filesystem attributes.

## Evidence

- Rework notes: no current `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3032 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` failed with `1 test files, 3032 assertions, 1 failures` because `TarArchive::filesystemAttributePolicyPreflight()` was missing.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3072 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` passed with `wordpress-archive-stream-preflight self-test passed`.
- PHP lint passed for `lanes/pandoc/src/TarArchive.php`, `lanes/pandoc/src/ArchiveCompressionStream.php`, `lanes/pandoc/tests/ArchiveCompressionStreamTest.php`, and `lanes/pandoc/examples/wordpress-archive-stream-preflight.php`.
- JSON validation passed for `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and `lanes/pandoc/lane-status.json`.
- Whitespace validation passed with `git diff --check -- lanes/pandoc`.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- `phpPass`: `1978 -> 1979`.
- `benchmarkDenominator.mapped`: `2397 -> 2398`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 160`.

## Non-Overlap

This slice does not repeat the accepted archive-compression support cases for gzip member limits, source-name/package-boundary reporting, LZ4 dictionary/skippable/block-size handling, TAR checksum/link/special/sparse/multivolume/incremental/PAX metadata, ZIP descriptor/ZIP64/split/archive-extra/general-purpose/encryption/source-name policy, or unsupported ZIP compression preflights. It is limited to review-only TAR filesystem attributes before extraction or package import.

## Dependency Closure

No new support component is needed. The patch reuses the lane's native PHP `TarArchive`, `GzipStream`, `Lz4Frame`, and `ArchiveCompressionStream` helpers plus in-memory TAR fixtures. Full upstream Pandoc runner parity remains gated on a hydrated pinned checkout and reviewed non-mutating Cabal plan. This slice did not run Pandoc, Cabal, Haskell runners, tar, gzip, zip/unzip, lz4, ZipArchive, external archive tools, online services, live provider tests, or live-service provider tests.
