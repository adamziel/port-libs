# Pandoc Archive Compression Streams Current Base 2026-06-08

- Lane: `pandoc`
- Slice: `pandoc-archive-compression-streams-current-base-20260608T073746Z`
- Base accepted HEAD: `cc8aff0c7fc799cc89f962c19a87ba076dddaa29`

## Behavior

Added bounded split LZ4 package frame-range provenance for archive review
packets:

- `Lz4Frame::frames()` and `framesWithDictionaries()` now expose
  `frameOffset`, `nextFrameOffset`, `decodedDataOffset`, and
  `decodedDataEndOffset` metadata for skippable and data frames.
- `ArchiveCompressionStream` carries those offsets into plain and
  dictionary-backed LZ4 package stream summaries.
- The focused test covers a dictionary-backed TAR review packet split across two
  LZ4 data frames, preserving package parsing while reporting the exact decoded
  byte ranges contributed by each frame.
- The WordPress archive preflight example now self-tests the same split LZ4
  dictionary-backed TAR handoff and missing-dictionary fail-closed path.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. The behavior is a native PHP package-fixture preflight contract for LZ4
frame streams: split frames must concatenate into the package bytes, and review
metadata must identify which source frame produced each decoded byte range. It
does not implement new compression formats, filesystem extraction, external
archive validation, or full upstream Pandoc runner parity.

No Pandoc, Cabal solver/build/test command, Haskell runner, `tar`, `gzip`,
`zip`, `unzip`, `lz4`, `ZipArchive`, Word, LibreOffice, external archive tool,
online service, live provider test, or live-service provider test was executed.

## Verification

- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 1547 assertions, 0 failures`.
- Red-first focused test after adding the new expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 1561 assertions, 1 failures`.
  - Failure: expected split LZ4 `frameOffset` metadata was `NULL`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 1583 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test coverage grew from `1547` to `1583` assertions.
- `lane-status.json` `phpPass` moves from `1564` to `1565`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from `1985` to `1986`.
- Archive compression stream mapped core cases move from `11` to `12`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `Lz4Frame`,
`ArchiveCompressionStream`, `TarArchive`, focused in-memory fixtures, and the
existing WordPress archive stream preflight example. Full upstream Pandoc/Haskell
runner parity, external archive-tool validation, filesystem extraction,
bzip2/xz support or diagnostics, and broader TAR/ZIP policy work remain separate
bounded follow-up tasks.

## Non-Overlap

This does not repeat accepted gzip member framing/provenance, raw/zlib DEFLATE
provenance, zlib preset-dictionary package inspection, LZ4 dictionary decode
entrypoints, single-frame dictionary-backed LZ4 package inspection, TAR PAX
timestamp/charset/duplicate-key/sparse/multivolume/incremental/link/special-file
policies, nested package discovery, archive-bomb ratio checks, ZIP package
primitives, or ZIP encryption/compression-method preflights. The patch is
limited to LZ4 frame byte-range provenance for split package streams.

## Follow-Up

Keep unsupported-compression diagnostics, additional LZ4 frame provenance for
real fixtures, recursive nested archive limits, sparse-file reconstruction,
hardlink/symlink materialization, filesystem extraction, external archive-tool
validation, and full upstream-runner parity as separate bounded slices.
