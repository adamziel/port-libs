# Pandoc Archive Compression Streams Current Base 2026-06-08T23:22:00Z

Slice: `pandoc-archive-compression-streams-current-base-20260608T232200Z`

Base accepted HEAD: `ec0c427f794b6434487ca723f84cfea12ace0acb`

## Behavior

- Added `ArchiveCompressionStream::inspectLz4BlockSizePolicy()` for metadata-only LZ4 frame block-size review.
- `Lz4Frame::dictionaryPolicyPreflight()` now records per-block payload sizes and the largest observed block payload size without decoding or exposing package bytes.
- The policy reports declared frame block maxima, observed block payload sizes, first over-limit frame indexes, stream-level diagnostics, and per-block review diagnostics.
- Skippable LZ4 reviewer frames are retained only as bounded metadata: size, SHA-256, printable preview, frame offset, and frame size.
- The WordPress archive stream preflight example now self-tests the same LZ4 block-size policy handoff path.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed for this lane.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2990 assertions, 0 failures`.
- Red-first focused test after adding the new expectation: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2990 assertions, 1 failures`.
- Red-first failure: `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectLz4BlockSizePolicy()`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 3032 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` -> `wordpress-archive-stream-preflight self-test passed`.
- PHP lint:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php` -> no syntax errors.
  - `php -l lanes/pandoc/src/Lz4Frame.php` -> no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php` -> no syntax errors.
- JSON validation: `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decoded successfully.
- Whitespace check: `git diff --check -- lanes/pandoc` -> no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test coverage grew from `2990` to `3032` assertions, adding `42` assertions.
- `lane-status.json` `phpPass`: `1964 -> 1965`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2385 -> 2386`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 162`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `Lz4Frame`,
`ArchiveCompressionStream`, focused in-memory TAR/LZ4 fixtures, and the
existing WordPress archive stream preflight example.

Pandoc, Cabal/Haskell runners, `tar`, `gzip`, `zip`/`unzip`, `lz4`,
`ZipArchive`, Word, LibreOffice, external archive tools, online services, live
provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not repeat ZIP package creator-host policy, ZIP data-descriptor
provenance, ZIP64 descriptor integrity, split ZIP disk markers, unsupported
compression stream policy, source-name mismatch policy, supplied LZ4 dictionary
decoding, split LZ4 frame byte-range provenance, gzip member-count/byte-limit
policy, TAR checksum/PAX timestamp/hdrcharset policy, nested package discovery,
archive-bomb ratio checks, or LZ4 skippable-frame payload threshold policy.
The patch is limited to metadata-only review policy for declared and observed
LZ4 block-size thresholds.

## Next

A useful non-overlapping archive follow-up would be filesystem extraction
policy metadata or compressed ZIP central-directory encryption metadata without
invoking external archive tools.
