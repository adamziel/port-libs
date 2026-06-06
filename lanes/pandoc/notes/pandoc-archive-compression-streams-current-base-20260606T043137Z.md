# Pandoc Archive Compression Streams Current Base

## Behavior Target

- Added bounded legacy TAR directory normalization for package fixtures.
- `TarArchive::fromString()` now treats a zero-size regular TAR entry whose
  resolved path ends in `/` as a directory entry.
- Payload-bearing regular entries with directory-like trailing-slash names now
  fail closed before package bytes are exposed.
- The WordPress archive preflight smoke now includes a gzip-wrapped legacy
  trailing-slash directory packet.

## Source Truth

The archive support-library contract remains native PHP package-fixture
handling for Pandoc conversion lanes: expose safe TAR directories/files through
`TarArchive` and `ArchiveCompressionStream` without invoking `tar`, `gzip`,
`zip`, `unzip`, `lz4`, `ZipArchive`, Pandoc, Cabal, or Haskell runners. Legacy
TAR streams may encode directory entries as zero-size regular headers with a
trailing slash; those should remain directories for DOCX/ODT/EPUB/WordPress
review packets, while non-empty directory-like regular entries remain unsafe.

## Verification

- Baseline before editing:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 537 assertions, 0 failures`.
- Red-first after adding the focused expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 539 assertions, 1 failures`.
  - Failure: the legacy trailing-slash directory was exposed as a regular file.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 554 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.

## Status Delta

- `phpPass`: `1194 -> 1195`.
- `benchmarkDenominator.mapped`: `1641 -> 1642`.
- Focused archive coverage is now
  `archiveCompressionStreamCoreCases=52`,
  `mappedArchiveCompressionStreamCoreCases=52`, and
  `archiveCompressionStreamCoreAssertions=554`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`TarArchive`, `ArchiveCompressionStream`, `GzipStream`, and the existing
WordPress archive preflight example. Full upstream Pandoc runner parity remains
blocked on the hydrated pinned Pandoc checkout plus Cabal/Tasty executable
builds for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip Latin-1/provenance
labels, split-gzip TAR member provenance, raw/zlib DEFLATE provenance, LZ4
frame parsing/writing, ZIP/OPC package primitives, TAR PAX path/size/owner/
timestamp metadata, duplicate PAX keyword rejection, GNU long-name metadata,
GNU long-link rejection, typeflag `7` contiguous file handling, TAR end-marker
validation, TAR drive-letter rejection, base-256 numeric decoding, TAR sparse/
link/device rejection, or generic TAR/ZIP package-kind detection.

## Next

Keep hardlink/symlink extraction policy, sparse-file reconstruction, nested
archive discovery, encrypted archive preflight, dictionary-backed LZ4 frames,
non-deflate ZIP methods, and full upstream-runner parity as separate bounded
slices.
