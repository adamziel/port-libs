# Pandoc Archive Compression Streams Current Base 2026-06-09

- Lane: `pandoc`
- Slice: `pandoc-archive-compression-streams-current-base-20260609T040657Z`
- Base accepted HEAD: `39b1c5d5b6751a4cd8edd906dabeef64d6d0fc2e`

## Behavior

Added bounded LZ4 TAR record-boundary preflight for archive review packets:

- `ArchiveCompressionStream::inspectLz4TarRecordBoundaryPolicy()` now reports
  LZ4 data-frame boundaries that split TAR PAX metadata records or TAR entry
  records.
- The policy preserves LZ4 skippable-frame provenance, source frame indexes,
  decoded boundary offsets, split record kinds, and entry/metadata counts
  without returning decoded TAR bytes or package objects.
- The WordPress archive stream preflight example now self-tests a three-frame
  LZ4 TAR review packet with skippable reviewer metadata and split PAX/entry
  record diagnostics.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. The behavior is native PHP package-fixture preflight for LZ4-framed TAR
streams: frame cuts are allowed, but cuts inside TAR records are surfaced for
review before conversion. It does not add a new compression format, extraction
path, filesystem materialization, external archive validation, or upstream
Pandoc runner parity.

No Pandoc, Cabal solver/build/test command, Haskell runner, `tar`, `gzip`,
`lz4`, `zip`, `unzip`, `ZipArchive`, Word, LibreOffice, external archive tool,
online service, live provider test, or live-service provider test was executed.

## Verification

- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 4155 assertions, 0 failures`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 4233 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test coverage grew from `4155` to `4233` assertions.
- `lane-status.json` `phpPass` moves from `2275` to `2276`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from `2677` to `2678`.
- Archive compression stream mapped core cases move from `11` to `12`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `Lz4Frame`,
`ArchiveCompressionStream`, `TarArchive` record-layout metadata, focused
in-memory fixtures, and the existing WordPress archive stream preflight
example. Full upstream Pandoc/Haskell runner parity, external archive-tool
validation, filesystem extraction, bzip2/xz/zstandard decoding, sparse-file
materialization, hardlink/symlink extraction, and recursive nested archive
limits remain separate bounded follow-up tasks.

## Non-Overlap

This does not repeat accepted gzip member framing/provenance, gzip TAR
record-boundary policy, raw/zlib DEFLATE provenance, zlib preset-dictionary
package inspection, LZ4 dictionary decode entrypoints, split LZ4 frame byte
range provenance, LZ4 frame source-boundary package policy, LZ4 content-size
or block-size policy, TAR PAX timestamp/charset/duplicate-key/sparse/
multivolume/incremental/link/special-file policies, nested package discovery,
archive-bomb ratio checks, ZIP package primitives, ZIP64/descriptor/archive
extra-data policy wrappers, or ZIP encryption/compression-method preflights.

## Follow-Up

Keep recursive nested archive depth limits for real fixtures, additional
unsupported-compression source diagnostics, sparse-file reconstruction policy,
hardlink/symlink materialization, filesystem extraction, external archive-tool
validation, and full upstream-runner parity as separate bounded slices.
