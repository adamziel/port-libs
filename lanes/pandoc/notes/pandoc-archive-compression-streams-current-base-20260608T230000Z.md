# Pandoc Archive Compression Streams Current Base 2026-06-08T23:00:00Z

Slice: `pandoc-archive-compression-streams-current-base-20260608T230000Z`

Base accepted HEAD: `d8ca989a03aa98e6028adc24e3edc39bb34ec9a6`

## Behavior

- Added `ArchiveCompressionStream::inspectLz4SkippableFramePolicy()` for bounded LZ4 skippable-frame review metadata.
- The preflight reports total skippable payload bytes, per-frame payload size, SHA-256, bounded printable preview, frame offsets/sizes, data-frame summaries, and threshold diagnostics.
- Full skippable payload bytes are intentionally not exposed in the archive-level inspection result.
- The WordPress archive stream preflight example now self-tests the same metadata-only skippable-frame policy path.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed for this lane.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2937 assertions, 0 failures`.
- Red-first focused test after adding the new expectation: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2937 assertions, 1 failures`.
- Red-first failure: `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectLz4SkippableFramePolicy()`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2990 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` -> `wordpress-archive-stream-preflight self-test passed`.
- PHP lint:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php` -> no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php` -> no syntax errors.
- JSON validation: `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decoded successfully.
- Whitespace check: `git diff --check -- lanes/pandoc` -> no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test coverage grew from `2937` to `2990` assertions, adding `53` assertions.
- `lane-status.json` `phpPass`: `1950 -> 1951`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2370 -> 2371`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 173`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `Lz4Frame`,
`ArchiveCompressionStream`, focused in-memory LZ4 fixtures, and the existing
WordPress archive stream preflight example.

Pandoc, Cabal/Haskell runners, `tar`, `gzip`, `zip`/`unzip`, `lz4`,
`ZipArchive`, Word, LibreOffice, external archive tools, online services, live
provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not repeat ZIP package creator-host policy, ZIP data-descriptor
provenance, ZIP64 descriptor integrity, split ZIP disk markers, unsupported
compression stream policy, source-name mismatch policy, supplied LZ4 dictionary
decoding, split LZ4 frame byte-range provenance, gzip member-count/byte-limit
policy, TAR checksum/PAX timestamp/hdrcharset policy, nested package discovery,
or archive-bomb ratio checks. The patch is limited to metadata-only review
policy for LZ4 skippable frames and their payload-size thresholds.

## Next

A useful non-overlapping archive follow-up would be stricter LZ4 block-size
boundary diagnostics, filesystem extraction policy metadata, or compressed ZIP
central-directory encryption metadata without invoking external archive tools.
