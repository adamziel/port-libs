# Pandoc Archive Compression Streams Current Base 2026-06-07T11:14:50Z

Lane: `pandoc`
Slice: `pandoc-archive-compression-streams-current-base-20260607T111450Z`
Base accepted HEAD: `f0ab63b0aec4070b72a5ad36f42b8b417227d7b2`

## Behavior

Added bounded archive-bomb policy preflight for package fixture streams:

- `ArchiveCompressionStream::inspectArchiveBombPolicyAuto()` auto-detects TAR or ZIP package streams across existing plain/gzip/zlib/raw-deflate/LZ4 dispatch.
- The preflight reports compressed stream size, decoded package byte size, entry uncompressed byte size, entry count, stream compression ratio, package expansion ratio, and total expansion ratio.
- Caller thresholds are explicit and fail closed when non-positive or non-finite.
- Streams over threshold return `review-before-conversion` with diagnostics for stream, package, or total expansion-ratio risk.
- The WordPress archive preflight example now shows the same reviewer-facing policy for a gzip-wrapped TAR packet.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support row. Office/EPUB/ODT and WordPress import package fixtures need bounded preflight metadata before conversion handoff, especially when an outer compression stream or inner ZIP payload can expand sharply. The patch ports that bounded PHP support contract without adding extraction, external archive validation, or recursive conversion.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`, external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Verification

- Baseline focused test before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 880 assertions, 0 failures`.
- Focused test after edits:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 912 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
- Required syntax and diff checks before handoff:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test grew from `880` to `912` assertions.
- `lane-status.json` `phpPass` moves from `1490` to `1491`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moves from `1909` to `1910`.
- Archive-compression manifest counters move from `11` to `12` mapped support cases and from `120` to `152` focused support assertions in this accepted worktree.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ArchiveCompressionStream`, `GzipStream`, `TarArchive`, `ZipPackage`, in-memory TAR/ZIP fixtures, the WordPress archive stream preflight example, and the focused PHP harness. Full upstream Pandoc/Haskell runner parity, external archive-tool validation, filesystem extraction, recursive conversion, encrypted archive handling, multi-volume archive handling, non-deflate ZIP methods, and automatic LZ4 dictionary discovery remain separate bounded follow-up work.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation, split-gzip XFL/OS/CRC32 and byte-layout provenance, raw/zlib DEFLATE provenance, LZ4 frame parsing or writing, LZ4 compressed/uncompressed block decoding, LZ4 skippable metadata, LZ4 dictionary metadata-only policy preflight, supplied LZ4 dictionary decode, content-size validation, block/content checksums, ZIP/OPC package primitives, ZIP encryption flag rejection, TAR PAX path/size/owner/atime/ctime/hdrcharset metadata, duplicate PAX keyword policy, GNU long-name parsing, TAR link-policy preflight, sparse-entry blocking and sparse-map provenance, GNU long-link rejection, typeflag `7` contiguous file handling, trailing-slash regular-entry directory normalization, TAR end-marker validation, TAR drive-letter rejection, base-256 numeric decoding, signed checksum compatibility, nested package discovery, generic TAR/ZIP package-kind detection, strict special-file rejection, or special-file policy preflight.

## Follow-Up

Keep threshold policy wiring into specific DOCX/EPUB/ODT readers, encrypted archive preflight, multi-volume archive diagnostics, automatic LZ4 dictionary discovery policy, non-deflate ZIP method metadata, filesystem extraction, recursive content conversion, external archive-tool validation, and full upstream-runner parity as separate bounded slices unless concrete package fixtures require them.
