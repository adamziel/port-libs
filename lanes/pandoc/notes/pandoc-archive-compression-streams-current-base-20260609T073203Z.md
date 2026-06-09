# Pandoc Archive Compression Streams Current-Base Slice

- Slice: `pandoc-archive-compression-streams-current-base-20260609T073203Z`
- Base accepted HEAD: `df259aa2eedc94083122c4983a2ea922c64e663c`
- Scope: bounded native PHP archive/compression support only; no Pandoc, Cabal/Haskell, Word, LibreOffice, zip/unzip, gzip, tar, external template engines, TeX/PDF engines, browser renderers, external converters, online services, live provider tests, live-service provider tests, or ZipArchive execution.

## Behavior

This slice adds metadata-only zlib Adler-32 integrity preflight for compressed package handoff:

- `DeflateStream::adler32IntegrityPreflight()` decodes the bounded zlib DEFLATE payload only to compute Adler-32 and compare it with the stored trailer.
- `ArchiveCompressionStream::inspectZlibAdler32IntegrityPolicy()` wraps the preflight for `zlib-tar` and `zlib-zip` package streams.
- Corrupt trailers produce `zlib-adler32-mismatch` diagnostics and `review-before-conversion` handoff policy without returning decoded TAR/ZIP bytes.
- Strict `DeflateStream::decode()` and strict zlib-TAR package opening still reject corrupt Adler-32 trailers before package handoff.
- `wordpress-zlib-adler32-integrity-preflight.php` covers the WordPress review-queue smoke path.

## Verification

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes were present.
- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 5838 assertions, 0 failures`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 5893 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-zlib-adler32-integrity-preflight.php --self-test`
  - Result: `zlib adler32 integrity preflight self-test passed`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2497 -> 2498`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2875 -> 2876`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 175`.
- Focused `ArchiveCompressionStreamTest.php`: `5838 -> 5893` assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `DeflateStream`, `ArchiveCompressionStream`, `TarArchive`, `ZipPackage`, the lane TestRunner, and the existing WordPress smoke-example pattern. Full upstream Pandoc/Haskell runner parity remains a separate upstream-runner dependency task requiring a hydrated pinned Pandoc checkout and Haskell test executables.

## Non-Overlap

This does not repeat accepted gzip header CRC, gzip trailer CRC/ISIZE, gzip member metadata/count/byte/package-boundary policies, LZ4 source-boundary/data-frame/content-size/block-size/dictionary policies, zlib preset-dictionary package inspection, raw-deflate missing-integrity classification, TAR sparse/multivolume/incremental/link policies, nested package discovery, archive-bomb ratio checks, ZIP central/local/data-descriptor/compression-method policies, unsupported bzip2/xz/zstd stream blocking, or ZIP raw central-directory name-collision preflight. It owns only zlib Adler-32 trailer integrity review for compressed TAR/ZIP package streams.

## Follow-Up

Potential non-overlapping follow-ups: raw-deflate source-boundary diagnostics, zlib dictionary mismatch policy with supplied dictionaries, or compressed ZIP/TAR source provenance mapping.
