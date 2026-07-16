# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260606T040119Z`

Base accepted HEAD: `d8a1a2e5c053ca2e28ed33fa0bdda224a560bb72`

## Implementation

- Added bounded support for legacy TAR typeflag `7` contiguous file entries.
- `TarArchive::fromString()` now treats typeflag `7` as a regular package file
  while preserving the existing checksum, safe-path, payload-size, duplicate
  entry, and unpacked-byte limit checks.
- Added focused archive coverage that reads a typeflag `7` entry through plain
  TAR inspection and gzip-wrapped package auto-preflight.
- Updated the WordPress archive stream preflight smoke to verify a
  gzip-wrapped legacy contiguous TAR packet without invoking external tools.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Legacy TAR writers can mark file payloads with typeflag `7` for
contiguous files; for Pandoc package fixture handoff and WordPress review
queues, the bounded native behavior is to expose those payloads as ordinary
regular files while still failing closed for unsafe paths, corrupt checksums,
unsupported links/devices/sparse entries, and configured size limits.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `tar`, external
`gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tooling, browser
renderer, JavaScript, online sanitizer, online service, or live provider test
was executed.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 523 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the focused contiguous-file expectation:
    `1 test files, 523 assertions, 1 failures`.
  - Failure: `Unsupported TAR entry type 7 for packet/legacy-contiguous.md`.
- After implementation:
  - `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 537 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1185 -> 1186`.
- `benchmarkDenominator.mapped`: `1633 -> 1634`.
- Focused archive coverage: `50 -> 51` PASS cases and `523 -> 537`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters now record
  `archiveCompressionStreamCoreCases=51`,
  `mappedArchiveCompressionStreamCoreCases=51`, and
  `archiveCompressionStreamCoreAssertions=537`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive`, `GzipStream`, `ArchiveCompressionStream`, focused PHP test
harness, and WordPress archive preflight example. Full upstream Pandoc runner
parity remains blocked on hydrating and building the pinned Haskell test
executables; this TAR stream behavior is covered by focused native PHP tests
and does not require Pandoc, Cabal, Haskell runners, Word, LibreOffice, `tar`,
`gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tooling, browser
renderers, online sanitizers, or online services.

## Non-Overlap

This does not repeat accepted ZIP/OPC package primitive parsing, compressed ZIP
stream dispatch, TAR PAX path/size/owner/timestamp metadata, duplicate PAX
keyword rejection, GNU long-name metadata, GNU long-link rejection, TAR
end-marker validation, TAR drive-letter rejection, base-256 numeric decoding,
TAR sparse/link/device rejection, gzip member framing, gzip Latin-1/provenance
labels, split-gzip TAR member provenance, raw/zlib DEFLATE provenance, LZ4
frame parsing/writing, dependent LZ4 block support, DOCX/ODT/EPUB readers,
doctemplates, YAML metadata, CSL/BibTeX, table geometry, math/TeX conversion,
PDF handoff planning, legacy DOC/CFB, charset, syntax highlighting, or
Markdown/HTML reader and writer behavior.

## Follow-Up

Keep dictionary-backed LZ4 frames, recursive nested archive discovery,
encrypted archive preflight, filesystem extraction, multi-volume tar/zip
handling, sparse-file reconstruction, hardlink/symlink extraction policy,
non-deflate ZIP compression methods, and full upstream-runner dependency
planning as separate bounded slices unless concrete Pandoc package fixtures
require them.
