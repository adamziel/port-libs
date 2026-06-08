# Pandoc Archive Compression Streams Current Base 20260608T173419Z

## Slice

- Lane: pandoc
- Micro-slice: pandoc-archive-compression-streams-current-base-20260608T173419Z
- Accepted base: 1a3a20a4d97a813d29b077097195ea208a489f6a
- Scope: bounded nested archive recursion-depth reporting for metadata-only package preflight.

## Implementation

`ArchiveCompressionStream::inspectNestedPackageStreamsAuto()` now reports when a nested package at the configured recursion boundary still contains deeper archive-looking entries by name. The inspection result includes aggregate `depthLimitReachedCount` and `depthLimitedCandidateCount` fields, and each package entry now carries `depthLimitReached`, `depthLimitedCandidateCount`, `depthLimitedCandidateNames`, and `depthLimitedCandidates` metadata.

The depth-limit scan only uses already parsed TAR/ZIP entry metadata at the boundary. It does not read or decode those deeper payloads, extract files, shell out to archive tools, or invoke Pandoc.

The WordPress archive-stream preflight example now self-tests both the existing max-depth-two nested inspection and a max-depth-one inspection that reports an inner DOCX candidate hidden behind the recursion limit.

## Focused Evidence

- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2096 assertions, 0 failures`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2111 assertions, 0 failures`
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
- Focused archive test coverage grew from `2096` to `2111` assertions.
- `lane-status.json` `phpPass`: `1701 -> 1702`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2121 -> 2122`.
- Archive compression stream mapped core cases: `11 -> 12`.
- Archive compression focused assertion counter: `120 -> 135`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ArchiveCompressionStream`, `GzipStream`, `TarArchive`, `ZipPackage`, the focused archive test, and the existing WordPress archive-stream preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, `tar`, `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted source-name policy, gzip FTEXT binary-payload policy, split ZIP markers, ZIP data descriptors, unsupported BZip2/XZ blocking, ZIP compression-method policy, ZIP encryption policy, archive-bomb ratios, basic nested package discovery, PAX timestamp/hdrcharset/duplicate-key handling, sparse/multi-volume/incremental/link/special-file TAR policies, zlib/LZ4 dictionary streams, split LZ4 frame provenance, or decoded TAR entry source segments. The patch is limited to recursion-limit metadata for already detected nested packages.

## Follow-Up

Keep additional stream-integrity metadata, source byte-range provenance for nested entries, extraction policy, and full upstream-runner parity as separate bounded slices.
