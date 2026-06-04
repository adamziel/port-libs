# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260604T212315Z`

Base accepted HEAD: `45692a207c18e5a850e689e675ab210e5115185b`

## Implementation

- Added `Lz4Frame` as a bounded native PHP LZ4 frame helper for package
  fixture streams.
- `Lz4Frame::build()` emits independent-block LZ4 frames with configurable
  block size, optional content-size metadata, optional block checksums, and
  optional content checksums. Blocks are compressed when the bounded raw LZ4
  encoder saves bytes and otherwise stored as uncompressed LZ4 frame blocks.
- `Lz4Frame::frames()` and `Lz4Frame::decode()` parse concatenated streams,
  preserve skippable frame metadata, validate the LZ4 frame header checksum,
  validate block/content checksums, enforce content-size and caller-supplied
  uncompressed-byte limits, decode compressed raw blocks, and reject dependent
  block or dictionary-backed frames before exposing bytes to package readers.
- The WordPress ZIP/package preflight example now also verifies an LZ4-framed
  tar review packet with skippable reviewer metadata, without invoking `lz4`,
  `tar`, `gzip`, `zip`, or `unzip`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. LZ4 frame handling is a bounded stream primitive for compressed package
fixtures and import handoff bundles, not a general archive extraction stack.
The implementation uses PHP's native `hash` support for `xxh32` checksums and
does not call Pandoc, Cabal, Haskell runners, office tools, external archive
binaries, TeX/PDF engines, browser renderers, or online services.

## Evidence

- `php -l lanes/pandoc/src/Lz4Frame.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 66 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `2 test files, 232 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `12 test files, 3569 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `375 -> 378`.
- `benchmarkDenominator.mapped`: `832 -> 835`.
- `archiveCompressionStreamCoreCases`: `7 -> 10`.
- `archiveCompressionStreamCoreAssertions`: `68 -> 101`.

## Dependency Closure

No new external support component is needed. This activates the already-listed
bounded LZ4 frame follow-up inside the archive-compression support row and
reuses native PHP byte handling plus `hash('xxh32')` for frame checksums.
Dependent-block frames, dictionary-backed frames, filesystem extraction policy,
and cross-lane Syncthing BEP compression details remain out of scope.

## Non-Overlap

This does not repeat accepted gzip member framing, POSIX tar file/directory
handling, PAX long-path metadata, ZIP central-directory parsing/writing, ZIP
extra-field parsing, local-header checks, data-descriptor handling, OPC
relationship/content-type parsing, DOCX/ODT package readers, doctemplates,
YAML metadata, CSL/citation handling, table geometry, math/TeX conversion, PDF
handoff planning, legacy DOC/CFB, charset, or Markdown/HTML reader and writer
behavior.

## Follow-Up

Keep dependent-block LZ4 streams, dictionary-backed LZ4 frames, ZIP64 policy,
tar sparse files, hardlink/symlink extraction policy, encrypted archive
preflight, and filesystem extraction policy as separate bounded slices unless
concrete Pandoc fixtures require them.
