# Pandoc Archive Compression Streams Current Base 2026-06-09T04:20:22Z

Slice: `pandoc-archive-compression-streams-current-base-20260609T042022Z`

Accepted base: `57f750cb0f2a8072346fa230252307d0b08d42b0`

## Behavior

- Added metadata-only source fingerprints to `ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy()` for BZip2, XZ, and Zstandard package streams.
- Unsupported compression policy records now preserve source-name candidate reason/kind/format, signature format, signature/source-name mismatch state, SHA-256 payload fingerprints, and bounded printable previews.
- Nested package and nested archive-bomb unsupported-compression records inherit the same safe source diagnostics while continuing to omit decoded payload bytes, package objects, `tarBytes`, and `zipBytes`.
- The WordPress archive-stream preflight example now self-tests unsupported XZ/Zstandard fingerprints and nested unsupported source hashes without running external decompressors.

## Verification

- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 4340 assertions, 0 failures`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 4386 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`

Root harness not run - isolated micro-slice.

## Status Delta

- Added one PHP PASS line for focused archive-compression source fingerprint coverage.
- Focused archive test coverage grew by 46 assertions.
- `lane-status.json` `phpPass`: `2289 -> 2290`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2690 -> 2691`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 166`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ArchiveCompressionStream` unsupported-compression detection, bounded printable previews, `TarArchive`, `GzipStream`, focused archive tests, and the existing WordPress archive stream preflight example.

No Pandoc, Cabal/Haskell runner, `tar`, `gzip`, `lz4`, `zip`, `unzip`, `ZipArchive`, BZip2/XZ/Zstandard CLI, Word, LibreOffice, TeX/PDF engine, Typst, browser renderer, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted gzip member provenance, gzip/LZ4 TAR record-boundary policy, LZ4 skippable/source-boundary/content-size/block-size policy, zlib/LZ4 dictionary decoding, TAR sparse/multivolume/incremental/link/special-file policies, nested package discovery, nested archive-bomb ratios, ZIP descriptor/ZIP64/split/encryption/compression-method wrappers, ZIP archive-extra-data wrappers, ZIP central-directory order wrappers, or LZ4 record-boundary diagnostics. It only adds safe source fingerprint metadata for already-blocked unsupported compression streams.

## Follow-Up

Useful archive follow-ups remain recursive nested archive limits for real fixtures, additional stream-level ZIP policy wrappers not already covered, or bounded archive provenance that does not decode unsupported compressors or invoke external archive tools.
