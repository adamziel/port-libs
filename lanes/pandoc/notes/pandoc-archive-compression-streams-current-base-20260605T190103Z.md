# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T190103Z`

Base accepted HEAD: `6eabc470c32c0f122118ac788fbbcb8021d0420e`

## Implementation

- Added generic package-kind detection to `ArchiveCompressionStream`.
- New public helpers:
  - `detectPackageKindAuto()` returns `tar` or `zip` after native wrapper
    decoding.
  - `inspectPackageStreamAuto()` returns the existing TAR or ZIP inspection
    payload with a `kind` key.
- The dispatcher reuses the existing bounded native gzip, zlib, raw-DEFLATE,
  LZ4, TAR, and ZIP readers; it does not shell out or introduce a new archive
  parser.
- Updated the WordPress ZIP/package preflight smoke so opaque gzip-wrapped ZIP
  and gzip-wrapped TAR uploads exercise generic package-kind detection before
  import bytes are exposed.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Prior archive slices added explicit TAR dispatch, explicit ZIP dispatch,
gzip member provenance, zlib/raw-DEFLATE inspection, and LZ4 frame inspection.
Opaque import queues still needed a bounded native handoff that can classify
compressed upload bytes as a TAR review packet or ZIP/OPC package before
calling the type-specific reader.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `tar`, external
`gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tooling, browser
renderer, JavaScript, online sanitizer, or online service was executed.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 417 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding generic package-kind expectations:
    `1 test files, 417 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectPackageStreamAuto()`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 434 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
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

- `phpPass`: `1044 -> 1045`.
- `benchmarkDenominator.mapped`: `1496 -> 1497`.
- Focused archive coverage: `42 -> 43` PASS cases and `417 -> 434`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record the verified focused file
  totals: `archiveCompressionStreamCoreCases=43`,
  `mappedArchiveCompressionStreamCoreCases=43`, and
  `archiveCompressionStreamCoreAssertions=434`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ArchiveCompressionStream`, `GzipStream`, `DeflateStream`, `Lz4Frame`,
`TarArchive`, `ZipPackage`, and WordPress ZIP/package preflight example. Full
upstream Pandoc runner parity remains blocked on hydrating and building the
pinned Haskell test executables; this package-kind detection behavior is
covered by focused native PHP tests and does not require Pandoc, Cabal,
Haskell runners, Word, LibreOffice, `tar`, `gzip`, `zip`, `unzip`, `lz4`,
`ZipArchive`, external archive tooling, browser renderers, online sanitizers,
or online services.

## Non-Overlap

This does not repeat accepted ZIP/OPC package primitive parsing, compressed
ZIP stream dispatch, TAR stream dispatch, ZIP central-directory signature
provenance, unsupported ZIP compression-method policy, gzip member framing,
gzip Latin-1/provenance labels, split-gzip TAR member provenance, raw/zlib
DEFLATE TAR provenance, POSIX TAR file and directory read/write paths, PAX
path/size/owner/review metadata policy, GNU long-name metadata, TAR
sparse/link/device rejection, LZ4 frame parsing/writing, dependent LZ4 block
support, DOCX/ODT/EPUB readers, doctemplates, YAML metadata, CSL/BibTeX, table
geometry, math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset,
syntax highlighting, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep recursive nested archive discovery, encrypted archive preflight,
filesystem extraction, multi-volume tar/zip handling, sparse-file
reconstruction, hardlink/symlink extraction policy, dictionary-backed LZ4
frames, non-deflate ZIP compression methods, and full upstream-runner
dependency planning as separate bounded slices unless concrete Pandoc package
fixtures require them.
