# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260606T092624Z`

Base accepted HEAD: `4327484a8280109407f012fb0dae9c93df0ee813`

## Behavior

- Added bounded TAR link-policy preflight without enabling link extraction.
- `TarArchive::linkPolicyPreflight()` now reports hardlink and symlink entries, target paths, target metadata source (`header-linkname`, `pax-linkpath`, or `gnu-long-link`), whether a hardlink target has already appeared, byte offsets, and blocked-extraction diagnostics.
- `ArchiveCompressionStream::inspectTarLinkPolicy()` applies the same policy inspection after bounded gzip/zlib/raw-deflate/LZ4/plain TAR decoding.
- `TarArchive::fromString()` still rejects TAR link entries before exposing package bytes; this slice only gives review queues a structured reason.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support row. POSIX TAR hardlinks and symlinks use typeflags `1` and `2`, the ustar `linkname` field, optional PAX `linkpath`, and GNU long-link `K` metadata. WordPress import/package fixtures need target provenance and an explicit blocked policy without following links, materializing filesystem aliases, or shelling out to archive tools.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`, external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Evidence

- No current-base Pandoc rework note was present in `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 627 assertions, 0 failures`.
- After implementation:
  - `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 654 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
  - JSON validation:
    - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
    - Result: both files valid.
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1258 -> 1259`.
- `benchmarkDenominator.mapped`: `1702 -> 1703`.
- Focused archive test grew from `627` to `654` assertions.
- Manifest archive-compression counters record `archiveCompressionStreamCoreCases=12`, `mappedArchiveCompressionStreamCoreCases=12`, and `archiveCompressionStreamCoreAssertions=162`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `TarArchive`, `ArchiveCompressionStream`, `GzipStream`, the focused PHP test harness, and the existing WordPress archive preflight example. Full upstream Pandoc runner parity remains blocked on hydrating the pinned Pandoc checkout and building Haskell Tasty executables for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation, gzip Latin-1/provenance labels, gzip text-hint flags, gzip member byte-layout offsets, raw/zlib DEFLATE provenance, LZ4 frame parsing or writing, ZIP/OPC package primitives, TAR PAX path/size/owner/access-time/change-time metadata parsing, PAX deletion application, duplicate PAX keyword rejection, GNU long-name parsing, GNU long-link rejection in the extraction reader, typeflag `7` contiguous file handling, trailing-slash regular-entry directory normalization, TAR end-marker validation, TAR drive-letter rejection, base-256 numeric decoding, TAR sparse/device rejection, or generic TAR/ZIP package-kind detection.

## Follow-Up

Keep nested archive discovery, encrypted archive preflight, sparse-file reconstruction, actual hardlink/symlink materialization or extraction, non-deflate ZIP methods, dictionary-backed LZ4 frames, and full upstream-runner parity as separate bounded slices unless concrete package fixtures require them.
