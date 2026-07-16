# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T133955Z`

Base accepted HEAD: `390fd9d5a6e0ca911e658e0a91d7a37d894b97ef`

## Implementation

- Added gzip Latin-1 header text projection to `GzipStream::members()`.
- `FNAME` and `FCOMMENT` raw bytes remain available as `filename` and
  `comment`, while `filenameText` and `commentText` expose UTF-8 review text
  with `gzip-latin1` encoding markers.
- Carried the same fields through
  `ArchiveCompressionStream::inspectTarStream*()` so TAR review-packet
  preflight callers do not need to bypass the archive inspection API.
- Updated the WordPress ZIP/package preflight smoke to verify a gzip-wrapped
  TAR packet with Latin-1 filename/comment metadata.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Gzip member `FNAME` and `FCOMMENT` metadata are byte header fields used by
package fixtures for provenance; this slice preserves those raw bytes and adds
bounded UTF-8 display text for WordPress review packets. It does not reinterpret
TAR entry paths, change payload decoding, or add filesystem extraction.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `tar`, external
`gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tooling, browser
renderer, JavaScript, online sanitizer, or online service was executed.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 301 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the Latin-1 gzip expectation:
    `1 test files, 303 assertions, 1 failures`.
  - Failure: `filenameText` was absent from gzip member metadata.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 311 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php -l lanes/pandoc/src/GzipStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
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

- `phpPass`: `925 -> 926`.
- `benchmarkDenominator.mapped`: `1382 -> 1383`.
- Focused archive coverage: `37 -> 38` PASS cases and `301 -> 311`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=38`,
  `mappedArchiveCompressionStreamCoreCases=38`, and
  `archiveCompressionStreamCoreAssertions=311`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`GzipStream`, `ArchiveCompressionStream`, `TarArchive`, and WordPress package
preflight example. Full upstream Pandoc runner parity remains blocked on
hydrating and building the pinned Haskell test executables; this gzip header
text behavior is covered by focused native PHP tests and does not require
Pandoc, Cabal, Haskell runners, Word, LibreOffice, archive commands, external
validators, or online services.

## Non-Overlap

This does not repeat accepted gzip DEFLATE payload validation, gzip extra
subfield validation, split-gzip TAR member CRC/XFL/OS provenance, raw/zlib
DEFLATE provenance, POSIX TAR file and directory read/write paths, local/global
PAX path and size policy, PAX owner metadata, PAX `linkpath` rejection, GNU
long-name path metadata, GNU long-link rejection, TAR end-marker validation,
TAR drive-letter rejection, base-256 numeric decoding, TAR sparse-file
rejection, independent/dependent LZ4 frame writing, dependent LZ4 frame
decoding, ZIP/OPC package primitives, DOCX/ODT/EPUB readers, doctemplates,
YAML metadata, CSL/BibTeX, table geometry, math/TeX conversion, PDF handoff
planning, legacy DOC/CFB, charset, syntax highlighting, or Markdown/HTML
reader and writer behavior.

## Follow-Up

Keep dictionary-backed LZ4 frames, recursive nested archive discovery,
encrypted archive preflight, filesystem extraction, compressed ZIP dispatch,
multi-volume tar/zip handling, sparse-file reconstruction, hardlink/symlink
extraction policy, non-deflate compression methods, and full upstream-runner
dependency planning as separate bounded slices unless concrete Pandoc package
fixtures require them.
