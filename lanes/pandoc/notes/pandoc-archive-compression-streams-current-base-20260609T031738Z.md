# Pandoc Archive Compression Streams Current Base 20260609T031738Z

Slice: `pandoc-archive-compression-streams-current-base-20260609T031738Z`

Base accepted HEAD: `fcee36bd5dbe5864d3125594c593630bcda502b2`

## Behavior

- Added `ArchiveCompressionStream::inspectTarEndMarkerPolicy()` for bounded TAR package streams, including gzip-wrapped TAR review packets.
- The policy records TAR block alignment, the first required two-block end-marker offset, end-marker end offset, trailing byte counts, first trailing non-zero byte offsets, trailing-byte hashes/previews, and decoded stream provenance.
- Extra zero padding after the end marker remains `within-thresholds` and metadata-only. Non-zero bytes after the end marker return `review-before-conversion` with `tar-end-marker-review`.
- The policy does not expose decoded `tarBytes`, a `TarArchive` object, or entry payloads. Strict TAR extraction remains blocked for non-zero trailing bytes.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes were present.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3785 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3829 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-tar-end-marker-preflight.php --self-test` passed with `tar end-marker preflight self-test passed`.
- PHP lint passed for `lanes/pandoc/src/ArchiveCompressionStream.php`, `lanes/pandoc/tests/ArchiveCompressionStreamTest.php`, and `lanes/pandoc/examples/wordpress-tar-end-marker-preflight.php`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2215 -> 2216`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2625 -> 2626`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 164`.
- Focused assertions: `3785 -> 3829` for `ArchiveCompressionStreamTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses native `ArchiveCompressionStream`, `TarArchive`, `GzipStream`, TAR end-marker scanning, bounded printable previews, stream inspection, focused PHP tests, and one local WordPress-oriented example.

Pandoc, Cabal/Haskell runners, tar, zip/unzip, LZ4 tools, ZipArchive, Word, LibreOffice, external archive tools, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not repeat gzip member count, byte-limit, source-name, standalone-package boundary, or record-boundary policy; decoded package chunk mapping; TAR sparse, link, special, multivolume, incremental, PAX, attribute, or case-insensitive policy; ZIP descriptor, ZIP64, split archive, archive extra-field, general-purpose flag, encryption, or compression policy; nested archive policy; source-name policy; expansion-ratio policy; or LZ4 content-size, dictionary, skippable-frame, or block-size policy.

This slice owns only metadata-only TAR end-marker offset and trailing-byte provenance before package handoff.

## Next

A useful archive follow-up would be a non-overlapping native package-stream gap such as LZ4 concatenated frame source-boundary policy or ZIP central-directory comment policy.
