# Pandoc Archive Compression Streams Current Base 20260608T221719Z

Lane: `pandoc`

Micro-slice: `pandoc-archive-compression-streams-current-base-20260608T221719Z`

Base accepted HEAD: `238c756134d68ede9072631361599c436a2f8d32`

## Behavior

Added `ArchiveCompressionStream::inspectGzipMemberByteLimitPolicy()` for
gzip-wrapped TAR and ZIP package streams.

The policy reports a metadata-only per-member decoded byte threshold before
conversion handoff. It preserves member filename/comment provenance, decoded
offsets, member sizes, aggregate over-limit counts, the first over-limit member
index, the largest decoded member size, and review diagnostics. It intentionally
does not expose decoded member `data`, `tarBytes`, `zipBytes`, `archive`, or
`package` objects.

The WordPress archive preflight example now covers a split gzip/TAR upload where
one decoded member exceeds the configured threshold and is marked
`review-before-conversion`.

## Verification

- Rework-note check: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2856 assertions, 0 failures`.
- Red-first focused test after adding the new case:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2856 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectGzipMemberByteLimitPolicy()`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2889 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test coverage grew from `2856` to `2889` assertions.
- `lane-status.json` `phpPass`: `1912 -> 1913`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2335 -> 2336`.
- Archive compression mapped core cases: `11 -> 12`.
- Archive compression focused assertion counter: `120 -> 153`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`ArchiveCompressionStream`, `GzipStream`, `TarArchive`, the focused archive
test, and the existing WordPress archive-stream preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, `tar`, external
`gzip`, `zip`, `unzip`, `lz4`, ZipArchive, Word, LibreOffice, external archive
tool, online service, live provider test, or live-service provider test was
executed.

## Non-Overlap

This does not repeat accepted gzip member count policy, gzip package-boundary
policy, gzip source-name policy, gzip FTEXT binary-payload policy, gzip member
byte-layout provenance, decoded package chunk mapping, ZIP descriptor/ZIP64/
split/encryption/general-purpose/compression-method policies, unsupported
BZip2/XZ/Zstandard blocking, archive-bomb ratios, nested package discovery,
TAR PAX timestamp/hdrcharset/duplicate-key handling, sparse/multi-volume/
incremental/link/special-file TAR policies, zlib/LZ4 dictionary streams, split
LZ4 frame provenance, or supplied LZ4 dictionary decode. This patch is limited
to decoded-size threshold diagnostics for individual gzip members.

## Follow-Up

Useful non-overlapping archive follow-ups remain TAR checksum provenance, LZ4
skippable-frame metadata, stricter LZ4 block-size boundary diagnostics,
filesystem extraction policy, and full upstream Pandoc runner parity.
