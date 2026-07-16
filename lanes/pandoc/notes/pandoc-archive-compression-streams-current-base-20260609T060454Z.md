# Pandoc Archive Compression Streams Current Base 2026-06-09

- Lane: `pandoc`
- Slice: `pandoc-archive-compression-streams-current-base-20260609T060454Z`
- Base accepted HEAD: `11b5789183ebb8ab34ff922479caf161e9cc4881`

## Behavior

Added bounded LZ4 data-frame threshold preflight for package streams:

- `ArchiveCompressionStream::inspectLz4DataFrameLimitPolicy()` accepts only
  LZ4-wrapped TAR or ZIP package formats.
- The policy reports total frame counts, data-frame counts, skippable-frame
  counts, per-frame decoded byte sizes, decoded offsets, source offsets,
  content-size metadata, package status, and entry names.
- Over-limit data-frame counts and over-limit decoded frame sizes are flagged
  before WordPress package handoff.
- Returned frame records intentionally omit decoded package payload bytes.
- The WordPress smoke covers a split LZ4 TAR review packet with an oversized
  decoded frame and an extra data frame.

This ports a native PHP package-fixture review contract for compressed Pandoc
support inputs. It does not implement filesystem extraction, external archive
validation, bzip2/xz/zstd decoding, or full upstream Pandoc runner parity.

No Pandoc, Cabal solver/build/test command, Haskell runner, `tar`, `gzip`,
`zip`, `unzip`, `lz4`, `ZipArchive`, Word, LibreOffice, external archive tool,
external converter, online service, live provider test, or live-service
provider test was executed.

## Verification

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes were
  present.
- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 5182 assertions, 0 failures`.
- Red-first focused test after adding the new expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 5182 assertions, 1 failures`.
  - Failure: missing `ArchiveCompressionStream::inspectLz4DataFrameLimitPolicy()`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 5266 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-lz4-frame-limit-preflight.php --self-test`
  - Result: `lz4 frame-limit preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2418 -> 2419`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2807 -> 2808`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 204`.
- Focused `ArchiveCompressionStreamTest.php`: `5182 -> 5266` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `Lz4Frame`,
`ArchiveCompressionStream`, `TarArchive`, `ZipPackage`, focused in-memory
fixtures, the PHP TestRunner, and a WordPress archive preflight smoke. Full
upstream Pandoc/Haskell runner parity remains a separate upstream-runner
dependency task requiring a hydrated Pandoc checkout and Haskell test
executables.

## Non-Overlap

This does not repeat accepted LZ4 dictionary decode entrypoints, dictionary
package inspection, skippable-frame byte limits, block-size policy,
content-size mismatch policy, source-boundary policy, TAR record-boundary
policy, split LZ4 frame byte-range provenance, gzip member count/byte limits,
gzip member package-boundary checks, gzip TAR record-boundary checks, zlib
preset-dictionary package inspection, TAR sparse/multivolume/incremental/link
policies, nested package discovery, archive-bomb ratio checks, ZIP local-header
metadata policies, ZIP compression method preflights, or unsupported bzip2/xz/
zstd stream blocking.

## Follow-Up

Keep ZIP EOCD trailing-byte/comment policy, ZIP local-header span layout
follow-up, unsupported-compression diagnostics, filesystem extraction, external
archive-tool validation, and full upstream-runner parity as separate bounded
slices.
