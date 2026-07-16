# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260606T082449Z`

Base accepted HEAD: `e1d03b8cc26cd725291bff3cc15a9b256bfbd961`

## Behavior

- Added bounded gzip member byte-layout provenance for split package streams.
- `GzipStream::inspect()` now records `memberOffset`, `headerSize`,
  `compressedDataOffset`, `trailerOffset`, and `nextMemberOffset` for every
  member while preserving existing CRC, size, header, and padding validation.
- `ArchiveCompressionStream` carries the same member offsets into TAR/ZIP
  stream inspection metadata so review queues can identify exact compressed
  member/header/payload/trailer ranges.
- The WordPress archive preflight smoke now validates the new offset
  provenance for its gzip-wrapped TAR packet.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. RFC1952 gzip streams are concatenations of self-contained members:
variable-length header fields precede the deflate payload, and each member ends
with an 8-byte CRC32/ISIZE trailer. Package review fixtures need those byte
boundaries for split gzip handoff provenance without invoking external tools.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`,
external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool,
browser renderer, online sanitizer, online service, live provider test, or
live-service provider test was executed.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 578 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 575 assertions, 1 failures`.
  - Failure: `memberOffset` was missing from gzip member inspection metadata.
- After implementation:
  - `php -l lanes/pandoc/src/GzipStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 593 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1251 -> 1252`.
- `benchmarkDenominator.mapped`: `1695 -> 1696`.
- Focused archive test grew from `578` to `593` assertions.
- Manifest archive-compression counters record
  `archiveCompressionStreamCoreCases=11`,
  `mappedArchiveCompressionStreamCoreCases=11`, and
  `archiveCompressionStreamCoreAssertions=116`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `GzipStream`,
`ArchiveCompressionStream`, `TarArchive`, the focused PHP test harness, and the
existing WordPress archive preflight example. Full upstream Pandoc runner
parity remains blocked on hydrating the pinned Pandoc checkout and building the
Haskell Tasty executables for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation,
gzip Latin-1/provenance labels, gzip text-hint flags, split-gzip XFL/OS/CRC32
and extra-subfield provenance, raw/zlib DEFLATE provenance, LZ4 frame parsing
or writing, ZIP/OPC package primitives, TAR PAX path/size/owner/access-time/
change-time metadata parsing, PAX deletion records, duplicate PAX keyword
rejection, GNU long-name metadata, GNU long-link rejection, typeflag `7`
contiguous file handling, trailing-slash regular-entry directory normalization,
TAR end-marker validation, TAR drive-letter rejection, base-256 numeric
decoding, TAR sparse/link/device rejection, or generic TAR/ZIP package-kind
detection.

## Follow-Up

Keep nested archive discovery, encrypted archive preflight, sparse-file
reconstruction, hardlink/symlink extraction policy, non-deflate ZIP methods,
dictionary-backed LZ4 frames, and full upstream-runner parity as separate
bounded slices unless concrete package fixtures require them.
