# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T070904Z`

Base accepted HEAD: `96835b31f0b7d31c68967e2c8b5127f6a9eff04e`

## Implementation

- Tightened `TarArchive::fromString()` so local PAX `linkpath` metadata is
  rejected before any following regular file bytes are exposed.
- Preserved accepted local PAX `path`, `size`, `mtime`, `uid`, `gid`, `uname`,
  and `gname` handling for safe regular file review packets.
- Updated the WordPress ZIP/package preflight smoke to report
  `tarPaxLinkpathPolicy=rejected`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. PAX `linkpath` is link-target metadata; the local tar support contract
exposes only safe regular files and directories to DOCX/ODT/EPUB and WordPress
review packet handoff. Since hard links and symlinks remain intentionally
unsupported, local PAX `linkpath` now fails closed instead of riding along on a
regular file entry.

This does not implement recursive nested archive discovery, filesystem
extraction, encrypted archive preflight, compressed ZIP dispatch, multi-volume
archive handling, sparse-file reconstruction, hardlink/symlink extraction, or
non-deflate compression methods.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 218 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after adding the PAX `linkpath` expectation:
    `1 test files, 219 assertions, 1 failures`.
  - Failure: `Expected exception RuntimeException was not thrown`.
- After implementation:
  - `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 219 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 252 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
  - `git diff --check -- lanes/pandoc`
  - Result: clean.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `734 -> 735`.
- `benchmarkDenominator.mapped`: `1193 -> 1194`.
- Focused archive coverage: `27 -> 28` PASS cases and `218 -> 219`
  assertions in `ArchiveCompressionStreamTest.php`.
- Manifest archive compression counters were corrected to the current focused
  shape: `archiveCompressionStreamCoreCases=28`,
  `mappedArchiveCompressionStreamCoreCases=28`, and
  `archiveCompressionStreamCoreAssertions=219`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TarArchive` parser and WordPress package preflight smoke. Full upstream Pandoc
runner parity remains blocked on hydrating and building the pinned Haskell test
executables, but this TAR/PAX safety policy is covered by focused native PHP
tests and does not require Pandoc, Cabal, Haskell runners, tar, zip/unzip, lz4,
office tools, renderers, or online services.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip extra subfield
validation, explicit or auto-detected archive dispatch, POSIX TAR file and
directory read/write paths, local PAX path/size/owner metadata, global PAX
per-entry metadata rejection, GNU long-name metadata, TAR end-marker
validation, TAR drive-letter rejection, base-256 numeric decoding, TAR
sparse-file rejection, USTAR version validation, raw/zlib DEFLATE wrapper
validation, independent/skippable/dependent LZ4 frame decoding, ZIP/OPC
package primitives, DOCX/ODT/EPUB readers, doctemplates, YAML metadata,
CSL/BibTeX, table geometry, math/TeX conversion, PDF handoff planning, legacy
DOC/CFB, charset, syntax highlighting, or Markdown/HTML reader and writer
behavior.

## Follow-Up

Keep recursive nested archive policy, encrypted archive preflight, filesystem
extraction, compressed ZIP dispatch, multi-volume tar/zip handling,
sparse-file reconstruction, hardlink/symlink extraction policy, and non-deflate
compression methods as separate bounded slices unless concrete Pandoc package
fixtures require them.
