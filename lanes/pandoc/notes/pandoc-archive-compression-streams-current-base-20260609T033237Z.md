# Pandoc Archive Compression Streams Current Base 20260609T033237Z

Slice: `pandoc-archive-compression-streams-current-base-20260609T033237Z`

Base accepted HEAD: `5a15a7a63f3c59d035e33a0be022ea134979a702`

## Behavior

- Added `ArchiveCompressionStream::inspectLz4FrameSourceBoundaryPolicy()` for bounded LZ4 TAR/ZIP package streams.
- The policy records LZ4 skippable-frame provenance, data-frame offsets, decoded byte ranges, content-size metadata, and per-frame package summaries without returning decoded package bytes.
- A single package split across multiple LZ4 data frames remains `single-decoded-package-stream` and metadata-only.
- Concatenated LZ4 data frames that each contain standalone TAR/ZIP packages return `review-before-conversion` with `lz4-frame-source-boundary-review`, preventing silent multi-package merge before WordPress import handoff.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes were present.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3961 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 4037 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-lz4-source-boundary-preflight.php --self-test` passed with `lz4 source-boundary preflight self-test passed`.
- PHP lint passed for `lanes/pandoc/src/ArchiveCompressionStream.php`, `lanes/pandoc/tests/ArchiveCompressionStreamTest.php`, and `lanes/pandoc/examples/wordpress-lz4-source-boundary-preflight.php`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2235 -> 2236`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2644 -> 2645`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 196`.
- Focused assertions: `3961 -> 4037` for `ArchiveCompressionStreamTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses native `ArchiveCompressionStream`, `Lz4Frame`, `TarArchive`, `ZipPackage`, bounded stream inspection, focused PHP tests, and one local WordPress-oriented example.

Pandoc, Cabal/Haskell runners, tar, zip/unzip, LZ4 tools, ZipArchive, Word, LibreOffice, external archive tools, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not repeat gzip member count, byte-limit, source-name, standalone-package boundary, or TAR record-boundary policy; decoded package chunk mapping; TAR end-marker, sparse, link, special, multivolume, incremental, PAX, attribute, or case-insensitive policy; ZIP descriptor, ZIP64, split archive, archive extra-field, general-purpose flag, encryption, or compression policy; nested archive policy; source-name policy; expansion-ratio policy; or LZ4 content-size, dictionary, skippable-frame, or block-size policy.

This slice owns only metadata-only LZ4 source-boundary preflight for concatenated package frames before package handoff.

## Next

A useful archive follow-up would be a non-overlapping native package-stream gap such as ZIP central-directory comment policy or LZ4 ZIP frame source-boundary coverage.
