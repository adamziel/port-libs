# Archive Compression Streams Current Base 2026-06-09

Slice: `pandoc-archive-compression-streams-current-base-20260609T124419Z`

Base accepted HEAD: `a38edfb50352ef212fcb62803d82a7ae9bd2908c`

## Behavior

- Added `ArchiveCompressionStream::inspectZipEndOfCentralDirectoryPolicy()` for ZIP bytes carried as plain ZIP, gzip ZIP, zlib ZIP, raw-deflate ZIP, or LZ4 ZIP streams.
- The wrapper decodes the bounded carrier, runs the native raw `ZipPackage::endOfCentralDirectoryTrailingBytesPreflight()` and `ZipPackage::endOfCentralDirectoryOffsetPreflight()` checks, and reports metadata-only review diagnostics without constructing a `ZipPackage` or exposing package entries.
- Focused coverage blocks EOCD trailing bytes and central-directory offset spoofing across all supported ZIP stream carriers, while preserving stream provenance for gzip member metadata and LZ4 skippable reviewer metadata.
- Added `wordpress-zip-eocd-policy-preflight.php` so WordPress import queues can preflight compressed DOCX/EPUB/ODT ZIP uploads for detached tail bytes and central-directory pointer spoofing before archive entry exposure.

## Evidence

- Baseline focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 6132 assertions, 0 failures`
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 6343 assertions, 0 failures`
- Added focused PASS line:
  - `preflights zip eocd trailing bytes and central directory offsets across archive streams`
- Assertion delta:
  - `+211`
- Syntax checks:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-zip-eocd-policy-preflight.php`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-zip-eocd-policy-preflight.php --self-test`
  - Result: `wordpress-zip-eocd-policy-preflight self-test passed`
- Whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `ArchiveCompressionStream`, `ZipPackage` raw EOCD preflights, `GzipStream`, `DeflateStream`, `Lz4Frame`, focused PHP tests, and the new WordPress EOCD preflight example.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`/`unzip`, `tar`, gzip CLI, LZ4 CLI, external archive tool, TeX/PDF engine, browser renderer, external converter, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted archive-compression work for ZIP data descriptors, ZIP64 EOCD or extra fields, Unicode extra fields, central-directory inventory/signatures, archive extra data records, local-header name/metadata/span/order policy, package prefixes, encrypted package flags, general-purpose flags, comments, modification times, creator-host systems, external attributes, platform sidecar metadata, split archives, unsupported compression methods, source-name policy, nested package discovery, archive-bomb ratios, deflate wrapper integrity, zlib/LZ4 dictionary policy, LZ4 skippable/content-size/block-size/source-boundary policy, gzip member boundaries, TAR record boundaries, or TAR sparse/multivolume/incremental/link/special-file metadata.

The owned behavior is only stream-level ZIP EOCD layout policy for trailing bytes and central-directory offsets before package exposure across compressed ZIP carriers.

## Follow-Up

Next archive-compression work should stay non-overlapping. Good candidates are ZIP extra-field structure/id policy wrappers across compressed package carriers or a central-directory repair-plan stream wrapper.
