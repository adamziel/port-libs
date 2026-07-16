# Pandoc Archive Compression Streams Current Base 20260609T024952Z

Slice: `pandoc-archive-compression-streams-current-base-20260609T024952Z`

Base accepted HEAD: `f46ebd3f38d4045b46cad3c6483db1eb4cd9e92b`

## Behavior

- Added `ArchiveCompressionStream::inspectGzipTarRecordBoundaryPolicy()` for gzip-wrapped TAR streams.
- The preflight reports gzip member boundaries that split TAR entry records or PAX/GNU metadata records, including decoded split offsets, member labels, record roles, and metadata-only diagnostics.
- Aligned gzip member boundaries remain `within-thresholds`; split records return `review-before-conversion` with `gzip-tar-record-boundary-review`.
- The policy intentionally does not expose decoded TAR bytes, archive objects, or gzip member payload bytes.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes were present.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3605 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3678 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` passed with `wordpress-archive-stream-preflight self-test passed`.
- PHP lint passed for `lanes/pandoc/src/ArchiveCompressionStream.php`, `lanes/pandoc/tests/ArchiveCompressionStreamTest.php`, and `lanes/pandoc/examples/wordpress-archive-stream-preflight.php`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2182 -> 2183`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2596 -> 2597`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 193`.
- Focused assertions: `3605 -> 3678` for `ArchiveCompressionStreamTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses native `ArchiveCompressionStream`, `GzipStream`, `TarArchive`, TAR entry layout metadata, and PAX/GNU metadata layout helpers.

Pandoc, Cabal/Haskell runners, tar, zip/unzip, LZ4 tools, ZipArchive, Word, LibreOffice, external archive tools, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not repeat gzip member count or byte-limit policy, gzip member source-name or standalone-package boundary policy, decoded package chunk mapping, TAR entry or metadata source-segment layout mapping, TAR sparse/link/special/multivolume/incremental/PAX policy, ZIP descriptor/ZIP64/split/archive-extra/general-purpose/encryption policy, nested archive policy, source-name policy, expansion-ratio policy, or LZ4 content-size/dictionary/skippable/block-size policy.

## Next

A useful archive follow-up would be a non-overlapping native package-stream gap such as ZIP central-directory comment policy, LZ4 concatenated frame source-boundary policy, or TAR end-marker/trailing-byte provenance.
