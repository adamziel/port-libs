# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T063645Z`

Base accepted HEAD: `f7d85167a5478158905fe95367f2ea484c7eccd8`

## Implementation

- Tightened `TarArchive` global PAX handling so global PAX records cannot set
  per-entry `path`, `size`, `linkpath`, or sparse-file metadata for later
  package entries.
- Preserved bounded global review metadata such as `comment` and `hdrcharset`,
  and preserved local PAX path/size/owner handoff for the immediately following
  entry.
- Updated the WordPress ZIP/package preflight smoke to report
  `tarGlobalPaxPerEntryPolicy=rejected`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Local PAX extended headers are per-entry metadata, while global PAX records
apply to subsequent entries; allowing global `path` or `size` lets one metadata
record rename or resize multiple package entries before DOCX/ODT/EPUB or
WordPress review handoff sees the bytes. The bounded PHP reader now fails
closed for those per-entry global keys instead of silently applying them.

This does not implement recursive nested archive discovery, filesystem
extraction, encrypted archive preflight, compressed ZIP dispatch, multi-volume
archive handling, sparse-file reconstruction, hardlink/symlink extraction, or
non-deflate compression methods.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 214 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the global-PAX expectation: `1 test files, 215 assertions, 1 failures`.
  - Failure: `Expected exception RuntimeException was not thrown`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 218 assertions, 0 failures`.
  - `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8117 assertions, 0 failures`.
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - Result: `lane-status json ok`.
  - `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `710 -> 711`.
- Focused archive coverage: `26 -> 27` PASS cases and `214 -> 218`
  assertions in `ArchiveCompressionStreamTest.php`.
- Focused lane coverage: `20 test files, 8117 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive` parser and WordPress package preflight smoke. Full upstream Pandoc
runner parity remains blocked on hydrating and building the pinned Haskell test
executables; this TAR global-PAX policy is covered by focused native PHP tests
and does not require Pandoc, Cabal, Haskell runners, tar, zip/unzip, lz4,
office tools, renderers, or online services.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip extra subfield
validation, explicit or auto-detected archive dispatch, POSIX TAR file and
directory read/write paths, local PAX path/size/owner metadata, GNU long-name
metadata, TAR end-marker validation, TAR drive-letter rejection, base-256
numeric decoding, TAR sparse-file rejection, USTAR version validation, raw/zlib
DEFLATE wrapper validation, independent/skippable/dependent LZ4 frame decoding,
ZIP/OPC package primitives, DOCX/ODT/EPUB readers, doctemplates, YAML metadata,
CSL/BibTeX, table geometry, math/TeX conversion, PDF handoff planning, legacy
DOC/CFB, charset, syntax highlighting, or Markdown/HTML reader and writer
behavior.

## Follow-Up

Keep recursive nested archive policy, encrypted archive preflight, filesystem
extraction, compressed ZIP dispatch, multi-volume tar/zip handling, sparse-file
reconstruction, hardlink/symlink extraction policy, and non-deflate compression
methods as separate bounded slices unless concrete Pandoc package fixtures
require them.
