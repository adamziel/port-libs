# Pandoc Archive Compression Streams Current Base 2026-06-07T01:36:31Z

Lane: `pandoc`
Slice: `pandoc-archive-compression-streams-current-base-20260607T013631Z`
Base accepted HEAD: `f045041a3e9bc718c4c62b84783de136a8a23e7f`

## Behavior

Added bounded metadata-only TAR special-file policy preflight:

- `TarArchive::specialFilePolicyPreflight()` reports character-device, block-device, and FIFO entries without enabling extraction.
- Character and block device entries preserve device major/minor numbers from ustar header fields or PAX `devmajor` / `devminor` metadata, including source provenance.
- `ArchiveCompressionStream::inspectTarSpecialFilePolicy()` exposes the same policy after bounded plain/gzip/zlib/raw-deflate/LZ4 TAR stream decoding.
- Strict `TarArchive::fromString()` still rejects special files before package bytes are exposed.
- `wordpress-archive-stream-preflight.php` now includes a gzip-wrapped TAR with blocked special-file entries for WordPress import review.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support row. POSIX/ustar special files use typeflags `3`, `4`, and `6` plus device major/minor fields for device nodes. Document-import package fixtures need reviewer-visible provenance and an explicit blocked policy, not filesystem device creation or extraction.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`, external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Verification

- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 794 assertions, 0 failures`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 828 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
- Required syntax and diff checks were run before handoff:
  - `php -l lanes/pandoc/src/TarArchive.php`
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test grew from `794` to `828` assertions.
- `lane-status.json` `phpPass` moves from `1429` to `1430`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moves from `1845` to `1846`.
- Archive-compression counters move from `11` to `12` mapped support cases and from `120` to `154` focused support assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `TarArchive`, `ArchiveCompressionStream`, `GzipStream`, in-memory TAR fixtures, the WordPress archive stream preflight example, and the focused PHP test harness. Full upstream Pandoc/Haskell runner parity, external archive-tool validation, filesystem extraction, recursive conversion, sparse-file reconstruction, hardlink/symlink materialization, non-deflate ZIP methods, dictionary-backed LZ4 frames, and multi-volume archive handling remain separate bounded follow-up work.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation, split-gzip XFL/OS/CRC32 and byte-layout provenance, raw/zlib DEFLATE provenance, LZ4 frame parsing or writing, ZIP/OPC package primitives, ZIP encryption flag rejection, TAR PAX path/size/owner metadata, PAX access/change timestamp parsing, PAX deletion application, PAX hdrcharset policy, duplicate PAX keyword rejection and duplicate-key policy preflight, GNU long-name parsing, TAR link-policy preflight, sparse-entry blocking and sparse-map provenance, GNU long-link rejection, typeflag `7` contiguous file handling, trailing-slash regular-entry directory normalization, TAR end-marker validation, TAR drive-letter rejection, base-256 numeric decoding, signed checksum compatibility, nested package discovery, generic TAR/ZIP package-kind detection, or strict special-file rejection.

## Follow-Up

Keep archive-bomb heuristics, encrypted archive preflight, sparse-file reconstruction, hardlink/symlink materialization policy, filesystem extraction, recursive content conversion, non-deflate ZIP methods, dictionary-backed LZ4 frames, multi-volume archive diagnostics, external archive-tool validation, and full upstream-runner parity as separate bounded slices unless concrete package fixtures require them.
