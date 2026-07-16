# Pandoc Shared ZIP Package Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260606T061436Z`

Base accepted HEAD: `98d37dedec48e231d559abd333dd1d6b05575268`

## Implementation

- Added `ZipPackage::strictImportPreflight()` and
  `ZipPackage::assertStrictImportable()`.
- The aggregate strict preflight combines the existing native ZIP package
  checks for archive layout, comments, compression methods, extra-field
  consistency, path hierarchy collisions, executable permissions, creator host
  systems, size and expansion-ratio limits, data-descriptor metadata, and
  payload read integrity.
- Updated the WordPress ZIP package preflight example to report strict import
  acceptance for a clean package and strict rejection for package/entry comment
  metadata.

## Source Truth

Pandoc DOCX, ODT, and EPUB readers consume ZIP/OPC-style packages before
format-specific parsing. This slice ports the support-library contract: callers
need one strict native import gate that composes already-supported ZIP safety
preflights before exposing package bytes to document readers.

This does not implement DOCX/ODT/EPUB reader default policy wiring, ZIP64,
split archives, encrypted or AES entries, non-deflate compression methods,
filesystem extraction, cryptographic signature validation, or any external
archive runner.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before implementation after adding the strict-import expectation:
    `1 test files, 688 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\ZipPackage::strictImportPreflight()`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 719 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - Final PHP lint, JSON validation, focused verification, example smoke, and
    `git diff --check -- lanes/pandoc` are recorded in the worker handoff.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1225 -> 1226`.
- `benchmarkDenominator.mapped`: `1668 -> 1669`.
- Focused ZIP package coverage: `+1` PASS case and `688 -> 719`
  red-first-to-green assertions in `ZipPackageTest.php`.
- Manifest ZIP counters now record `zipPackageCoreSupportCases=22`,
  `mappedZipPackageCoreSupportCases=22`, and
  `zipPackageCoreAssertions=162`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage` parser and preflight methods plus the lane-local WordPress ZIP
package preflight example. Full upstream Pandoc runner parity remains blocked
on hydrating and building the pinned Haskell test executables; this ZIP package
support behavior is covered by focused native PHP tests and does not require
Pandoc, Cabal, Haskell runners, Word, LibreOffice, `zip`, `unzip`, ZipArchive,
external archive tools, browser renderers, online sanitizers, or online
services.

## Non-Overlap

This does not repeat accepted central/local extra-field parsing, NTFS timestamp
preflight, central-directory signature metadata, unsupported compression-method
preflight, trailing-deflate payload integrity, ZIP64 extra-field rejection,
Unix symlink rejection, drive-letter path rejection, ODT mimetype first-entry
policy, OPC relationships/content-types, archive compression helpers, DOCX,
ODT, EPUB3, doctemplates, YAML metadata, CSL/BibTeX, table geometry, math/TeX,
PDF handoff, legacy DOC/CFB, charset, syntax highlighting, or Markdown/HTML
reader and writer behavior. It owns only the strict aggregate policy that makes
those already-native ZIP checks harder for package import callers to skip.

## Follow-Up

Wire the strict preflight into the DOCX, ODT, and EPUB import entry points as
separate bounded reader slices. Keep ZIP64, split archive handling, encrypted
entries, non-deflate compression methods, and cryptographic signature
validation as separate support rows unless concrete package fixtures require
them.
