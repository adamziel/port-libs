# pandoc-archive-compression-streams-current-base-20260608T223559Z

Base accepted HEAD: `c5c14bd99fa330d27c77e6af2133453dccf48a5a`

## Scope

Implemented bounded native TAR checksum provenance for archive-compressed Pandoc package handoff. The slice adds `TarArchive::checksumPolicyPreflight()` and `ArchiveCompressionStream::inspectTarChecksumPolicy()` so callers can audit TAR header checksum fields before package payload exposure.

The policy reports:

- stored checksum field and octal value;
- unsigned POSIX checksum;
- historic signed checksum;
- matched checksum kind, including ambiguous ASCII-only headers;
- header/data/end offsets and PAX-size-aware payload lengths;
- historic signed checksum diagnostics for review.

The traversal reuses the native TAR reader's safety model for end markers, PAX metadata, GNU long-name/link metadata, checksum validation, UTF-8 path validation, and PAX size overrides. It does not execute `tar`, `gzip`, `zip/unzip`, `lz4`, `ZipArchive`, Pandoc, Cabal/Haskell runners, or external archive tools.

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2937 assertions, 0 failures`
  - Delta: +48 focused assertions inside the existing archive stream test file.
- `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`

Additional verification is recorded in final handoff output.

## Mapping Delta

- `phpPass`: `1928 -> 1929`
- `benchmarkDenominator.mapped`: `2350 -> 2351`
- `archiveCompressionStreamCoreCases`: `11 -> 12`
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`
- `archiveCompressionStreamCoreAssertions`: `120 -> 168`

## Non-Overlap

This slice does not repeat recent archive work for gzip member byte/count/package-boundary policies, gzip source-name policy, zlib preset dictionaries, ZIP data descriptors/general-purpose flags, ZIP64/split/extra data, nested unsupported bzip2/xz/zstd candidates, archive bomb ratios, LZ4 dictionary handoff, or LZ4 split-package byte ranges. It closes the separate TAR checksum provenance follow-up noted by recent archive stream handoffs.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP `TarArchive`, `GzipStream`, and `ArchiveCompressionStream` primitives. Follow-up archive work can stay bounded to LZ4 skippable-frame metadata, stricter LZ4 block-size boundary diagnostics, or filesystem extraction policy.
