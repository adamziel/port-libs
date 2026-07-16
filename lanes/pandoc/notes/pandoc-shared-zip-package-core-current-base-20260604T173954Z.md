# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260604T173954Z`

Base accepted HEAD: `a3ed21553e0924089dcab2d718afc2adfde26809`

## Implementation

- Added bounded ZIP "version made by" metadata to `ZipPackageEntry`:
  - `madeByHostSystem()` exposes the central-directory host system byte;
  - `madeByVersion()` exposes the ZIP version byte;
  - `unixMode()` interprets the high 16 bits of external attributes only for
    Unix-made entries;
  - `isUnixSymlink()` identifies Unix symlink archive members.
- `ZipPackage::fromString()` now rejects Unix symlink entries before exposing
  Office/EPUB/ODT package parts to higher-level readers.
- `ZipPackage::fromParts()` / `build()` now reject generated package entries
  with Unix symlink external attributes before emitting bytes.
- Updated the WordPress ZIP package preflight smoke to verify symlink entries
  are rejected before media import.

## Source Truth

This stays inside the accepted `pandoc-shared-zip-package-core-*` support row
for DOCX/EPUB/ODT-style ZIP containers. ZIP central-directory entries carry a
"version made by" field whose high byte names the originating host system; for
Unix-made entries, the high 16 bits of external attributes carry the Unix file
mode. A symlink entry must not be treated as an ordinary package part or
WordPress attachment candidate in this bounded native reader.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`/`unzip`, TeX
or PDF engine, external template engine, browser renderer, or online service
was executed.

## Red/Green Evidence

- Baseline focused ZIP command before adding the new test:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 158 assertions, 0 failures`.
- Red-first command after adding the symlink-policy test:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before implementation: `1 test files, 156 assertions, 2 failures`.
  - Failures: `ZipPackageEntry::madeByHostSystem()` did not exist yet and
    symlink-backed package entries were not rejected.
- After implementation:
  - `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/src/ZipPackageEntry.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 166 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `11 test files, 3300 assertions, 0 failures`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`ZipPackage` and `ZipPackageEntry` primitives and reuses the accepted bounded
central-directory, external-attribute, and package preflight paths. It does
not require `ZipArchive`, external `zip`/`unzip`, Pandoc, office tools,
TeX/PDF engines, external template engines, Haskell test binaries, or online
services.

## Non-Overlap

This does not repeat accepted ZIP central-directory parsing, package writing,
CRC/size/timestamp local-header validation, data-descriptor validation,
central/local generic extra-field parsing, Info-ZIP extended timestamp
parsing/writing, NTFS timestamp parsing, gzip stream framing, OPC relationship
preflight, DOCX/ODT package readers, doctemplates, YAML metadata, CSL/citation
handling, table geometry, math/TeX conversion, PDF engine handoff planning,
legacy DOC/CFB extraction, or Markdown/HTML reader/writer behavior. It only
adds bounded ZIP Unix symlink detection and rejection.

## Follow-Up

Keep ZIP64 policy, encrypted ZIP preflight, tar file entries, LZ4 frames, and
higher-level DOCX/EPUB/ODT diagnostics as separate bounded slices unless
concrete Pandoc fixtures require them.
