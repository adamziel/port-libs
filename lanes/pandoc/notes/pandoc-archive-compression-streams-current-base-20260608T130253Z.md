# Pandoc Archive Compression Streams Current Base 20260608T130253Z

## Slice

- Lane: pandoc
- Micro-slice: pandoc-archive-compression-streams-current-base-20260608T130253Z
- Accepted base: 1ea9ca5bf68d5249e21c102f28ab7d021c8d674e
- Scope: bounded gzip FTEXT review policy for archive package streams.

## Implementation

Added `ArchiveCompressionStream::inspectGzipTextHintPolicy()` for gzip-wrapped TAR and ZIP streams. The policy inspects gzip member metadata and a bounded decoded payload probe, flags FTEXT members whose payload probe looks binary, and returns `review-before-conversion` diagnostics without exposing decoded package bytes or changing normal TAR/ZIP extraction behavior.

The WordPress archive-stream preflight smoke now reports the gzip text-hint handoff policy, binary text-hint member count, member filename, and diagnostics.

## Focused Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 1859 assertions, 0 failures`.
- Red-first: the same focused test failed with `1 test files, 1859 assertions, 1 failures` because `inspectGzipTextHintPolicy()` was missing.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 1897 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` passed.

## Non-Overlap

This slice does not duplicate split ZIP markers, ZIP data descriptors, unsupported bzip2/xz stream policy, PAX timestamp/hdrcharset handling, LZ4 dictionary/split frame behavior, zlib preset dictionary policy, gzip header CRC provenance, gzip Latin-1 metadata, gzip FTEXT flag exposure, or archive-bomb ratio policy. It adds the missing review decision for an already-exposed gzip FTEXT flag when the decoded package payload is binary-looking.

## Dependency Closure

No new support component is needed. The patch reuses `ArchiveCompressionStream`, `GzipStream`, `TarArchive`, `ZipPackage`, the archive focused test, and the existing WordPress archive-stream preflight example. No Pandoc, Cabal solver/build/test command, Haskell runner, tar, zip/unzip, lz4, ZipArchive, external archive tool, online service, live provider test, or live-service provider test was executed.
