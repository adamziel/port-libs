# pandoc-shared-zip-package-core-current-base-20260608T195652Z

Accepted base: `5ab7f3dd2c18dec97fb5d2517ffc7501ba04e5b8`

## Scope

Implemented bounded ZIP64 local-header compatibility preflight for the native
Pandoc ZIP package primitive. `ZipPackage::zip64ExtraFieldPreflight()` now
resolves ZIP64 local-header offset sentinels, reads the referenced local header,
and reports central/local raw-name, decoded-name, general-purpose flag, and
compression-method compatibility before strict Office/EPUB/ODT package import.

The bounded reader still rejects ZIP64 package import. This slice only makes
the rejection explainable when a central-directory entry uses a ZIP64 local
header offset to point at a different local header name.

## Red-first Evidence

- Baseline focused test before patch:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1684 assertions, 0 failures`
- One-off in-memory ZIP64 offset-sentinel probe before patch:
  - Result: `["word/document.xml",["zip64-extra-field","zip64-size-or-offset-sentinel"],0]`
  - The preflight surfaced ZIP64 metadata but did not report the central/local
    header name mismatch.

## Verification

- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1711 assertions, 0 failures`
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`
- PHP lint:
  `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: `No syntax errors detected in lanes/pandoc/src/ZipPackage.php`
- PHP lint:
  `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `No syntax errors detected in lanes/pandoc/tests/ZipPackageTest.php`
- PHP lint:
  `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-zip-package-preflight.php`

## Mapping Delta

- Mapped upstream/static support denominator: `2196 -> 2197`
- `phpPass`: `1778 -> 1779`
- `zipPackageCoreSupportCases`: `22 -> 23`
- `mappedZipPackageCoreSupportCases`: `22 -> 23`
- `zipPackageCoreAssertions`: `161 -> 188`
- Focused ZIP package assertions: `1684 -> 1711`

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`
central-directory parsing, ZIP64 extra-field decoding, local-header scanning,
raw strict import preflight, focused ZIP tests, and the lane-local WordPress
ZIP package preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
`zip`/`unzip`, `ZipArchive`, external archive tool, online service, live
provider test, or live-service provider test was executed.

## Non-overlap

This does not repeat accepted ZIP slices for central-directory signatures,
trailing deflate bytes, Unicode name collisions, invalid DOS timestamps,
data-descriptor provenance, non-ZIP64 local-header name/metadata spoofing,
split archive disk markers, broad ZIP64 rejection, AES/encryption rejection,
duplicate extra-field IDs, NTFS timestamps, malformed Info-ZIP Unicode
metadata, or EOCD comment signature disambiguation. The slice is limited to
ZIP64 local-header offset sentinel compatibility diagnostics.

## Follow-up

For ZIP package follow-up, choose a non-overlapping native package primitive
such as OPC/package cross-checks or bounded ZIP64 EOCD planning diagnostics
without executing Pandoc, external archive tools, office tools, online
services, or Haskell runners.
