# pandoc-shared-zip-package-core-current-base-20260608T202158Z

Accepted base: `e804d88dd32d5db061bbd8258db113c523e8f8c3`

## Scope

Implemented bounded malformed ZIP64 end-of-central-directory locator preflight
for the native Pandoc ZIP package primitive. When the ZIP64 EOCD locator is
present but its record offset points to bytes that are absent or do not start
with the ZIP64 EOCD signature, `ZipPackage` now preserves the locator metadata
and reports `zip64-end-of-central-directory-record-missing` as structured
unsupported ZIP64 package metadata.

Strict package import remains fail-closed for ZIP64 archives. The slice only
makes the rejection explainable before Office/EPUB/ODT media handoff.

## Red-first Evidence

- Baseline focused test before patch:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1751 assertions, 0 failures`
- Red-first focused test after adding the malformed ZIP64 locator assertion and
  before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1751 assertions, 1 failures`
  - Failure: `ZIP64 end-of-central-directory locator points to an invalid record`

## Verification

- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1779 assertions, 0 failures`
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
- Diff whitespace:
  `git diff --check -- lanes/pandoc`
  - Result: passed

## Mapping Delta

- Mapped upstream/static support denominator: `2225 -> 2226`
- `phpPass`: `1802 -> 1803`
- `zipPackageCoreSupportCases`: `22 -> 23`
- `mappedZipPackageCoreSupportCases`: `22 -> 23`
- `zipPackageCoreAssertions`: `161 -> 189`
- Focused ZIP package assertions: `1751 -> 1779`

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage` EOCD
scanning, ZIP64 locator/accounting preflight, raw strict import diagnostics,
focused ZIP package tests, and the lane-local WordPress ZIP package preflight
example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
`zip`/`unzip`, `ZipArchive`, external archive tool, online service, live
provider test, or live-service provider test was executed.

## Non-overlap

This does not repeat accepted ZIP slices for central-directory signatures,
trailing deflate bytes, Unicode name collisions, invalid DOS timestamps,
data-descriptor provenance, local-header name/metadata spoofing, split archive
disk markers, broad ZIP64 rejection, ZIP64 local-header offset sentinel
compatibility diagnostics, AES/encryption rejection, duplicate extra-field IDs,
NTFS timestamps, malformed Info-ZIP Unicode metadata, or EOCD comment signature
disambiguation. The slice is limited to malformed ZIP64 EOCD locator record
offset diagnostics.

## Follow-up

For ZIP package follow-up, choose a non-overlapping native package primitive
such as OPC/package cross-checks, ZIP64 central-directory sizing policy, or
package path/security preflight without executing Pandoc, external archive
tools, office tools, online services, or Haskell runners.
