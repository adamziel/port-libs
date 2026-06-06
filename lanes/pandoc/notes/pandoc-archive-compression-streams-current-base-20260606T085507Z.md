# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260606T085507Z`

Base accepted HEAD: `9bad70694349fdf8df2944b1d0fdaa86a6613e3b`

## Behavior

- Added bounded TAR entry provenance for effective, inherited global, local,
  and locally deleted PAX metadata.
- `TarArchiveEntry` now records the effective global PAX metadata inherited
  before local overrides, the raw local PAX records attached to the entry, and
  zero-length local PAX keys that deleted inherited metadata.
- `TarArchiveEntry` also records path-source provenance for ordinary header
  paths, ustar prefix paths, PAX `path` metadata, and GNU long-name metadata.
- `ArchiveCompressionStream::inspectTarStream*()` surfaces the same provenance
  in entry layouts so WordPress/package review queues can audit whether an
  import timestamp, reviewer comment, owner, or path came from global PAX,
  local PAX, GNU long-name, ustar prefix, or a plain header.
- The WordPress archive preflight example now validates the new provenance for
  its gzip-wrapped TAR packet with local PAX deletion records.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. POSIX-style TAR PAX global headers apply to subsequent entries until
changed, local PAX headers apply only to the next entry, and zero-length local
records delete inherited metadata for that entry. Package fixtures need this
review provenance without changing the effective file bytes exposed to DOCX,
ODT, EPUB, and WordPress import handoff code.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`,
external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool,
browser renderer, online sanitizer, online service, live provider test, or
live-service provider test was executed.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 593 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 573 assertions, 3 failures`.
  - Failure: `TarArchiveEntry` and TAR entry layout metadata did not expose
    global/local/deleted PAX provenance or path-source fields.
- After implementation:
  - `php -l lanes/pandoc/src/TarArchiveEntry.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 627 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1256 -> 1257`.
- `benchmarkDenominator.mapped`: `1700 -> 1701`.
- Focused archive test grew from `593` to `627` assertions.
- Manifest archive-compression counters record
  `archiveCompressionStreamCoreCases=11`,
  `mappedArchiveCompressionStreamCoreCases=11`, and
  `archiveCompressionStreamCoreAssertions=135`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`TarArchive`, `TarArchiveEntry`, `ArchiveCompressionStream`, `GzipStream`, the
focused PHP test harness, and the existing WordPress archive preflight example.
Full upstream Pandoc runner parity remains blocked on hydrating the pinned
Pandoc checkout and building the Haskell Tasty executables for `test-pandoc`
and `test-pandoc-lua-engine`.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation,
gzip Latin-1/provenance labels, gzip text-hint flags, gzip member byte-layout
offsets, raw/zlib DEFLATE provenance, LZ4 frame parsing or writing, ZIP/OPC
package primitives, TAR PAX path/size/owner/access-time/change-time metadata
parsing, PAX deletion application, duplicate PAX keyword rejection, GNU
long-name parsing or validation, GNU long-link rejection, typeflag `7`
contiguous file handling, trailing-slash regular-entry directory
normalization, TAR end-marker validation, TAR drive-letter rejection, base-256
numeric decoding, TAR sparse/link/device rejection, or generic TAR/ZIP
package-kind detection.

## Follow-Up

Keep nested archive discovery, encrypted archive preflight, sparse-file
reconstruction, hardlink/symlink extraction policy, non-deflate ZIP methods,
dictionary-backed LZ4 frames, and full upstream-runner parity as separate
bounded slices unless concrete package fixtures require them.
