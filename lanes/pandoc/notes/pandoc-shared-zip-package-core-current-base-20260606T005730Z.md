# Pandoc ZIP Package Core Current Base

- Slice: `pandoc-shared-zip-package-core-current-base-20260606T005730Z`
- Base accepted HEAD: `ff7d31e1397095949e33524eafeb5b7160ae8790`
- Scope: native PHP ZIP/OPC package primitive behavior only.

## Behavior

`ZipPackage` now exposes `pathHierarchyPreflight()` and
`assertNoPathHierarchyCollisions()` for file/directory hierarchy collisions
before Office media handoff. The preflight reports same-path file/directory
entries such as `word/media` plus `word/media/`, file entries used as directory
ancestors, and descendants blocked by an ancestor file entry. Strict assertion
rejects those packages before WordPress or another importer treats embedded
Office/EPUB/ODT media as safe package parts.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  failed before implementation with `1 test files, 603 assertions, 1 failures`
  because `ZipPackage::pathHierarchyPreflight()` did not exist.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 630 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`ZipPackage`, `ZipPackageEntry`, focused PHP tests, and the WordPress ZIP
package preflight example. It intentionally does not run Pandoc, Cabal, Haskell
runners, zip/unzip, ZipArchive, Word, LibreOffice, external archive tools,
browser renderers, online sanitizers, online services, or live provider tests.

## Follow-Up

Keep full ZIP64 large-archive support, spanning archives, AES/encrypted payload
support, non-deflate decompressor implementation, cryptographic central
directory signature validation, and strict package-reader default policy wiring
as separate bounded ZIP/package slices.
