# Pandoc Archive Compression Streams Current Base 20260608T134122Z

## Slice

- Lane: pandoc
- Micro-slice: pandoc-archive-compression-streams-current-base-20260608T134122Z
- Accepted base: c09710161ff2cdca8a8469de31dd5d314260fa0c
- Scope: bounded source-name versus detected package-stream policy for archive review.

## Implementation

Added `ArchiveCompressionStream::inspectPackageSourceNamePolicyAuto()`. The policy detects the actual native package stream, compares it with supported source-name hints for TAR, ZIP, gzip/zlib/raw-deflate/LZ4 package streams, and Office/ODF/EPUB ZIP package extensions, then reports review diagnostics for:

- source names that are not recognized as package candidates;
- source names whose package kind differs from detected bytes;
- source names whose expected compression/package format differs from detected bytes.

The policy returns only metadata, entry names, and stream provenance. It does not expose decoded package bytes, `TarArchive`, or `ZipPackage` objects.

The WordPress archive-stream preflight smoke now covers a `.docx` source name whose bytes are actually a gzip-wrapped TAR review packet, producing review-before-conversion diagnostics without invoking external tools.

## Focused Evidence

- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 1897 assertions, 0 failures`
- Red-first focused test after adding the new case:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 1897 assertions, 1 failures`
  - Failure: `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectPackageSourceNamePolicyAuto()`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 1943 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`
- PHP lint:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test coverage grew from `1897` to `1943` assertions.
- `lane-status.json` `phpPass`: `1655 -> 1656`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2075 -> 2076`.
- Archive compression stream mapped core cases: `11 -> 12`.
- Archive compression focused assertion counter: `120 -> 166`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ArchiveCompressionStream`, `GzipStream`, `TarArchive`, `ZipPackage`, the focused archive test, and the existing WordPress archive-stream preflight example. Full upstream Pandoc/Haskell runner parity remains gated separately.

No Pandoc, Cabal solver/build/test command, Haskell runner, `tar`, `zip`, `unzip`, `gzip`, `lz4`, `ZipArchive`, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted gzip FTEXT binary-payload policy, split ZIP markers, ZIP data descriptors, unsupported BZip2/XZ blocking, ZIP compression-method policy, ZIP encryption policy, archive-bomb ratios, nested package discovery, PAX timestamp/hdrcharset/duplicate-key handling, sparse/multi-volume/incremental/link/special-file TAR policies, zlib/LZ4 dictionary streams, split LZ4 frame provenance, or decoded TAR entry source segments. The patch is limited to source-name expectation metadata for already supported native package stream formats.

## Follow-Up

Keep recursive nested archive limit reporting, additional stream-integrity metadata, broader package fixture provenance, filesystem extraction policy, and full upstream-runner parity as separate bounded slices.
