# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T151754Z`

Base accepted HEAD: `91de4ed62d6b0b7fdd5499395c7b7dbc88f92c5e`

## Implementation

- Tightened `TarArchive` PAX review metadata handling for bounded TAR review
  packets.
- Parsed PAX header keys and values now must be valid UTF-8 before the archive
  exposes entry names, packet annotations, or file bytes.
- Generated global PAX headers now use the same UTF-8 guard before fixture
  bytes are written.
- Updated the WordPress ZIP/package preflight smoke to report
  `tarPaxReviewMetadataUtf8Policy=rejected`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. TAR PAX records are text metadata that this lane exposes in WordPress
review packets. Prior slices guarded selected fields such as `path`, `uname`,
and `gname`; this slice applies the same review-safe UTF-8 policy to generic
PAX keys and values so arbitrary annotations cannot carry invalid bytes into
JSON/review output.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `tar`, external
`gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tooling, browser
renderer, JavaScript, online sanitizer, or online service was executed.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 329 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the focused PAX review-metadata expectation:
    `1 test files, 332 assertions, 1 failures`.
  - Failure: `Expected exception RuntimeException was not thrown` because an
    invalid UTF-8 PAX `comment` value was accepted.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 335 assertions, 0 failures`.
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

- `phpPass`: `970 -> 971`.
- `benchmarkDenominator.mapped`: `1425 -> 1426`.
- Focused archive coverage: `39 -> 40` PASS cases and `329 -> 335`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=40`,
  `mappedArchiveCompressionStreamCoreCases=40`, and
  `archiveCompressionStreamCoreAssertions=335`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive`, `ArchiveCompressionStream`, `GzipStream`, `DeflateStream`,
`Lz4Frame`, and WordPress ZIP/package preflight example. Full upstream Pandoc
runner parity remains blocked on hydrating and building the pinned Haskell test
executables; this TAR PAX metadata safety behavior is covered by focused native
PHP tests and does not require Pandoc, Cabal, Haskell runners, Word,
LibreOffice, `tar`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive
tooling, browser renderers, online sanitizers, or online services.

## Non-Overlap

This does not repeat accepted gzip DEFLATE payload validation, gzip extra
subfield validation, gzip header label/text projection, split-gzip TAR member
CRC/XFL/OS provenance, raw/zlib DEFLATE provenance, POSIX TAR file and
directory read/write paths, local/global PAX path and size policy, PAX owner
metadata, PAX `linkpath` rejection, GNU long-name path metadata, GNU long-link
rejection, TAR end-marker validation, TAR drive-letter rejection, base-256
numeric decoding, TAR sparse-file rejection, independent/dependent LZ4 frame
writing, dependent LZ4 frame decoding, ZIP/OPC package primitives, DOCX/ODT/
EPUB readers, doctemplates, YAML metadata, CSL/BibTeX, table geometry,
math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset, syntax
highlighting, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep recursive nested archive discovery, encrypted archive preflight,
filesystem extraction, compressed ZIP dispatch, multi-volume tar/zip handling,
sparse-file reconstruction, hardlink/symlink extraction policy,
dictionary-backed LZ4 frames, non-deflate ZIP compression methods, and full
upstream-runner dependency planning as separate bounded slices unless concrete
Pandoc package fixtures require them.
