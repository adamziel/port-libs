# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260606T183635Z`
Base accepted HEAD: `8d02d2b80dc4ec1dcde2ffb3e63387fccc05cf0a`

## Behavior

- Added bounded TAR sparse-map review metadata to `TarArchive::sparsePolicyPreflight()`.
- GNU and SCHILY PAX sparse maps now expose:
  - `sparseMapSource`
  - `sparseMapSegments` with `offset`, `length`, and `endOffset`
  - `sparseMapSegmentCount`
  - `sparseMapPayloadBytes`
- Malformed sparse maps are rejected before sparse-policy packets are exposed:
  odd offset/length pairs, non-numeric values, mixed GNU/SCHILY maps,
  overlapping or unsorted segments, and segments past the declared real size.
- Sparse extraction remains blocked; this is metadata-only review support for
  archive package fixtures.
- The WordPress archive stream preflight smoke now asserts SCHILY sparse-map
  segment metadata and a malformed-map blocked flag.

## Source Truth

This ports a bounded TAR support-library behavior needed by package fixture
review. POSIX/GNU sparse TAR entries are not safe to expose as normal files
without reconstruction policy, but their PAX sparse maps are useful provenance
for WordPress import reviewers deciding why a package member stayed blocked.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`,
external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool,
browser renderer, online sanitizer, online service, live provider test, or
live-service provider test was executed.

## Verification

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 748 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 729 assertions, 2 failures`.
  - Failures: sparse-map fields were missing from policy metadata and malformed
    sparse maps were accepted by `sparsePolicyPreflight()`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 767 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
- Syntax and lane checks:
  - `php -l lanes/pandoc/src/TarArchive.php`
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test grew from `748` to `767` assertions.
- `lane-status.json` `phpPass` moves from `1388` to `1389`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moves from `1801` to `1802`.
- Archive-compression counters move from `10` to `11` mapped support cases and
  from `101` to `120` focused support assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `TarArchive`,
`ArchiveCompressionStream`, `GzipStream`, in-memory TAR fixtures, the WordPress
archive stream preflight example, and the focused PHP test harness. Full
sparse-file reconstruction or extraction, filesystem extraction policy,
external archive-tool validation, and full upstream Pandoc/Haskell runner parity
remain separate bounded follow-up work.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation,
split-gzip XFL/OS/CRC32 and byte-layout provenance, raw/zlib DEFLATE
provenance, LZ4 frame parsing or writing, ZIP/OPC package primitives, ZIP
encryption flag rejection, TAR PAX path/size/owner metadata, PAX access/change
timestamp parsing, PAX deletion application, PAX hdrcharset policy, duplicate
PAX keyword rejection, GNU long-name parsing, TAR link-policy preflight,
sparse-entry blocking, GNU long-link rejection, typeflag `7` contiguous file
handling, trailing-slash regular-entry directory normalization, TAR end-marker
validation, TAR drive-letter rejection, base-256 numeric decoding, TAR device
rejection, signed checksum compatibility, nested package discovery, or generic
TAR/ZIP package-kind detection.

## Follow-Up

Keep sparse-file reconstruction, hardlink/symlink extraction, filesystem
extraction, recursive content conversion, archive-bomb heuristics, non-deflate
ZIP methods, dictionary-backed LZ4 frames, multi-volume archive handling,
external archive-tool validation, and full upstream-runner parity as separate
bounded slices unless concrete package fixtures require them.
