# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T095042Z`

Base accepted HEAD: `8ccd932f4bdb6defe3ca56e114e35753cbcce40d`

## Implementation

- Tightened `TarArchive` GNU long-name metadata handling so the resolved path
  must be valid UTF-8 before it is exposed as a package entry name.
- Added a focused native TAR test that rejects invalid UTF-8 GNU long-name
  bytes before any WordPress review-packet content is addressable.
- Updated the WordPress ZIP/package preflight smoke to report
  `tarGnuLongNameUtf8Policy=rejected`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. GNU long-name TAR records are path metadata, equivalent in effect to PAX
`path` for the bounded reader: the metadata becomes the visible archive entry
name that DOCX/ODT/EPUB review packets and WordPress import code can inspect.
The local contract now fails closed on invalid UTF-8 in both PAX path and GNU
long-name metadata before exposing package bytes.

This does not implement binary path recoding, nested archive discovery,
encrypted archive preflight, filesystem extraction, compressed ZIP dispatch,
multi-volume archive handling, sparse-file reconstruction, hardlink/symlink
extraction, dictionary-backed LZ4 frames, or non-deflate ZIP compression
methods.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 272 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the GNU long-name UTF-8 expectation:
    `1 test files, 273 assertions, 1 failures`.
  - Failure: `Expected exception RuntimeException was not thrown`.
- After implementation:
  - `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 273 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 9925 assertions, 0 failures`.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `816 -> 817`.
- `benchmarkDenominator.mapped`: `1276 -> 1277`.
- Focused archive coverage: `32 -> 33` PASS cases and `272 -> 273`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=33`,
  `mappedArchiveCompressionStreamCoreCases=33`, and
  `archiveCompressionStreamCoreAssertions=273`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive` parser and the WordPress package preflight smoke. Full upstream
Pandoc runner parity remains blocked on hydrating and building the pinned
Haskell test executables; this TAR metadata validation is covered by focused
native PHP tests and does not require Pandoc, Cabal, Haskell runners, tar,
gzip, lz4, zip/unzip, external archive tools, office tools, renderers, or
online services.

## Non-Overlap

This does not repeat accepted POSIX TAR file and directory read/write paths,
PAX UTF-8 path validation, PAX size/owner metadata, global PAX policy, GNU
long-name happy-path metadata, TAR end-marker validation, TAR drive-letter
rejection, base-256 numeric decoding, TAR sparse-file rejection, raw/zlib
DEFLATE validation, gzip member provenance, independent/dependent LZ4 block
decoding or writing, ZIP/OPC package primitives, DOCX/ODT/EPUB readers,
doctemplates, YAML metadata, CSL/BibTeX, table geometry, math/TeX conversion,
PDF handoff planning, legacy DOC/CFB, charset, syntax highlighting, or
Markdown/HTML reader and writer behavior.

## Follow-Up

Keep binary path recoding, recursive nested archive discovery, encrypted
archive preflight, filesystem extraction, compressed ZIP dispatch, multi-volume
tar/zip handling, sparse-file reconstruction, hardlink/symlink extraction
policy, dictionary-backed LZ4 frames, and non-deflate compression methods as
separate bounded slices unless concrete Pandoc package fixtures require them.
