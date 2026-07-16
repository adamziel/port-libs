# Pandoc Archive Compression Streams - GZIP Member Metadata Policy

- Micro-slice: `pandoc-archive-compression-streams-current-base-20260609T055102Z`
- Base accepted HEAD: `0f5df40680da5ed9191360998ab90d0db36f1bca`
- Source truth: RFC 1952 gzip member filename/comment fields are optional member metadata. Existing lane `GzipStream` already decodes those fields, and this slice keeps them review-only before WordPress archive handoff.

## Change

- Added `ArchiveCompressionStream::inspectGzipMemberMetadataPolicy()` for gzip-wrapped TAR/ZIP streams.
- Flags unsafe gzip member filename/comment metadata including parent segments, backslashes, absolute paths, drive paths, control bytes, and Unicode format/bidi controls.
- Keeps policy output metadata-only and does not expose decoded package bytes, archive payloads, or tar bytes.
- Added WordPress smoke `wordpress-gzip-member-metadata-preflight.php`.

## Verification

- `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
- `php -l lanes/pandoc/examples/wordpress-gzip-member-metadata-preflight.php`
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 5182 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-gzip-member-metadata-preflight.php --self-test`
  - Result: `gzip member metadata preflight self-test passed`
- `git diff --check -- lanes/pandoc`
- Root harness: not run - isolated micro-slice

## Delta

- +1 focused PHP PASS line.
- +32 focused assertions.
- `phpPass`: 2402 -> 2403.
- Mapped archive/compression stream core cases: 11 -> 12.

## Dependency Closure

No new support component needed. This reuses native PHP `GzipStream`, `ArchiveCompressionStream`, `TarArchive`, the focused lane TestRunner, and a lane-local WordPress smoke. No Pandoc, Haskell runner, Word, LibreOffice, tar, gzip, lz4, zip/unzip, external converter, online service, or live-provider test was run.

## Non-overlap

This does not repeat accepted gzip member/source-boundary policies, member count/byte limits, timestamp/platform/text-hint metadata, zlib/raw-deflate wrapper integrity, LZ4 policies, archive bomb thresholds, TAR sparse/multi-volume/link/special-file policies, or ZIP package extraction policies. It only adds gzip member filename/comment hygiene metadata before package handoff.
