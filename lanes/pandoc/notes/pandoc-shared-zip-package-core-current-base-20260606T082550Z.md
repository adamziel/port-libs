# Pandoc ZIP Package Core Current Base - WinZip AES Extra Field

Slice: `pandoc-shared-zip-package-core-current-base-20260606T082550Z`
Base: `e1d03b8cc26cd725291bff3cc15a9b256bfbd961`

## Behavior

- Added bounded WinZip AES extra-field rejection to the shared ZIP package
  primitive.
- `ZipPackageEntry::extraFieldsFromData()` now rejects extra field id `0x9901`
  for central-directory fields, local-header fields, and generated
  `extraFieldData`.
- The WordPress ZIP preflight smoke now reports `zipAesExtraFieldPolicy` as
  rejected before embedded Office/media bytes are exposed.

## Evidence

- Baseline `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 749 assertions, 0 failures`.
- Red-first `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  failed with `1 test files, 750 assertions, 1 failures` because AES extra
  metadata did not throw.
- After implementation `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 752 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`ZipPackageEntry`, in-process ZIP fixtures, and the existing WordPress ZIP
preflight example. It did not run Pandoc, Cabal, Haskell runners, `zip`,
`unzip`, `ZipArchive`, Word, LibreOffice, external archive tools, browser
renderers, online sanitizers, online services, live provider tests, or
live-service provider tests.

## Non-Overlap

This does not repeat accepted central-directory parsing, data descriptors,
CRC/local-header integrity, ZIP64 EOCD or ZIP64 extra-field rejection, Unix
symlink/special-file rejection, central-directory digital signature provenance,
executable permission preflight, unsupported compression-method preflight, or
trailing-deflate payload-integrity checks. The new surface is only bounded
WinZip AES extra-field package rejection.

## Follow-Up

Keep full ZIP64 large-archive support, multi-disk spanning archives,
encrypted/AES payload handling, cryptographic central-directory signature
verification, and non-deflate decompressor implementation as separate bounded
ZIP package slices if concrete Pandoc fixtures require them.
