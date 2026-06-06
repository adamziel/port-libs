# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260606T134451Z`
Base accepted HEAD: `26b7b921df7789392f128f64b5b25be57013fd35`

## Behavior

- Added bounded TAR header checksum compatibility for historic signed-byte checksum records.
- `TarArchive::fromString()` now accepts a header when either the POSIX unsigned checksum or the historic signed-byte checksum matches the header bytes with the checksum field treated as spaces.
- Corrupted headers still fail checksum validation; the focused fixture mutates the mode field after writing a signed checksum and verifies rejection.
- The WordPress archive preflight smoke now exercises a gzip-wrapped TAR review packet with a UTF-8 path and signed checksum provenance.

## Source Truth

The support row remains archive/package fixture handling, not external runner work. GNU tar's format documentation records the POSIX checksum rule as an unsigned sum over header bytes with the checksum field treated as spaces. Commons Compress `TarUtils` documents the compatibility reason for also checking signed-byte sums: some historic tar implementations treated header bytes as signed. This slice ports that compatibility boundary without invoking `tar` or any external archive tool.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`, external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Verification

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 684 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 684 assertions, 1 failures`.
  - Failure: `TAR header checksum does not match header bytes` for the new signed-checksum fixture.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 692 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test grew from `684` to `692` assertions.
- `lane-status.json` `phpPass` moves from `1336` to `1337`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moves from `1750` to `1751`; archive-compression counters move to `11` mapped support cases and `109` focused support assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `TarArchive`, `ArchiveCompressionStream`, `GzipStream`, the focused PHP test harness, and the existing WordPress archive preflight example. Full upstream Pandoc runner parity remains blocked on hydrated upstream checkout and Haskell Tasty runner execution, which is intentionally out of scope for this slice.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation, gzip Latin-1/provenance labels, gzip text-hint flags, split-gzip XFL/OS/CRC32 and byte-layout provenance, raw/zlib DEFLATE provenance, LZ4 frame parsing or writing, ZIP/OPC package primitives, TAR PAX path/size/owner/access/change timestamp parsing, PAX deletion application, duplicate PAX keyword rejection, GNU long-name parsing, TAR link-policy or sparse-policy preflight, GNU long-link rejection, typeflag `7` contiguous file handling, trailing-slash regular-entry directory normalization, TAR end-marker validation, TAR drive-letter rejection, base-256 numeric decoding, TAR device rejection, or generic TAR/ZIP package-kind detection.

## Follow-Up

Keep encrypted archive preflight, nested archive discovery, sparse-file reconstruction, hardlink/symlink materialization, non-deflate ZIP methods, dictionary-backed LZ4 frames, and full upstream-runner parity as separate bounded slices unless concrete package fixtures require them.
