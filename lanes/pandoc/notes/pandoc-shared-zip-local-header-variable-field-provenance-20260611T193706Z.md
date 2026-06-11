# Pandoc Shared ZIP Local Header Variable Field Provenance

## Behavior

This slice adds bounded native ZIP package preflight provenance for local-header
variable fields. `ZipPackage::localHeaderPreflight()` and raw
`ZipPackage::localHeaderSpanPreflight()` now summarize local header name bytes,
local extra-field bytes, local extra-field entry counts, and boolean presence
flags. The same summaries are carried through object strict import preflight and
raw strict import preflight for DOCX/EPUB/ODF package review queues.

## Evidence

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 3201 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 65484 assertions, 0 failures`

## Accounting

- `phpPass`: `3104 -> 3105`
- `mapped`: `3204 -> 3205`
- `mappedZipLocalHeaderVariableFieldCases`: `1`
- `zipLocalHeaderVariableFieldAssertions`: `18`

## Non-Overlap

This does not repeat accepted central-directory variable-field, extra-field ID,
ZIP64, creator-host, local-header name/metadata mismatch, local span, or
strict-import aggregation work. It only adds aggregate local-header
name/extra-field byte provenance and propagation through existing strict
preflight summaries.

No Pandoc, office suites, zip/unzip, ZipArchive, browser renderers, external
validators, online services, live provider tests, or live-service provider
tests were invoked.
