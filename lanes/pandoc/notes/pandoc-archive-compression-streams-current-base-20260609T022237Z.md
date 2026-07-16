# Pandoc Archive Compression Streams Current Base 20260609T022237Z

## Behavior Target

LZ4-framed archive handoff now has a metadata-only declared content-size policy. `Lz4Frame::decode()` remains strict and rejects frames whose declared content size does not match decoded bytes, while `ArchiveCompressionStream::inspectLz4ContentSizePolicy()` returns review metadata with declared size, decoded size, delta, frame offsets, and skippable-frame provenance without exposing package payload bytes.

This gives WordPress archive review queues an explicit `review-before-conversion` reason for suspicious LZ4 package streams before tar/zip package handoff.

## Source Truth And Non-Overlap

This is bounded native PHP archive/compression support for the LZ4 frame content-size descriptor path. It does not run Pandoc, Cabal/Haskell runners, tar, zip/unzip, lz4, ZipArchive, Word, LibreOffice, external archive tools, online services, live provider tests, or live-service provider tests.

The slice avoids overlapping accepted archive work for gzip member framing/provenance, gzip member limits, source-name/package-boundary reporting, LZ4 dictionary handling, LZ4 skippable-frame payload limits, LZ4 block-size limits, nested archive policy, source-name policy, expansion-ratio policy, TAR checksum/link/special/sparse/multivolume/incremental/PAX metadata, and ZIP descriptor/ZIP64/split/archive-extra/general-purpose/encryption/source-name policy.

## Verification Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes were present.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3532 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3583 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-lz4-content-size-preflight.php --self-test` passed with `lz4 content-size preflight self-test passed`.
- PHP lint passed for `lanes/pandoc/src/ArchiveCompressionStream.php`, `lanes/pandoc/src/Lz4Frame.php`, `lanes/pandoc/tests/ArchiveCompressionStreamTest.php`, and `lanes/pandoc/examples/wordpress-lz4-content-size-preflight.php`.
- JSON validation passed for `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and `lanes/pandoc/lane-status.json`.
- Diff check: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2130 -> 2131`.
- `benchmarkDenominator.mapped`: `2557 -> 2558`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 171`.
- Focused assertions: `3532 -> 3583` for `ArchiveCompressionStreamTest.php`.

## Dependency Closure

No new support component is needed. The patch reuses native `Lz4Frame`, `ArchiveCompressionStream`, `TarArchive`, focused archive tests, and a lane-local WordPress archive example. Full upstream Pandoc runner parity remains gated on hydrated Haskell test executables and is outside this archive-stream micro-slice.

## Next

Archive/compression follow-up should choose a non-overlapping native gap such as LZ4 frame concatenation source segments, TAR metadata-record source segment layout, ZIP central-directory comment policy, or nested package policy refinement.
