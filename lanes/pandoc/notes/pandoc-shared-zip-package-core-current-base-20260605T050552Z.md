# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T050552Z`

Base accepted HEAD: `f7a2c4d50859ee2201e67502670935dceb5a08c7`

## Implementation

- Added bounded raw ZIP entry-name preflight before Info-ZIP Unicode path
  decoding.
- `ZipPackage::fromString()` now rejects unsafe central/local raw member names
  such as absolute paths and traversal segments even when a `0x7075` Unicode
  path extra field would otherwise decode them to a safe displayed package
  part.
- Preserved the existing safe Unicode-path behavior for legacy raw names such
  as `word/media/review-image.bin` that map to Unicode Office media paths.
- Updated the WordPress ZIP package preflight smoke to expose
  `rawUnicodePathPolicy=rejected` before reviewer media bytes are importable.

## Source Truth

This stays inside the accepted `pandoc-shared-zip-package-core-*` support row
for DOCX/EPUB/ODT-style ZIP containers. Pandoc package readers depend on ZIP
member names as package part identifiers; raw local/central names that encode
absolute or traversal paths are unsafe package metadata even if Unicode path
extras present a cleaner decoded name. This is a narrow preflight guard over
the existing native PHP ZIP reader, not a full ZIP64, encryption, or external
archive-tool implementation.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, Word, LibreOffice, `zip`,
`unzip`, tar, LZ4 CLI, TeX/PDF engine, external template engine, browser
renderer, or online service was executed.

## Evidence

- Baseline focused command before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 238 assertions, 0 failures`.
- After implementation:
  - `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 243 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Delta

- Focused PHP PASS cases: `639 -> 640` lane total.
- Focused ZIP assertions: `238 -> 243` for `ZipPackageTest.php`, adding 5
  assertions.
- Manifest mapped checks: `1114 -> 1115`.
- ZIP package support cases: `21 -> 22`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`ZipPackage` primitive and reuses the accepted extra-field, Unicode path, and
safe package-name checks. Full upstream Pandoc runner parity remains blocked
on hydrating/building the Haskell Pandoc checkout at the manifest commit, but
raw ZIP path preflight is not blocked by that runner.

## Non-Overlap

This does not repeat accepted ZIP central-directory parsing, package writing,
data descriptors, CRC/local-header checks, central/local extra-field parsing,
extended or NTFS timestamp handling, ZIP64 extra-field rejection, Unix symlink
rejection, drive-letter decoded path rejection, directory payload rejection,
bounded reads, gzip/tar/LZ4 archive streams, OPC relationships/content types,
DOCX/ODT/EPUB readers, syntax highlighting, table geometry, or Markdown/HTML
reader and writer behavior. It only adds raw entry-name safety before Unicode
path metadata can expose package parts.

## Follow-Up

Keep default per-reader size-limit policy, compression-ratio diagnostics,
central-directory digital-signature handling, extra compression methods,
AES/encrypted archives, and full ZIP64 large-archive support as separate
bounded ZIP package slices.
