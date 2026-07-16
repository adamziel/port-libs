Slice: `pandoc-archive-compression-streams-current-base-20260605T073821Z`

Base accepted HEAD: `c160df4ef237403b28f073e9cd6d9cd3e2dbcf7d`

## Implementation

- Added `ArchiveCompressionStream::inspectTarStream()` and
  `ArchiveCompressionStream::inspectTarStreamAuto()` for bounded native PHP
  TAR stream preflight.
- The inspection report returns the detected/selected format, decoded TAR
  bytes, parsed `TarArchive`, ordered entry names, entry count, decoded stream
  size, unpacked regular-file byte size, and format-specific stream metadata.
- GZIP TAR inspection reports member count plus per-member filename, comment,
  modified time, uncompressed size, compressed payload size, member size, extra
  field count, and optional header CRC.
- LZ4 TAR inspection reports skippable/data frame counts, aggregate block
  count, and per-frame metadata without requiring the caller to invoke external
  `lz4`.
- The WordPress ZIP/package preflight smoke now exposes split gzip-member and
  split LZ4-frame TAR review packets and keeps concatenated complete TAR
  archives rejected before import.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. The bounded contract is one logical TAR review packet carried by a
compression stream. A single TAR payload may be split across gzip members or
LZ4 frames, but concatenating two complete TAR archives remains unsafe because
the second archive would appear after the first TAR end marker.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, tar, gzip, lz4,
zip/unzip, external template engine, TeX/PDF engine, browser renderer, online
sanitizer, or online service was executed.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 219 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the split-stream expectation:
    `1 test files, 219 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectTarStreamAuto()`.
- After implementation:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 241 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
  - `git diff --check -- lanes/pandoc`
  - Result: clean.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `750 -> 751`.
- `benchmarkDenominator.mapped`: `1209 -> 1210`.
- Focused archive coverage: `28 -> 29` PASS cases and `219 -> 241`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=29`,
  `mappedArchiveCompressionStreamCoreCases=29`, and
  `archiveCompressionStreamCoreAssertions=241`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`GzipStream`, `Lz4Frame`, `DeflateStream`, and `TarArchive` components.
Full upstream Pandoc runner parity remains blocked on hydrating and building
the pinned Haskell test executables, but this stream-preflight behavior is
covered by focused native PHP tests and the WordPress package smoke.

## Non-Overlap

This does not repeat accepted gzip header/member validation, gzip extra
subfield validation, explicit or auto-detected archive dispatch, POSIX TAR file
and directory read/write paths, local/global PAX policy, GNU long-name
metadata, TAR end-marker validation, TAR drive-letter rejection, base-256
numeric decoding, TAR sparse-file rejection, raw/zlib DEFLATE validation,
independent/dependent LZ4 block decoding, ZIP/OPC package primitives,
DOCX/ODT/EPUB readers, doctemplates, YAML metadata, CSL/BibTeX, table geometry,
math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset, syntax
highlighting, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep nested archive discovery, encrypted archive preflight, filesystem
extraction, compressed ZIP dispatch, multi-volume tar/zip handling, sparse-file
reconstruction, hardlink/symlink extraction policy, and non-deflate compression
methods as separate bounded slices unless concrete Pandoc package fixtures
require them.
