# Pandoc ODF Manifest Sidecar Order Flags

- Area: Pandoc ODF/ODT OpenDocument package ingestion
- Slice: preserve script and configuration sidecar package classification in manifest-order handoff rows.

## Change

`OpenDocumentPackage` now carries `scriptPackagePart` and `configurationPackagePart`
through `manifestReview.manifestFileEntryOrder`.

`OdfReader` now stores `scriptPackagePart` on parsed manifest items, exposes package
sidecar booleans on `importReport.manifest.packageProvenance.manifestFileEntryOrder`,
and carries `scriptPackagePart` on package provenance part rows.

Bytes remain blocked for script and configuration sidecars; this only exposes
metadata-only package provenance for review handoff.

## Accounting

- Focused PHP behavior coverage after rebase: `phpPass 438 -> 439`, `phpFail 0`.
- Added `lanes/pandoc/tests/OdfManifestSidecarOrderFlagsTest.php` as the mapped
  ODF package-ingestion behavior case.
- Compact package assertions increased in `OpenDocumentPackageTest.php` for the
  existing manifest media-family matrix.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php -l lanes/pandoc/tests/OdfManifestSidecarOrderFlagsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfManifestSidecarOrderFlagsTest.php`: 1 file, 16 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`: 1 file, 1752 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`: touched package-ingestion cases pass; full file remains on the existing broad ODF rendering-output baseline with 22 unrelated failures.
