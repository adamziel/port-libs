# pandoc-archive-compression-streams-current-base-20260609T030724Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-archive-compression-streams-current-base-20260609T030724Z`
- Base accepted HEAD: `82ece526c3b1abf329ce3c42e1c2113cbac669aa`
- Bounded behavior: ZIP local-header metadata mismatch preflight across compressed archive stream carriers.

## Behavior

`ArchiveCompressionStream::inspectZipLocalHeaderMetadataPolicy()` now decodes
plain ZIP, gzip-wrapped ZIP, zlib-deflate ZIP, raw-deflate ZIP, and LZ4-framed
ZIP carriers before delegating to the native `ZipPackage::localHeaderMetadataPreflight()`
scanner.

The focused fixture reports method, CRC32, compressed-size, uncompressed-size,
and data-descriptor placeholder mismatches between central-directory metadata
and local headers before strict package exposure. The WordPress archive stream
preflight example now includes a gzip-wrapped DOCX-like ZIP packet whose local
header spoofs method/size/CRC metadata and a descriptor-backed entry whose local
header placeholders are nonzero.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`, `unzip`, `tar`,
`lz4`, `ZipArchive`, external converter, online service, live provider test, or
live-service provider test was executed.

## Verification

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes were present.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 3712 assertions, 0 failures`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 3844 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2204 -> 2205`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2614 -> 2615`
- `archiveCompressionStreamCoreCases`: `11 -> 12`
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`
- `archiveCompressionStreamCoreAssertions`: `120 -> 252`
- Focused `ArchiveCompressionStreamTest.php`: `3712 -> 3844` assertions

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`
local-header metadata preflight, `ArchiveCompressionStream` carrier decoders,
`GzipStream`, `DeflateStream`, `Lz4Frame`, focused archive tests, and the
existing WordPress archive stream preflight example. Full upstream Pandoc runner
parity remains a separate upstream-runner dependency task requiring a hydrated
Pandoc checkout and Haskell test executables.

## Non-Overlap

This does not repeat accepted archive stream coverage for ZIP local-header name
and flag mismatch policy, ZIP general-purpose flag policy, ZIP compression
method policy, ZIP data descriptors, ZIP64 EOCD/extra fields, central-directory
inventory/signature provenance, split ZIP markers, archive extra records,
encrypted ZIP entries, gzip timestamp/platform/member policies, TAR PAX/GNU
metadata policies, LZ4 dictionary/skippable/block-size/content-size policy, or
nested archive expansion policy.

Follow-up archive-compression work should stay bounded and non-overlapping:
ZIP local-header span layout wrappers, EOCD trailing-byte/comment policy,
creator-host/external-attribute stream wrappers, or nested package diagnostics.
