# Pandoc Archive Compression Streams Slice 2026-06-06 02:59 UTC

Slice: `pandoc-archive-compression-streams-current-base-20260606T025943Z`

Base accepted HEAD: `218f7be316686ea5b2005dbccc9e20ca989dc733`

## Behavior

- Added bounded TAR stream-layout metadata to `ArchiveCompressionStream`
  inspection results.
- `inspectTarStream()`, `inspectTarStreamAuto()`, and generic package
  inspection now expose:
  - `regularFileCount` and `directoryCount`;
  - `endMarkerOffset` and `trailingZeroBytes`;
  - per-entry `entryLayouts` with header/data offsets, data-end offsets,
    padded data size, record size, mode/owner metadata, timestamp metadata, and
    sorted PAX header keys.
- The metadata is additive and works for plain TAR plus wrapped TAR streams
  after gzip/zlib/raw-deflate/LZ4 decoding. Existing unsafe path, link, sparse,
  checksum, PAX, and end-marker rejection behavior is unchanged.
- Added a narrow WordPress archive stream preflight example that uses a
  gzip-wrapped TAR review packet and verifies the exposed layout metadata.

## Evidence

- Rework notes: no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  file was present for this lane.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  passed with `1 test files, 523 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  passed with `wordpress-archive-stream-preflight self-test passed`.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1167 -> 1168`.
- `benchmarkDenominator.mapped`: `1617 -> 1618`.
- Focused archive compression coverage: `50` PASS cases / `523` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`ArchiveCompressionStream`, `TarArchive`, `GzipStream`, `DeflateStream`,
`Lz4Frame`, and `ZipPackage` support already present in the lane. It does not
require Pandoc, Cabal, Haskell runners, `tar`, `gzip`, `zip`/`unzip`, `lz4`,
`ZipArchive`, Word, LibreOffice, external archive tools, browser renderers,
online sanitizers, online services, or live provider tests.

## Non-Overlap

This does not repeat accepted split-gzip member provenance, gzip Latin-1
filename/comment decoding, gzip FTEXT flags, raw/zlib DEFLATE integrity, TAR
PAX timestamp/owner/path metadata, TAR sparse/link/device rejection, ZIP
central-directory/data-descriptor/trailing-deflate work, LZ4 framing/checksums,
or package auto-detection. It only exposes the previously implicit TAR byte
layout that package preflight needs to explain accepted stream boundaries.

## Follow-Up

Keep nested-archive discovery, encrypted archive preflight, sparse file
reconstruction, hardlink/symlink extraction policy, non-deflate ZIP methods,
and full upstream Pandoc runner parity as separate bounded slices.
