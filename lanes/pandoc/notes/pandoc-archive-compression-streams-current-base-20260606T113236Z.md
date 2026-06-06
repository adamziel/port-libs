# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260606T113236Z`

Base accepted HEAD: `454eb2e80ab750c1392b21e50662320bbde7c428`

## Behavior

- Added bounded TAR sparse-policy preflight without enabling sparse extraction or reconstruction.
- `TarArchive::sparsePolicyPreflight()` now reports GNU typeflag `S`, GNU PAX sparse, and SCHILY sparse entries with sparse families, sparse header keys, optional real size, payload fragment size, byte offsets, and blocked-extraction diagnostics.
- `ArchiveCompressionStream::inspectTarSparsePolicy()` applies the same preflight after bounded plain/gzip/zlib/raw-deflate/LZ4 TAR decoding.
- `TarArchive::fromString()` still rejects sparse entries before exposing package bytes; this slice only gives review queues a structured reason.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support row. TAR sparse entries are not normal package files: GNU tar uses typeflag `S` and PAX keys under `GNU.sparse.*`; Schily/star sparse metadata uses `SCHILY.filetype=sparse` and related `SCHILY.sparse.*` keys. WordPress import/package fixtures need a safe blocked policy with provenance, not sparse reconstruction or filesystem extraction.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`, external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Evidence

- No current-base Pandoc rework note was present in `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 654 assertions, 0 failures`.
- After implementation:
  - `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 684 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1317 -> 1318`.
- `benchmarkDenominator.mapped`: `1731 -> 1732`.
- Focused archive test grew from `654` to `684` assertions.
- Manifest archive-compression counters record `archiveCompressionStreamCoreCases=11`, `mappedArchiveCompressionStreamCoreCases=11`, and `archiveCompressionStreamCoreAssertions=131`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `TarArchive`, `ArchiveCompressionStream`, `GzipStream`, in-memory TAR fixtures, the focused PHP test harness, and the existing WordPress archive stream preflight example. Full upstream Pandoc runner parity remains blocked on hydrating the pinned Pandoc checkout and building Haskell Tasty executables for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation, gzip Latin-1/provenance labels, gzip text-hint flags, gzip member byte-layout offsets, raw/zlib DEFLATE provenance, LZ4 frame parsing or writing, ZIP/OPC package primitives, TAR PAX path/size/owner/access-time/change-time metadata parsing, PAX deletion application, duplicate PAX keyword rejection, GNU long-name parsing, TAR link-policy preflight, GNU long-link rejection in the extraction reader, typeflag `7` contiguous file handling, trailing-slash regular-entry directory normalization, TAR end-marker validation, TAR drive-letter rejection, base-256 numeric decoding, TAR sparse extraction/reconstruction, TAR device rejection, or generic TAR/ZIP package-kind detection.

## Follow-Up

Keep sparse-file reconstruction, nested archive discovery, encrypted archive preflight, actual hardlink/symlink materialization or extraction, non-deflate ZIP methods, dictionary-backed LZ4 frames, and full upstream-runner parity as separate bounded slices unless concrete package fixtures require them.
