# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T084201Z`

Base accepted HEAD: `80b373e90e1c3df6aabeea77b198f3f317bb03d9`

## Implementation

- Tightened `TarArchive` local PAX handling so `path` metadata must be valid
  UTF-8 before it is used as an exposed package entry name.
- Preserved accepted safe UTF-8 PAX path, size, mtime, uid/gid, uname/gname,
  global review metadata, GNU long-name, base-256 numeric, gzip, deflate, and
  LZ4 stream behavior.
- Updated the WordPress ZIP/package preflight smoke to report
  `tarPaxUtf8PathPolicy=rejected` for invalid PAX path byte sequences.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. POSIX PAX `path` records are the long-name metadata that higher-level
DOCX/ODT/EPUB and WordPress review packet code sees as import entry names. The
bounded PHP reader now fails closed when that path is not valid UTF-8 instead
of exposing invalid byte strings as part names.

This does not implement binary PAX path decoding, GNU long-name UTF-8 policy,
recursive nested archive discovery, encrypted archive preflight, filesystem
extraction, compressed ZIP dispatch, multi-volume archive handling,
sparse-file reconstruction, hardlink/symlink extraction, dictionary-backed LZ4
frames, or non-deflate ZIP compression methods.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 249 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the PAX UTF-8 expectation:
    `1 test files, 253 assertions, 1 failures`.
  - Failure: `Expected exception RuntimeException was not thrown`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 253 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
  - `git diff --check -- lanes/pandoc`
  - Result: clean.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `783 -> 784`.
- `benchmarkDenominator.mapped`: `1242 -> 1243`.
- Focused archive coverage: `30 -> 31` PASS cases and `249 -> 253`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=31`,
  `mappedArchiveCompressionStreamCoreCases=31`, and
  `archiveCompressionStreamCoreAssertions=253`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive` parser and WordPress package preflight smoke. Full upstream Pandoc
runner parity remains blocked on hydrating and building the pinned Haskell test
executables, but this TAR/PAX path validation is covered by focused native PHP
tests and does not require Pandoc, Cabal, Haskell runners, tar, gzip, lz4,
zip/unzip, office tools, renderers, or online services.

## Non-Overlap

This does not repeat accepted gzip header/member validation, gzip extra
subfield validation, explicit or auto-detected archive dispatch, split
gzip/LZ4 stream inspection, POSIX TAR file and directory read/write paths,
local PAX size/owner metadata, global PAX per-entry metadata rejection, GNU
long-name metadata, TAR end-marker validation, TAR drive-letter rejection,
base-256 numeric decoding, TAR sparse-file rejection, USTAR version
validation, raw/zlib DEFLATE wrapper validation, independent/dependent LZ4
block decoding or writing, ZIP/OPC package primitives, DOCX/ODT/EPUB readers,
doctemplates, YAML metadata, CSL/BibTeX, table geometry, math/TeX conversion,
PDF handoff planning, legacy DOC/CFB, charset, syntax highlighting, or
Markdown/HTML reader and writer behavior.

## Follow-Up

Keep binary PAX path decoding, GNU long-name UTF-8 policy, recursive nested
archive discovery, encrypted archive preflight, filesystem extraction,
compressed ZIP dispatch, multi-volume tar/zip handling, sparse-file
reconstruction, hardlink/symlink extraction, dictionary-backed LZ4 frames, and
non-deflate ZIP compression methods as separate bounded slices unless concrete
Pandoc package fixtures require them.
