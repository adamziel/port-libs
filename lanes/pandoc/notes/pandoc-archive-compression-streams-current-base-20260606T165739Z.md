# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260606T165739Z`
Base accepted HEAD: `0fd6cb8c2fc5873379e5f6ee4a0531d4abf37ada`

## Behavior

- Added `ArchiveCompressionStream::inspectNestedPackageStreamsAuto()` for bounded metadata-only nested package discovery.
- The new preflight walks regular-file entries in already-supported TAR/ZIP packages, identifies archive-like candidates by extension and magic bytes, and reports nested TAR/ZIP kind, compression format, entry names, sizes, depth, and path provenance using `!` between nested package levels.
- Corrupt nested candidates stay visible as `unreadable` diagnostics instead of aborting the top-level package preflight.
- Discovery is bounded by the existing gzip/zlib/raw-deflate/LZ4/TAR/ZIP size limits and does not extract to the filesystem.
- The WordPress archive preflight smoke now includes a gzip-wrapped TAR containing a nested gzip-TAR, nested DOCX-style ZIP package, deeper ZIP inside the nested TAR, and a corrupt ZIP candidate.

## Source Truth

This ports a bounded support-library behavior needed by document conversion package fixtures: archive streams can carry nested Office/EPUB/review packets that should be surfaced to an import reviewer without shelling out to archive tools or recursively converting content. The slice reuses the lane's native PHP package readers and stream inspectors as source truth for accepted formats.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`, external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Verification

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 692 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 692 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectNestedPackageStreamsAuto()`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 736 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
- Syntax and lane checks:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: both JSON files ok.
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test grew from `692` to `736` assertions.
- `lane-status.json` `phpPass` moves from `1369` to `1370`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moves from `1782` to `1783`; archive-compression counters move to `11` mapped support cases and `145` focused support assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ArchiveCompressionStream`, `TarArchive`, `ZipPackage`, `GzipStream`, `DeflateStream`, `Lz4Frame`, in-memory fixtures, and the focused PHP test harness. Full upstream Pandoc runner parity remains gated on hydrated upstream checkout and Haskell Tasty runner execution, which is intentionally out of scope for this slice.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation, gzip Latin-1/provenance labels, gzip text-hint flags, split-gzip XFL/OS/CRC32 and byte-layout provenance, raw/zlib DEFLATE provenance, LZ4 frame parsing or writing, ZIP/OPC package primitives, TAR PAX path/size/owner/access/change timestamp parsing, PAX deletion application, duplicate PAX keyword rejection, GNU long-name parsing, TAR link-policy or sparse-policy preflight, GNU long-link rejection, typeflag `7` contiguous file handling, trailing-slash regular-entry directory normalization, TAR end-marker validation, TAR drive-letter rejection, base-256 numeric decoding, TAR device rejection, signed checksum compatibility, or generic TAR/ZIP package-kind detection.

## Follow-Up

Keep filesystem extraction, recursive content conversion, encrypted archive preflight, archive-bomb heuristics, sparse-file reconstruction, hardlink/symlink materialization, non-deflate ZIP methods, dictionary-backed LZ4 frames, multi-volume archive handling, and full upstream-runner parity as separate bounded slices unless concrete package fixtures require them.
