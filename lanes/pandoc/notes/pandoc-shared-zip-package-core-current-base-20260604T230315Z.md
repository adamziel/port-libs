# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260604T230315Z`

Base accepted HEAD: `dfe72a34a9a7921b2b472d062fc3e25f4922e152`

## Implementation

- Extended `ZipPackageEntry` to parse the full Info-ZIP `0x5455` extended
  timestamp field, including optional modified, accessed, and created Unix
  timestamps.
- Added local-header extended timestamp accessors on `ZipPackage` so DOCX,
  EPUB, ODT, and review-packet media preflight can inspect access/creation
  metadata that central-directory records commonly omit.
- Kept existing last-modified behavior intact while validating local and
  central timestamp fields when both sides expose the same value.
- Strengthened malformed `0x5455` extra-field guards so truncated access or
  creation timestamp payloads are rejected before package bytes are exposed.
- Updated `wordpress-zip-package-preflight.php` to self-test local
  modified/accessed/created extended timestamp metadata for media provenance.

## Source Truth

This remains inside the accepted shared ZIP package support row for Pandoc
DOCX/EPUB/ODT-style containers. The `0x5455` extended timestamp field is the
standard Info-ZIP metadata record used by common ZIP package writers. The slice
ports the bounded package contract only: native PHP ZIP central/local metadata
inspection, no external `zip`/`unzip`, office tool, Pandoc, Haskell runner, or
online service execution.

## Verification

- Red-first `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 166 assertions, 3 failures`.
- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/ZipPackageEntry.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 184 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `13 test files, 3,707 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native
`ZipPackage` reader/writer, `ZipPackageEntry` extra-field parser, and PHP zlib
raw DEFLATE support already used by the Pandoc lane. No Pandoc, Cabal,
Haskell, Word, LibreOffice, TeX/PDF, external template, `zip`, `unzip`, or
online service dependency was added.

## Non-Overlap

This does not repeat OPC content-types or relationship graph behavior, NTFS
timestamp parsing, Unix symlink rejection, data-descriptor parsing, archive
compression stream handling, PDF engine handoff diagnostics, DOCX body parsing,
EPUB3 package handoff, ODT parsing, doctemplate rendering, YAML metadata, CSL,
BibTeX, table geometry, math/TeX, charset/Unicode, or legacy DOC/CFB slices.
It only extends shared ZIP package primitives with local extended timestamp
access/creation preflight and malformed timestamp guards.

## Follow-Up

Keep ZIP64, AES/encrypted archives, central-directory encryption, non-deflate
compression methods, filename charset conversion, and executable permission
policy as separate bounded ZIP package slices if richer upstream package
fixtures require them.
