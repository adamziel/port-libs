# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T081043Z`

Base accepted HEAD: `7a8bfe458c7cf3f121b479b45379fc05e74c094d`

## Implementation

- Added bounded dependent-block LZ4 frame writing to `Lz4Frame::build()`.
- New `blockIndependent => false` build option clears the LZ4 independence
  flag and lets later compressed blocks seed matches from the previous 64 KiB
  of decoded block history.
- Preserved the existing default independent-block writer behavior and
  checksum/content-size validation.
- Updated the WordPress ZIP/package preflight smoke to build a dependent LZ4
  review payload through the production API and verify the second block
  compresses from prior block history.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. The LZ4 frame format allows frames with the block-independence flag unset,
where compressed blocks may reference the previous decoded block history. The
bounded PHP helper now writes that frame shape for package/review fixtures
without invoking external `lz4` or shelling out to archive tools.

This does not implement a generic high-ratio LZ4 compressor, dictionary-backed
LZ4 frames, nested archive discovery, encrypted archive preflight, filesystem
extraction, compressed ZIP dispatch, multi-volume archive handling, sparse-file
reconstruction, hardlink/symlink extraction, or non-deflate ZIP compression
methods.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 241 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the dependent-builder expectation:
    `1 test files, 244 assertions, 1 failures`.
  - Failure: expected `blockIndependent=false`; actual frame metadata stayed
    `true` because the writer ignored the new dependency-mode option.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 249 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `765 -> 766`.
- `benchmarkDenominator.mapped`: `1224 -> 1225`.
- Focused archive coverage: `29 -> 30` PASS cases and `241 -> 249`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=30`,
  `mappedArchiveCompressionStreamCoreCases=30`, and
  `archiveCompressionStreamCoreAssertions=249`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`Lz4Frame` frame parser/writer and the WordPress package preflight smoke. Full
upstream Pandoc runner parity remains blocked on hydrating and building the
pinned Haskell test executables; this stream-writer behavior is covered by
focused native PHP tests and does not require Pandoc, Cabal, Haskell runners,
tar, zip/unzip, external `lz4`, office tools, renderers, or online services.

## Non-Overlap

This does not repeat accepted gzip header/member validation, gzip extra
subfield validation, explicit or auto-detected archive dispatch, split gzip/LZ4
stream inspection, POSIX TAR file and directory read/write paths,
local/global PAX policy, GNU long-name metadata, TAR end-marker validation,
TAR drive-letter rejection, base-256 numeric decoding, TAR sparse-file
rejection, raw/zlib DEFLATE validation, independent LZ4 block writing,
dependent LZ4 frame decoding, ZIP/OPC package primitives, DOCX/ODT/EPUB
readers, doctemplates, YAML metadata, CSL/BibTeX, table geometry, math/TeX
conversion, PDF handoff planning, legacy DOC/CFB, charset, syntax highlighting,
or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep broader LZ4 compressor optimization, dictionary-backed frames, nested
archive discovery, encrypted archive preflight, filesystem extraction,
compressed ZIP dispatch, multi-volume tar/zip handling, sparse-file
reconstruction, hardlink/symlink extraction policy, and non-deflate compression
methods as separate bounded slices unless concrete Pandoc package fixtures
require them.
