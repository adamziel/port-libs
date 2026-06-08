# pandoc-shared-zip-package-core-current-base-20260608T204909Z

Accepted base: `760ca6aa9f81ad19edcddbf9a887d409a553e927`

## Scope

Implemented bounded ZIP64 EOCD accounting diagnostics for native Pandoc ZIP
package preflight. When a ZIP64 end-of-central-directory record is present and
the legacy EOCD entry-count fields are non-sentinel values that disagree with
the ZIP64 record, `ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight()`
now reports:

- `eocdFieldsMatchZip64Record: false`
- `eocdZip64MismatchedFields`
- `zip64-eocd-field-mismatch`

Strict package import still rejects ZIP64 packages. This slice only makes the
rejection explainable before DOCX/EPUB/ODT media handoff.

## Red-First Evidence

- Baseline focused test before patch:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1807 assertions, 0 failures`
- Red-first focused test after adding the accounting assertions and before
  implementation:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1798 assertions, 1 failures`
  - Failure: `eocdFieldsMatchZip64Record` was absent from the ZIP64 accounting
    summary.

## Verification

- Focused test:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1817 assertions, 0 failures`
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`

Root harness not run - isolated micro-slice.

## Mapping Delta

- Mapped upstream/static support denominator: `2258 -> 2259`
- `phpPass`: unchanged at `1834` because this adds assertions inside an
  existing PHP PASS case.
- `zipPackageCoreSupportCases`: `22 -> 23`
- `mappedZipPackageCoreSupportCases`: `22 -> 23`
- `zipPackageCoreAssertions`: `161 -> 171`
- Focused ZIP package assertions: `1807 -> 1817`

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage` EOCD
scanning, ZIP64 EOCD accounting preflight, raw strict import diagnostics, the
focused ZIP package tests, and the lane-local WordPress ZIP package preflight
example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
`zip`/`unzip`, `ZipArchive`, external archive tool, online service, live
provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ZIP slices for central-directory signatures,
trailing deflate bytes, Unicode name collisions, invalid DOS timestamps,
data-descriptor provenance, local-header name/metadata spoofing, split archive
disk markers, broad ZIP64 rejection, malformed ZIP64 locator missing-record
diagnostics, ZIP64 extra fields, ZIP64 local-header offset sentinel
compatibility diagnostics, AES/encryption rejection, duplicate extra-field IDs,
NTFS timestamps, malformed Info-ZIP Unicode metadata, or EOCD comment signature
disambiguation. The slice is limited to ZIP64 EOCD-vs-legacy-EOCD entry-count
accounting diagnostics.

## Follow-Up

For ZIP package follow-up, choose a non-overlapping native package primitive
such as OPC/package cross-checks, ZIP64 record-size/payload-size policy, or
package path/security preflight without executing Pandoc, external archive
tools, office tools, online services, or Haskell runners.
