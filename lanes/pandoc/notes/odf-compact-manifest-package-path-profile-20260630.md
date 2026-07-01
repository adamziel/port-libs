# ODF Compact Manifest Package Path Profile - 2026-06-30

Slice: `plib-ab8x7`, ODF/ODT OpenDocument package ingestion core blocker.

Base: `origin/main` at `959433e44`.

## Summary

`OpenDocumentPackage::summarize()` now includes a metadata-only
`manifestReview.manifestPackagePathProfile` section for ODT manifest
file-entry paths. The profile records package root buckets, path depth buckets,
file extension buckets, encoded reference counts, suffix reference counts,
duplicate basenames, and per-entry path shape fields while preserving decoded
package paths and original manifest references.

The change does not alter byte exposure, manifest validation, media handoff,
or direct-format parity accounting.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - Result: `1 test files, 1946 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 5066 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfManifestSidecarOrderFlagsTest.php`
  - Result: `1 test files, 16 assertions, 0 failures`
- `git diff --check`

No Pandoc, office suite, TeX, browser, zip/unzip, Jupyter, Node, external
validator, online service, or live provider test was invoked.
