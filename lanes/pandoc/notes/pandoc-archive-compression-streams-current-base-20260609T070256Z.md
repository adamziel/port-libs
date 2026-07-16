# Pandoc Archive Compression Streams Current-Base Slice

- Slice: `pandoc-archive-compression-streams-current-base-20260609T070256Z`
- Base accepted HEAD: `53cc273b044292e061f08ae6f6fdabc37210dcb0`
- Scope: bounded native PHP archive/compression support only; no Pandoc, Cabal/Haskell, Word, LibreOffice, zip/unzip, gzip, tar, TeX/PDF engine, browser renderer, external converter, online service, live provider, or live-service provider execution.

## Behavior

This slice adds metadata-only gzip member FHCRC preflight for package-review handoff:

- `GzipStream::headerCrcPolicyPreflight()` inspects gzip member header CRC state without exposing decoded member payloads in the returned member records.
- `ArchiveCompressionStream::inspectGzipHeaderCrcPolicy()` wraps the gzip preflight for `gzip-tar` and `gzip-zip` package streams.
- Tampered FHCRC members produce `gzip-member-header-crc-mismatch` diagnostics and `review-before-conversion` package handoff policy.
- Strict gzip decoding and strict gzip-TAR package opening still reject the mismatched FHCRC before package bytes are exposed.

## Verification

- Baseline before edits: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `1 test files, 5614 assertions, 0 failures`
- Red-first before implementation: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `1 test files, 5614 assertions, 1 failures`
  - Missing method: `PortLibs\Pandoc\ArchiveCompressionStream::inspectGzipHeaderCrcPolicy()`
- Final focused archive stream test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `1 test files, 5660 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-gzip-header-crc-preflight.php --self-test`
  - `gzip header crc preflight self-test passed`
- PHP syntax checks:
  - `php -l lanes/pandoc/src/GzipStream.php`
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-gzip-header-crc-preflight.php`
- Whitespace check: `git diff --check -- lanes/pandoc`
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2467 -> 2468`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2851 -> 2852`
- `archiveCompressionStreamCoreCases`: `11 -> 12`
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`
- `archiveCompressionStreamCoreAssertions`: `120 -> 166`
- Focused archive test assertion delta: `+46`

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP `GzipStream`, `ArchiveCompressionStream`, `TarArchive`, lane TestRunner, and WordPress smoke-example patterns.

## Non-Overlap

This does not repeat accepted gzip member timestamp/platform/text-hint metadata, valid FHCRC provenance, trailer CRC/ISIZE integrity checks, gzip member count and byte limits, gzip TAR/ZIP package boundary policies, decoded chunk streaming, LZ4 frame/skippable/content-size/block-size/source-boundary policies, zlib preset dictionary behavior, deflate-wrapper handling, TAR record/link/sparse/multivolume policies, or ZIP stream policies.

## Follow-Up

Potential non-overlapping follow-ups: zlib Adler mismatch metadata, raw-deflate source-boundary diagnostics, ZIP path/time package review policy, or LZ4 ZIP source-boundary preflight.
